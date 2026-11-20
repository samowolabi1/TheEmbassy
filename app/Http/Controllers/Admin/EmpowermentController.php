<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmpowermentApplication;
use App\Models\EmpowermentProgramme;
use App\Models\EmpowermentQuestion;
use App\Services\Content\Images;
use App\Services\Empowerment\Notices;
use App\Support\Xlsx;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Youth empowerment: programmes with their banner, dates and choices, the application form, and
 * reviewing applications.
 */
class EmpowermentController extends Controller
{
    public function index(): View
    {
        return view('admin.empowerment.index', [
            'programmes' => EmpowermentProgramme::withCount(['applications', 'applications as new_count' => fn ($q) => $q->where('status', 'new'), 'tracks'])->latest()->get(),
        ]);
    }

    /**
     * A new programme, as a draft, with a few starting questions to edit.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:120']]);
        $programme = new EmpowermentProgramme([
            'title' => $data['title'],
            'slug' => $this->slug($data['title']),
            'tagline' => 'Learn a skill. Start something. Build your future.',
            'max_choices' => 2,
            'min_age' => 16,
            'max_age' => 35,
        ]);
        $programme->created_by = $request->user()->id;
        $programme->save();
        foreach ([
            ['Why do you want to join, and what do you hope to do with what you learn?', 'textarea', null, true],
            ['Have you had any training in this before?', 'yes_no', null, true],
            ['What do you do at the moment?', 'radio', ['Student', 'Working', 'Running a business', 'Looking for work', 'Other'], true],
            ['Are you available on weekdays?', 'yes_no', null, false],
        ] as $i => [$label, $type, $options, $required]) {
            $programme->questions()->create(['label' => $label, 'type' => $type, 'options' => $options, 'is_required' => $required, 'position' => $i]);
        }

        return redirect()->route('admin.empowerment.edit', $programme)->with('status', 'Programme created as a draft. Add a banner and the choices on offer, check the form, then publish it.');
    }

    /**
     * The applications to a programme.
     */
    public function show(Request $request, EmpowermentProgramme $programme): View
    {
        $status = array_key_exists((string) $request->query('status'), EmpowermentApplication::STATUSES) ? $request->query('status') : null;
        $track = $request->integer('track') ?: null;
        $q = trim((string) $request->query('q'));

        return view('admin.empowerment.show', [
            'programme' => $programme,
            'tracks' => $programme->tracks()->withCount(['applicants', 'accepted'])->get(),
            'counts' => $programme->applications()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'applications' => $this->filtered($programme, $status, $track, $q)->with('choices')->latest()->paginate(30)->withQueryString(),
            'filters' => compact('status', 'track', 'q'),
        ]);
    }

    public function application(EmpowermentProgramme $programme, EmpowermentApplication $application): View
    {
        abort_unless($application->empowerment_programme_id === $programme->id, 404);

        return view('admin.empowerment.application', [
            'programme' => $programme,
            'application' => $application->load('choices', 'track', 'member', 'decider'),
            'questions' => $programme->questions()->get(),
            'tracks' => $programme->tracks()->withCount('accepted')->get(),
        ]);
    }

