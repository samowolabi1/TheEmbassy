<?php

namespace App\Http\Controllers\Unit;

use App\Http\Controllers\Controller;
use App\Models\SocialPost;
use App\Models\Unit;
use App\Services\Social\MetaPublisher;
use App\Services\Social\PostPublisher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Writing a post once and sending it to the church's social accounts. Team members draft and
 * submit; the HOD (or the media staff) approves, schedules or publishes. Facebook and Instagram
 * are posted by the app; the share kit covers YouTube, X, TikTok and WhatsApp.
 */
class SocialController extends Controller
{
    public function index(Request $request, Unit $unit, MetaPublisher $meta): View
    {
        $tab = in_array($request->query('tab'), ['queue', 'published', 'drafts'], true) ? $request->query('tab') : 'queue';

        return view('unit.media.social', [
            'unit' => $unit,
            'tab' => $tab,
            'posts' => SocialPost::with('author', 'approver')
                ->when($tab === 'queue', fn ($q) => $q->whereIn('status', ['awaiting_approval', 'scheduled', 'publishing', 'failed', 'partly_published']))
                ->when($tab === 'published', fn ($q) => $q->where('status', 'published'))
                ->when($tab === 'drafts', fn ($q) => $q->where('status', 'draft'))
                ->latest()->paginate(15)->withQueryString(),
            'canLead' => Gate::allows('lead-media', $unit),
            'connected' => ['facebook' => $meta->configured('facebook'), 'instagram' => $meta->configured('instagram')],
        ]);
    }

    public function store(Request $request, Unit $unit): RedirectResponse
    {
        $data = $request->validate([
            'caption' => ['required', 'string', 'max:2200'],
            'media' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,mp4,mov', 'max:102400'],
            'platforms' => ['required', 'array', 'min:1'],
            'platforms.*' => [Rule::in(array_keys(SocialPost::PLATFORMS))],
            'publish_at' => ['nullable', 'date', 'after:now'],
            'intent' => ['required', Rule::in(['draft', 'submit', 'publish'])],
        ], ['platforms.required' => 'Choose at least one platform.', 'media.max' => 'Keep videos under 100 MB.']);

        if (in_array('instagram', $data['platforms'], true) && ! $request->hasFile('media')) {
            return back()->withInput()->withErrors(['media' => 'Instagram posts need a photo or video.']);
        }

        $post = new SocialPost(['caption' => $data['caption'], 'platforms' => array_values($data['platforms']), 'publish_at' => $data['publish_at'] ?? null]);
        if ($file = $request->file('media')) {
            $post->media_path = $file->store('social', 'public');
            $post->media_type = Str::startsWith($file->getMimeType(), 'video/') ? 'video' : 'image';
        }
        $post->created_by = $request->user()->id;
        $post->status = 'draft';
        $post->save();

        return $this->move($request, $unit, $post, $data['intent']);
    }

    public function show(Unit $unit, SocialPost $post): View
    {
        return view('unit.media.social-post', ['unit' => $unit, 'post' => $post->load('author', 'approver'), 'canLead' => Gate::allows('lead-media', $unit)]);
    }

    /**
     * Submit a draft for approval, or (leaders) approve: schedule it, or publish now.
     */
    public function act(Request $request, Unit $unit, SocialPost $post): RedirectResponse
    {
        $intent = $request->validate(['intent' => ['required', Rule::in(['submit', 'publish', 'retry', 'reject'])]])['intent'];

        return $this->move($request, $unit, $post, $intent);
    }

    public function destroy(Request $request, Unit $unit, SocialPost $post): RedirectResponse
    {
        abort_unless(in_array($post->status, ['draft', 'awaiting_approval', 'scheduled'], true), 422, 'Published posts are kept as a record.');
        abort_unless($post->created_by === $request->user()->id || Gate::allows('lead-media', $unit), 403);
        if ($post->media_path) {
            Storage::disk('public')->delete($post->media_path);
        }
        $post->delete();

        return redirect()->route('unit.social.index', $unit)->with('status', 'Post deleted.');
    }

    private function move(Request $request, Unit $unit, SocialPost $post, string $intent): RedirectResponse
    {
        $leads = Gate::allows('lead-media', $unit);

        if ($intent === 'draft') {
            return redirect()->route('unit.social.show', [$unit, $post])->with('status', 'Saved as a draft.');
        }
        // Team members submit; only leaders can publish.
        if ($intent === 'submit' || ! $leads) {
            abort_unless(in_array($post->status, ['draft'], true), 422);
            $post->forceFill(['status' => 'awaiting_approval'])->save();

            return redirect()->route('unit.social.show', [$unit, $post])->with('status', 'Sent to the HOD to approve.');
        }
        if ($intent === 'reject') {
            abort_unless($post->status === 'awaiting_approval', 422);
            $post->forceFill(['status' => 'draft'])->save();

            return back()->with('status', 'Sent back to drafts.');
        }

        abort_unless(in_array($post->status, ['draft', 'awaiting_approval', 'failed', 'partly_published'], true), 422, 'This post has already gone out.');
        if ($intent === 'retry') {
            // Try the platforms that failed again; ones already published are left alone.
            $post->results = collect($post->results ?? [])->reject(fn ($r) => ($r['status'] ?? null) === 'failed')->all();
        }
        $post->forceFill(['status' => 'scheduled', 'approved_by' => $request->user()->id])->save();

        if ($post->publish_at && $post->publish_at->isFuture()) {
            return redirect()->route('unit.social.show', [$unit, $post])->with('status', 'Approved. It goes out '.$post->publish_at->format('D j M, g:i A').'.');
        }

        app(PostPublisher::class)->publish($post);

        return redirect()->route('unit.social.show', [$unit, $post])->with('status', match ($post->fresh()->status) {
            'published' => 'Published.',
            'publishing' => 'Publishing: Instagram is still processing the video. It will post in a minute or two.',
            'partly_published' => 'Published to some platforms. See below for what went wrong.',
            default => 'Couldn\'t publish. See below for what went wrong.',
        });
    }
}
