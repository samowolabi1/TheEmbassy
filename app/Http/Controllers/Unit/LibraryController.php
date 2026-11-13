<?php

namespace App\Http\Controllers\Unit;

use App\Http\Controllers\Controller;
use App\Models\MediaFile;
use App\Models\MediaFolder;
use App\Models\Unit;
use App\Services\Media\MediaLibrary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The media unit's private file library: photos, videos, designs, logos and documents, in
 * folders, tagged and searchable. Work delivered for requests is kept here too.
 */
class LibraryController extends Controller
{
    public function index(Request $request, Unit $unit): View
    {
        $kind = array_key_exists($request->query('kind'), MediaFile::KINDS) ? $request->query('kind') : null;
        $folder = $request->query('folder');
        $q = trim((string) $request->query('q'));
        $fromRequests = $request->query('from') === 'requests';

        $files = MediaFile::with('folder', 'uploader.member', 'request')
            ->where('purpose', $fromRequests ? 'deliverable' : 'library')
            ->when($kind, fn ($query) => $query->where('kind', $kind))
            ->when(! $fromRequests && $folder === 'none', fn ($query) => $query->whereNull('media_folder_id'))
            ->when(! $fromRequests && ctype_digit((string) $folder), fn ($query) => $query->where('media_folder_id', (int) $folder))
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('title', 'like', "%{$q}%")->orWhere('tags', 'like', "%{$q}%")->orWhere('description', 'like', "%{$q}%")->orWhere('original_name', 'like', "%{$q}%")))
            ->latest('id')->paginate(24)->withQueryString();

        return view('unit.media.library', [
            'unit' => $unit,
            'files' => $files,
            'folders' => MediaFolder::withCount(['files' => fn ($query) => $query->where('purpose', 'library')])->orderBy('name')->get(),
            'filters' => ['kind' => $kind, 'folder' => $folder, 'q' => $q, 'from' => $fromRequests ? 'requests' : null],
            'canLead' => Gate::allows('lead-media', $unit),
            'used' => (int) MediaFile::sum('size'),
            'maxMb' => (int) config('church.media.max_upload_mb', 500),
        ]);
    }

    public function upload(Request $request, Unit $unit, MediaLibrary $library): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'files' => ['required', 'array', 'max:20'],
            'files.*' => MediaLibrary::fileRules(),
            'media_folder_id' => ['nullable', Rule::exists('media_folders', 'id')],
            'tags' => ['nullable', 'string', 'max:300'],
        ], [
            'files.required' => 'Choose at least one file.',
            'files.max' => 'Upload up to 20 files at a time.',
            'files.*.max' => 'Each file can be up to '.config('church.media.max_upload_mb', 500).' MB. Add bigger files as a link.',
            'files.*.extensions' => 'One of those files isn\'t a type the library takes.',
        ]);

        foreach ($request->file('files') as $file) {
            $library->store($file, $request->user(), 'library', null, ['media_folder_id' => $data['media_folder_id'] ?? null, 'tags' => $this->tags($data['tags'] ?? null)]);
        }
        $to = route('unit.library.index', [$unit, 'folder' => $data['media_folder_id'] ?? null]);
        $message = count($request->file('files')).' '.str('file')->plural(count($request->file('files'))).' added.';
        if ($request->expectsJson()) {
            session()->flash('status', $message);

            return response()->json(['redirect' => $to]);
        }

        return redirect($to)->with('status', $message);
    }

    public function link(Request $request, Unit $unit, MediaLibrary $library): RedirectResponse
    {
        $data = $request->validate([
            'url' => ['required', 'url:http,https', 'max:500'],
            'title' => ['required', 'string', 'max:150'],
            'media_folder_id' => ['nullable', Rule::exists('media_folders', 'id')],
            'tags' => ['nullable', 'string', 'max:300'],
        ]);
        $library->link($data['url'], $data['title'], $request->user(), 'library', null, ['media_folder_id' => $data['media_folder_id'] ?? null, 'tags' => $this->tags($data['tags'] ?? null)]);

        return back()->with('status', 'Link added.');
    }

    public function update(Request $request, Unit $unit, MediaFile $file): RedirectResponse
    {
        abort_if($file->purpose === 'reference', 404);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'media_folder_id' => ['nullable', Rule::exists('media_folders', 'id')],
            'tags' => ['nullable', 'string', 'max:300'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);
        $file->update([...$data, 'tags' => $this->tags($data['tags'] ?? null)]);
        // Filing a delivered piece of work in a folder brings it into the main library.
        if ($file->purpose === 'deliverable' && ! empty($data['media_folder_id'])) {
            $file->forceFill(['purpose' => 'library'])->save();
        }

        return back()->with('status', 'Saved.');
    }

    public function destroy(Request $request, Unit $unit, MediaFile $file, MediaLibrary $library): RedirectResponse
    {
        abort_if($file->purpose === 'reference', 404);
        abort_unless($file->uploaded_by === $request->user()->id || Gate::allows('lead-media', $unit), 403, 'Only whoever added it or a leader can delete it.');
        $library->delete($file);

        return back()->with('status', '"'.$file->title.'" deleted.');
    }

    public function storeFolder(Request $request, Unit $unit): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80', Rule::unique('media_folders', 'name')], 'description' => ['nullable', 'string', 'max:200']]);
        $folder = MediaFolder::create($data);

        return redirect()->route('unit.library.index', [$unit, 'folder' => $folder->id])->with('status', "Folder \"{$folder->name}\" made.");
    }

    public function updateFolder(Request $request, Unit $unit, MediaFolder $folder): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80', Rule::unique('media_folders', 'name')->ignore($folder)], 'description' => ['nullable', 'string', 'max:200']]);
        $folder->update($data);

        return back()->with('status', 'Folder saved.');
    }

    /**
     * The files stay in the library, just out of the folder.
     */
    public function destroyFolder(Request $request, Unit $unit, MediaFolder $folder): RedirectResponse
    {
        $folder->files()->update(['media_folder_id' => null]);
        $folder->delete();

        return redirect()->route('unit.library.index', $unit)->with('status', "Folder \"{$folder->name}\" removed. Its files are under \"Not in a folder\".");
    }

    private function tags(?string $tags): ?string
    {
        $list = collect(explode(',', (string) $tags))->map(fn ($t) => mb_strtolower(trim($t)))->filter()->unique()->take(15);

        return $list->isEmpty() ? null : mb_substr($list->join(', '), 0, 300);
    }
}