    public function decide(Request $request, EmpowermentProgramme $programme, EmpowermentApplication $application, Notices $notices): RedirectResponse
    {
        abort_unless($application->empowerment_programme_id === $programme->id, 404);
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(EmpowermentApplication::STATUSES))],
            'empowerment_track_id' => ['nullable', Rule::exists('empowerment_tracks', 'id')->where('empowerment_programme_id', $programme->id)],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);
        if (in_array($data['status'], ['accepted', 'completed'], true) && empty($data['empowerment_track_id'])) {
            $data['empowerment_track_id'] = $application->choices()->value('empowerment_tracks.id');
        }
        $changed = $application->status !== $data['status'];
        $application->forceFill([
            'status' => $data['status'],
            'empowerment_track_id' => in_array($data['status'], ['accepted', 'completed'], true) ? $data['empowerment_track_id'] : $application->empowerment_track_id,
            'admin_note' => $data['admin_note'] ?? null,
            'decided_by' => $changed ? $request->user()->id : $application->decided_by,
            'decided_at' => $changed ? now() : $application->decided_at,
        ])->save();
        if ($changed && $request->boolean('notify')) {
            $notices->statusChanged($application->fresh(['programme', 'track']));
        }

        return back()->with('status', "{$application->name}: ".$application->statusLabel().'.'.($changed && $request->boolean('notify') ? ' They\'ve been told.' : ''));
    }

    /**
     * Change the status of several applications at once.
     */
    public function bulk(Request $request, EmpowermentProgramme $programme, Notices $notices): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
            'status' => ['required', Rule::in(array_keys(EmpowermentApplication::STATUSES))],
        ], ['ids.required' => 'Tick the applications to change first.']);
        $apps = $programme->applications()->whereIn('id', $data['ids'])->with('choices')->get();
        foreach ($apps as $a) {
            if ($a->status === $data['status']) {
                continue;
            }
            $a->forceFill([
                'status' => $data['status'],
                'empowerment_track_id' => in_array($data['status'], ['accepted', 'completed'], true) ? ($a->empowerment_track_id ?? $a->choices->first()?->id) : $a->empowerment_track_id,
                'decided_by' => $request->user()->id, 'decided_at' => now(),
            ])->save();
            if ($request->boolean('notify')) {
                $notices->statusChanged($a->fresh(['programme', 'track']));
            }
        }

        return back()->with('status', $apps->count().' '.Str::plural('application', $apps->count()).' now: '.EmpowermentApplication::STATUSES[$data['status']].'.');
    }

    public function edit(EmpowermentProgramme $programme): View
    {
        return view('admin.empowerment.edit', [
            'programme' => $programme,
            'tracks' => $programme->tracks()->withCount(['applicants', 'accepted'])->get(),
            'questions' => $programme->questions()->get(),
            'types' => EmpowermentQuestion::TYPES,
        ]);
    }

    public function update(Request $request, EmpowermentProgramme $programme, Images $images): RedirectResponse
    {
        $data = $request->validateWithBag('settings', [
            'title' => ['required', 'string', 'max:120'],
            'tagline' => ['nullable', 'string', 'max:200'],
            'intro' => ['nullable', 'string', 'max:5000'],
            'opens_on' => ['nullable', 'date'],
            'closes_on' => ['nullable', 'date', 'after_or_equal:opens_on'],
            'max_choices' => ['required', 'integer', 'between:1,3'],
            'min_age' => ['nullable', 'integer', 'between:5,99'],
            'max_age' => ['nullable', 'integer', 'between:5,99', 'gte:min_age'],
            'thank_you' => ['nullable', 'string', 'max:600'],
            'banner' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ], ['closes_on.after_or_equal' => 'The closing date must be on or after the opening date.', 'max_age.gte' => 'The oldest age must be at least the youngest.', 'banner.max' => 'Choose a banner under 8 MB.']);

        try {
            if ($request->boolean('remove_banner') || $request->hasFile('banner')) {
                $images->delete($programme->banner_path);
                $programme->banner_path = null;
            }
            if ($file = $request->file('banner')) {
                $saved = $images->store($file, 'empowerment', 2000, 160);
                $images->delete($saved['thumb_path']);
                $programme->banner_path = $saved['path'];
            }
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['banner' => $e->getMessage()], 'settings');
        }
        $programme->fill([...collect($data)->except('banner')->all(), 'is_published' => $request->boolean('is_published')]);
        if ($programme->is_published && ! $programme->tracks()->where('is_active', true)->exists()) {
            $programme->is_published = false;
            $programme->save();

            return back()->with('status', 'Saved, but not published yet: add at least one choice people can apply for first.');
        }
        $programme->save();

        return back()->with('status', $programme->is_published ? 'Saved. The page is live at '.route('empowerment.show', $programme).'.' : 'Saved as a draft.');
    }

    /**
     * Add, edit, reorder and remove the choices people pick from.
     */
    public function tracks(Request $request, EmpowermentProgramme $programme): RedirectResponse
    {
        $data = $request->validateWithBag('tracks', [
            'tracks' => ['array', 'max:40'],
            'tracks.*.name' => ['nullable', 'string', 'max:80'],
            'tracks.*.description' => ['nullable', 'string', 'max:500'],
            'tracks.*.slots' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'tracks.*.position' => ['nullable', 'integer', 'min:0', 'max:999'],
            'new.name' => ['nullable', 'string', 'max:80'],
            'new.description' => ['nullable', 'string', 'max:500'],
            'new.slots' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);
        foreach ($data['tracks'] ?? [] as $id => $row) {
            $track = $programme->tracks()->whereKey($id)->first();
            if (! $track) {
                continue;
            }
            if ($request->boolean("tracks.{$id}.remove")) {
                // Choices people already picked are kept (switched off) so their applications stay whole.
                $track->applicants()->exists() ? $track->update(['is_active' => false]) : $track->delete();

                continue;
            }
            if (blank($row['name'] ?? null)) {
                continue;
            }
            $track->update([
                'name' => trim($row['name']), 'description' => $row['description'] ?? null, 'slots' => $row['slots'] ?? null,
                'position' => (int) ($row['position'] ?? 0), 'is_active' => $request->boolean("tracks.{$id}.is_active"),
            ]);
        }
        if (filled($data['new']['name'] ?? null)) {
            $programme->tracks()->create([
                'name' => trim($data['new']['name']), 'description' => $data['new']['description'] ?? null, 'slots' => $data['new']['slots'] ?? null,
                'position' => (int) $programme->tracks()->max('position') + 1, 'is_active' => true,
            ]);
        }

        return redirect()->to(route('admin.empowerment.edit', $programme).'#choices')->with('status', 'Choices saved.');
    }

    /**
     * Add, edit, reorder and remove the questions on the form.
     */
    public function questions(Request $request, EmpowermentProgramme $programme): RedirectResponse
    {
        $rules = [
            'questions' => ['array', 'max:40'],
            'questions.*.label' => ['nullable', 'string', 'max:200'],
            'questions.*.help' => ['nullable', 'string', 'max:300'],
            'questions.*.type' => ['nullable', Rule::in(array_keys(EmpowermentQuestion::TYPES))],
            'questions.*.options' => ['nullable', 'string', 'max:2000'],
            'questions.*.position' => ['nullable', 'integer', 'min:0', 'max:999'],
            'new.label' => ['nullable', 'string', 'max:200'],
            'new.help' => ['nullable', 'string', 'max:300'],
            'new.type' => ['nullable', Rule::in(array_keys(EmpowermentQuestion::TYPES))],
            'new.options' => ['nullable', 'string', 'max:2000'],
        ];
        $data = $request->validateWithBag('questions', $rules);
        $options = fn (?string $text, string $type) => in_array($type, EmpowermentQuestion::WITH_OPTIONS, true)
            ? array_values(array_unique(array_filter(array_map('trim', preg_split('/\r?\n/', (string) $text))))) : null;

        foreach ($data['questions'] ?? [] as $id => $row) {
            $question = $programme->questions()->whereKey($id)->first();
            if (! $question) {
                continue;
            }
            if ($request->boolean("questions.{$id}.remove") || blank($row['label'] ?? null)) {
                $question->delete(); // answers already given stay on their applications

                continue;
            }
            $type = $row['type'] ?? $question->type;
            $list = $options($row['options'] ?? null, $type);
            if ($list !== null && count($list) < 2) {
                return back()->withErrors(['questions' => '"'.$row['label'].'" needs at least two options, one per line.'], 'questions');
            }
            $question->update(['label' => trim($row['label']), 'help' => $row['help'] ?? null, 'type' => $type, 'options' => $list,
                'is_required' => $request->boolean("questions.{$id}.is_required"), 'position' => (int) ($row['position'] ?? 0)]);
        }
        if (filled($data['new']['label'] ?? null)) {
            $type = $data['new']['type'] ?? 'text';
            $list = $options($data['new']['options'] ?? null, $type);
            if ($list !== null && count($list) < 2) {
                return back()->withInput()->withErrors(['questions' => 'Give the new question at least two options, one per line.'], 'questions');
            }
            $programme->questions()->create(['label' => trim($data['new']['label']), 'help' => $data['new']['help'] ?? null, 'type' => $type, 'options' => $list,
                'is_required' => $request->boolean('new.is_required'), 'position' => (int) $programme->questions()->max('position') + 1]);
        }

        return redirect()->to(route('admin.empowerment.edit', $programme).'#form')->with('status', 'Form saved.');
    }

    public function destroy(EmpowermentProgramme $programme, Images $images): RedirectResponse
    {
        abort_if($programme->applications()->exists(), 422, 'This programme has applications. Unpublish it instead, so they\'re kept.');
        $images->delete($programme->banner_path);
        $programme->delete();

        return redirect()->route('admin.empowerment.index')->with('status', 'Programme deleted.');
    }

    /**
     * Every application, with each answer in its own column.
     */
    public function export(Request $request, EmpowermentProgramme $programme): BinaryFileResponse
    {
        abort_unless(Xlsx::available(), 500, 'This server can\'t make Excel files: ask your host to turn on PHP\'s zip extension.');
        $questions = $programme->questions()->get();
        $apps = $programme->applications()->with('choices', 'track')->oldest()->get();
        $tracks = $programme->tracks()->withCount(['applicants', 'accepted'])->get();
        $sheets = [
            ['name' => 'Summary', 'title' => [config('church.name').': '.$programme->title, 'Made '.now()->format('j M Y, g:i A'), ''], 'pairs' => [
                ['Applications', $apps->count()],
                ...collect(EmpowermentApplication::STATUSES)->map(fn ($l, $k) => ['   '.$l, $apps->where('status', $k)->count()])->values()->all(),
                ['', null],
                ['Choices (picked by / accepted / places)', null],
                ...$tracks->map(fn ($t) => [$t->name, $t->applicants_count.' / '.$t->accepted_count.' / '.($t->slots ?? 'no limit')])->all(),
            ]],
            ['name' => 'Applications', 'columns' => [
                ['Applied', 'date'], ['Reference'], ['Name'], ['Phone'], ['Email'], ['Gender'], ['Date of birth', 'date'], ['Age', 'int'], ['Location'],
                ...array_map(fn ($n) => [($n === 1 ? 'First' : ($n === 2 ? 'Second' : 'Third')).' choice'], range(1, max(1, $programme->max_choices))),
                ['Status'], ['Accepted for'], ...$questions->map(fn ($q) => [$q->label])->all(), ['Note'],
            ], 'rows' => $apps->map(fn ($a) => [
                $a->created_at->toDateString(), $a->reference, $a->name, $a->phone, $a->email, ucfirst((string) $a->gender), $a->date_of_birth?->toDateString(), $a->age(), $a->location,
                ...array_map(fn ($i) => $a->choices[$i]->name ?? null, range(0, max(1, $programme->max_choices) - 1)),
                $a->statusLabel(), $a->track?->name, ...$questions->map(fn ($q) => $q->answerText($a->answers[$q->key()] ?? null))->all(), $a->admin_note,
            ])->all()],
        ];
        $path = Xlsx::write($sheets, $programme->title);

        return response()->download($path, Str::slug($programme->title).'-applications-'.now()->format('Y-m-d').'.xlsx')->deleteFileAfterSend();
    }

    private function filtered(EmpowermentProgramme $programme, ?string $status, ?int $track, string $q)
    {
        return $programme->applications()
            ->when($status, fn ($x) => $x->where('status', $status))
            ->when($track, fn ($x) => $x->whereHas('choices', fn ($c) => $c->whereKey($track)))
            ->when($q !== '', function ($x) use ($q) {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q).'%';
                $x->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('reference', 'like', $like));
            });
    }

    private function slug(string $title): string
    {
        $base = Str::slug($title) ?: 'empowerment';
        $slug = $base;
        for ($n = 2; EmpowermentProgramme::where('slug', $slug)->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }
}
