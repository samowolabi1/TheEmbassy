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
 * The words and photos on the About page, and the home page's slider and testimony.
 */
class PageContentController extends Controller
{
    private const PHOTO = ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:15360'];

    /** What a slide can show over its picture. */
    public const SLIDE_FEATURES = [
        'none' => 'Nothing',
        'service' => 'Next Sunday\'s time and place',
        'date' => 'A date badge (e.g. 14 NOV)',
        'series' => 'The current sermon series',
        'verse' => 'A scripture (in place of the picture)',
    ];

    public const SLIDE_SWATCHES = ['mid', 'night', 'navy', 'gold', 'ice', 'wash'];

    public const MAX_SLIDES = 8;

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
        $slides = $this->slides($request, $current, $images);
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

        return ['slides' => $slides, 'testimony' => [
            'quote' => $data['quote'],
            'name' => $data['name'],
            'initials' => mb_strtoupper($initials),
            'role' => $data['role'] ?? '',
            'photo' => $this->photo($request->file('photo'), $current['testimony']['photo'] ?? null, $request->boolean('remove_photo'), 'photo', $images),
        ]];
    }

    /**
     * The home page slider. Rows without a title are dropped (a sermon series slide can leave
     * it blank to use the series' name); photos follow the row they came from.
     *
     * @param  array<string, mixed>  $current
     * @return list<array<string, mixed>>
     */
    private function slides(Request $request, array $current, Images $images): array
    {
        // A form without the slider (e.g. only the testimony) leaves the slides as they are.
        if (! $request->has('slides')) {
            return $current['slides'] ?? [];
        }
        $link = ['nullable', 'string', 'max:300', 'regex:~^(/|#|https?://|@youtube$|@sermon$|@share$)~'];
        $data = $request->validate([
            'slides' => ['nullable', 'array', 'max:'.(self::MAX_SLIDES + 2)],
            'slides.*.from' => ['nullable', 'integer', 'min:0'],
            'slides.*.order' => ['nullable', 'integer', 'min:0', 'max:99'],
            'slides.*.kicker' => ['nullable', 'string', 'max:40'],
            'slides.*.kicker_style' => ['nullable', Rule::in(['outline', 'gold'])],
            'slides.*.title' => ['nullable', 'string', 'max:80'],
            'slides.*.accent' => ['nullable', 'string', 'max:60'],
            'slides.*.text' => ['nullable', 'string', 'max:220'],
            'slides.*.primary_label' => ['nullable', 'string', 'max:40'],
            'slides.*.primary_link' => [...$link, 'required_with:slides.*.primary_label'],
            'slides.*.secondary_label' => ['nullable', 'string', 'max:40'],
            'slides.*.secondary_link' => [...$link, 'required_with:slides.*.secondary_label'],
            'slides.*.photo' => self::PHOTO,
            'slides.*.remove_photo' => ['nullable', 'boolean'],
            'slides.*.photo_alt' => ['nullable', 'string', 'max:150'],
            'slides.*.swatch' => ['nullable', Rule::in(self::SLIDE_SWATCHES)],
            'slides.*.feature' => ['nullable', Rule::in(array_keys(self::SLIDE_FEATURES))],
            'slides.*.feature_text' => ['nullable', 'string', 'max:300'],
            'slides.*.feature_ref' => ['nullable', 'string', 'max:40'],
            'slides.*.show_until' => ['nullable', 'date'],
            'slides.*.hidden' => ['nullable', 'boolean'],
        ], [
            'slides.*.primary_link.regex' => 'Links start with / (a page on this site), # or https://, or are @youtube, @sermon or @share.',
            'slides.*.secondary_link.regex' => 'Links start with / (a page on this site), # or https://, or are @youtube, @sermon or @share.',
            'slides.*.primary_link.required_with' => 'Where should this button go?',
            'slides.*.secondary_link.required_with' => 'Where should this button go?',
        ]);

        $kept = [];
        $slides = [];
        foreach ($data['slides'] ?? [] as $i => $row) {
            $was = isset($row['from']) ? ($current['slides'][(int) $row['from']] ?? null) : null;
            $feature = $row['feature'] ?? 'none';
            if (trim($row['title'] ?? '') === '' && $feature !== 'series') {
                continue;
            }
            $photo = $this->photo($request->file("slides.{$i}.photo"), $was['photo'] ?? null, (bool) ($row['remove_photo'] ?? false), "slides.{$i}.photo", $images, 1800);
            $kept[] = $photo;
            $slides[] = [
                'order' => (int) ($row['order'] ?? $i),
                'kicker' => trim($row['kicker'] ?? ''),
                'kicker_style' => $row['kicker_style'] ?? 'outline',
                'title' => trim(str_replace("\r", '', $row['title'] ?? '')),
                'accent' => trim($row['accent'] ?? ''),
                'text' => trim($row['text'] ?? ''),
                'primary_label' => trim($row['primary_label'] ?? ''),
                'primary_link' => trim($row['primary_link'] ?? '') ?: null,
                'secondary_label' => trim($row['secondary_label'] ?? ''),
                'secondary_link' => trim($row['secondary_link'] ?? '') ?: null,
                'photo' => $photo,
                'photo_alt' => trim($row['photo_alt'] ?? ''),
                'swatch' => $row['swatch'] ?? 'mid',
                'feature' => $feature,
                'feature_text' => trim($row['feature_text'] ?? '') ?: null,
                'feature_ref' => trim($row['feature_ref'] ?? '') ?: null,
                'show_until' => $row['show_until'] ?? null,
                'hidden' => (bool) ($row['hidden'] ?? false),
            ];
        }
        if (count($slides) > self::MAX_SLIDES) {
            throw ValidationException::withMessages(['slides' => 'Up to '.self::MAX_SLIDES.' slides. Hide or clear one first.']);
        }
        if ($slides === []) {
            throw ValidationException::withMessages(['slides' => 'Keep at least one slide.']);
        }
        // Photos of slides that were removed.
        foreach ($current['slides'] ?? [] as $old) {
            if (($old['photo'] ?? null) && ! in_array($old['photo'], $kept, true)) {
                $images->delete($old['photo'], $this->thumbOf($old['photo']));
            }
        }

        return collect($slides)->sortBy('order')->values()->map(fn ($s) => collect($s)->except('order')->all())->all();
    }

    /**
     * The photo to keep: a new upload, the existing one, or none. Replaced photos are deleted.
     */
    private function photo(?UploadedFile $file, ?string $existing, bool $remove, string $field, Images $images, int $width = 1400): ?string
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
            return $images->store($file, 'pages', $width, 480)['path'];
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
