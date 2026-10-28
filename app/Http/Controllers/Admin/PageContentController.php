<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PageContent;
use App\Models\Post;
use App\Services\Content\Images;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

/**
 * The words and photos on the About page and the home page testimony.
 */
class PageContentController extends Controller
{
    private const PHOTO = ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:15360'];

    public function edit(string $page): View
    {
        abort_unless(in_array($page, PageContent::PAGES, true), 404);

        return view('admin.pages', [
            'page' => $page,
            'content' => PageContent::for($page),
            'swatches' => Post::SWATCHES,
        ]);
    }

    public function update(Request $request, string $page, Images $images): RedirectResponse
    {
        abort_unless(in_array($page, PageContent::PAGES, true), 404);
        $current = PageContent::for($page);

        $content = $page === 'about' ? $this->about($request, $current, $images) : $this->home($request, $current, $images);
        PageContent::put($page, $content);

        return redirect()->route('admin.pages.edit', $page)->with('status', ($page === 'about' ? 'About page' : 'Home page').' saved.');
    }

    /**
     * @param  array<string, mixed>  $current
     * @return array<string, mixed>
     */
    private function about(Request $request, array $current, Images $images): array
    {
        $data = $request->validate([
            'hero_title' => ['required', 'string', 'max:120'],
            'hero_text' => ['nullable', 'string', 'max:400'],
            'story_heading' => ['required', 'string', 'max:120'],
            'story' => ['nullable', 'string', 'max:4000'],
            'story_photo' => self::PHOTO,
            'story_photo_alt' => ['nullable', 'string', 'max:200'],
            'remove_story_photo' => ['nullable', 'boolean'],
            'mission' => ['nullable', 'string', 'max:300'],
            'vision' => ['nullable', 'string', 'max:300'],
            'values' => ['nullable', 'string', 'max:1000'],
            'beliefs' => ['nullable', 'string', 'max:3000'],
            'leaders' => ['nullable', 'array', 'max:16'],
            'leaders.*.from' => ['nullable', 'integer', 'min:0'],
            'leaders.*.name' => ['nullable', 'string', 'max:100'],
            'leaders.*.role' => ['nullable', 'required_with:leaders.*.name', 'string', 'max:100'],
            'leaders.*.swatch' => ['nullable', Rule::in(Post::SWATCHES)],
            'leaders.*.photo' => self::PHOTO,
            'leaders.*.remove_photo' => ['nullable', 'boolean'],
            'faqs' => ['nullable', 'array', 'max:20'],
            'faqs.*.q' => ['nullable', 'string', 'max:200'],
            'faqs.*.a' => ['nullable', 'required_with:faqs.*.q', 'string', 'max:1000'],
        ], ['leaders.*.role.required_with' => 'Give each leader a role.', 'faqs.*.a.required_with' => 'Every question needs an answer.']);

        $content = [
            'hero_title' => $data['hero_title'],
            'hero_text' => $data['hero_text'] ?? '',
            'story_heading' => $data['story_heading'],
            'story' => $data['story'] ?? '',
            'story_photo_alt' => $data['story_photo_alt'] ?? null,
            'mission' => $data['mission'] ?? '',
            'vision' => $data['vision'] ?? '',
            'values' => $this->lines($data['values'] ?? '', 10),
            'beliefs' => $this->lines($data['beliefs'] ?? '', 20),
            'story_photo' => $this->photo($request->file('story_photo'), $current['story_photo'] ?? null, $request->boolean('remove_story_photo'), 'story_photo', $images),
        ];

        // Leaders: blank rows are dropped; photos follow the row they came from.
        $kept = [];
        $leaders = [];
        foreach ($data['leaders'] ?? [] as $i => $row) {
            $was = isset($row['from']) ? ($current['leaders'][(int) $row['from']] ?? null) : null;
            if (trim($row['name'] ?? '') === '') {
                continue;
            }
            $photo = $this->photo($request->file("leaders.{$i}.photo"), $was['photo'] ?? null, (bool) ($row['remove_photo'] ?? false), "leaders.{$i}.photo", $images);
            $kept[] = $photo;
            $leaders[] = [
                'name' => trim($row['name']),
                'role' => trim($row['role'] ?? ''),
                'swatch' => $row['swatch'] ?? 'navy',
                'photo' => $photo,
            ];
        }
        // Photos of leaders who were removed.
        foreach ($current['leaders'] ?? [] as $old) {
            if (($old['photo'] ?? null) && ! in_array($old['photo'], $kept, true)) {
                $images->delete($old['photo'], $this->thumbOf($old['photo']));
            }
        }
        $content['leaders'] = $leaders;

        $content['faqs'] = collect($data['faqs'] ?? [])
            ->filter(fn ($f) => trim($f['q'] ?? '') !== '')
            ->map(fn ($f) => ['q' => trim($f['q']), 'a' => trim($f['a'] ?? '')])
            ->values()->all();

        return $content;
    }

    /**
     * @param  array<string, mixed>  $current
     * @return array<string, mixed>
     */
    private function home(Request $request, array $current, Images $images): array
    {
        $data = $request->validate([
            'quote' => ['required', 'string', 'max:500'],
            'name' => ['required', 'string', 'max:100'],
            'initials' => ['nullable', 'string', 'max:3'],
            'role' => ['nullable', 'string', 'max:100'],
            'photo' => self::PHOTO,
            'remove_photo' => ['nullable', 'boolean'],
        ]);

        $initials = trim($data['initials'] ?? '') ?: collect(preg_split('/\s+/', preg_replace('/[^\pL\s]/u', '', $data['name'])))
            ->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->join('');

        return ['testimony' => [
            'quote' => $data['quote'],
            'name' => $data['name'],
            'initials' => mb_strtoupper($initials),
            'role' => $data['role'] ?? '',
            'photo' => $this->photo($request->file('photo'), $current['testimony']['photo'] ?? null, $request->boolean('remove_photo'), 'photo', $images),
        ]];
    }

    /**
     * The photo to keep: a new upload, the existing one, or none. Replaced photos are deleted.
     */
    private function photo(?UploadedFile $file, ?string $existing, bool $remove, string $field, Images $images): ?string
    {
        if (! $file && ! $remove) {
            return $existing;
        }
        if ($existing) {
            $images->delete($existing, $this->thumbOf($existing));
        }
        if (! $file) {
            return null;
        }

        try {
            return $images->store($file, 'pages', 1400, 480)['path'];
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages([$field => $e->getMessage()]);
        }
    }

    private function thumbOf(string $path): string
    {
        return preg_replace('/\.webp$/', '-thumb.webp', $path);
    }

    /**
     * @return list<string>
     */
    private function lines(string $text, int $max): array
    {
        return collect(preg_split('/\R/', $text))->map(fn ($l) => trim($l))->filter()->take($max)->values()->all();
    }
}
