<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    /**
     * Create an announcement: publish now, schedule it, or keep it as a draft.
     */
    public function store(Request $request): RedirectResponse
    {
        $announcement = Announcement::create($this->validated($request));

        return redirect()->route('admin.content')->with('status', $this->message($announcement, 'saved'));
    }

    /**
     * Edit form for one announcement.
     */
    public function edit(Announcement $announcement): View
    {
        return view('admin.announcement-edit', ['announcement' => $announcement]);
    }

    /**
     * Save changes to an announcement.
     */
    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        $announcement->update($this->validated($request, $announcement));

        return redirect()->route('admin.content')->with('status', $this->message($announcement, 'updated'));
    }

    /**
     * Delete an announcement.
     */
    public function destroy(Announcement $announcement): RedirectResponse
    {
        $announcement->delete();

        return redirect()->route('admin.content')->with('status', "\"{$announcement->title}\" deleted.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Announcement $existing = null): array
    {
        $data = $request->validate([
            'tag' => ['required', 'string', 'max:40'],
            'tone' => ['required', Rule::in(array_keys(Announcement::TONES))],
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:500'],
            // A full web address or a link to a page on this site; nothing else (e.g. no javascript:).
            'url' => ['nullable', 'string', 'max:255', 'regex:#^(https?://|/)#i'],
            'when' => ['required', Rule::in(['now', 'schedule', 'draft'])],
            'publish_at' => ['nullable', 'required_if:when,schedule', 'date', 'after:now'],
        ], [
            'url.regex' => 'The link must start with https:// or / (a page on this site).',
            'publish_at.required_if' => 'Pick when it should go live.',
            'publish_at.after' => 'Pick a time in the future, or choose "Publish now".',
        ]);

        $data['published_at'] = match ($data['when']) {
            // Keep the original publish time when re-saving something already live.
            'now' => $existing?->status() === 'live' ? $existing->published_at : now(),
            'schedule' => Carbon::parse($data['publish_at'], config('app.timezone')),
            'draft' => null,
        };
        unset($data['when'], $data['publish_at']);

        return $data;
    }

    private function message(Announcement $announcement, string $verb): string
    {
        return match ($announcement->status()) {
            'live' => "\"{$announcement->title}\" is live on the home page.",
            'scheduled' => "\"{$announcement->title}\" will go live ".$announcement->published_at->format('D j M, g:i A').'.',
            default => "\"{$announcement->title}\" {$verb} as a draft.",
        };
    }
}
