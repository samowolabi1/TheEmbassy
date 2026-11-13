<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\PrayerReply;
use App\Models\PrayerRequest;
use App\Models\PushSubscription;
use App\Services\Messaging\WebPush;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class PrayerRequestController extends Controller
{
    /**
     * The prayer request inbox, newest first, filterable by unread or private.
     */
    public function index(Request $request): View
    {
        $filter = in_array($request->query('show'), ['unread', 'private', 'members', 'wall'], true) ? $request->query('show') : 'all';

        $requests = PrayerRequest::query()->with('member', 'replies.author.member')
            ->when($filter === 'unread', fn ($q) => $q->unread())
            ->when($filter === 'private', fn ($q) => $q->where('is_private', true))
            ->when($filter === 'members', fn ($q) => $q->whereNotNull('member_id'))
            ->when($filter === 'wall', fn ($q) => $q->wall())
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.prayer-requests', [
            'requests' => $requests,
            'filter' => $filter,
            'counts' => [
                'all' => PrayerRequest::count(),
                'unread' => PrayerRequest::unread()->count(),
                'private' => PrayerRequest::where('is_private', true)->count(),
                'members' => PrayerRequest::whereNotNull('member_id')->count(),
                'wall' => PrayerRequest::wall()->count(),
            ],
        ]);
    }

    /**
     * Mark a request as read (prayed over), or back to unread.
     */
    public function update(Request $request, PrayerRequest $prayerRequest): RedirectResponse
    {
        $prayerRequest->forceFill(['read_at' => $request->boolean('read') ? now() : null])->save();

        return back();
    }

    /**
     * A word of encouragement to the member who asked; they get it by email, on their phone and
     * on their prayer page. Replying marks the request as prayed for.
     */
    public function reply(Request $request, PrayerRequest $prayerRequest, WebPush $push): RedirectResponse
    {
        abort_unless($prayerRequest->member_id, 422, 'Requests from the home page have no account to reply to. Use their contact details.');
        $body = $request->validate(['body' => ['required', 'string', 'max:2000']])['body'];
        $reply = $prayerRequest->replies()->make(['body' => $body]);
        $reply->user_id = $request->user()->id;
        $reply->save();
        $prayerRequest->forceFill(['read_at' => $prayerRequest->read_at ?? now()])->save();

        $member = $prayerRequest->member;
        if ($email = $member->user?->email ?? $member->email) {
            try {
                Mail::to($email)->send(new PrayerReply($prayerRequest, $reply));
            } catch (Throwable $e) {
                Log::error('Could not send prayer reply', ['prayer' => $prayerRequest->id, 'error' => $e->getMessage()]);
            }
        }
        if ($member->user && $push->configured()) {
            $push->send(PushSubscription::where('user_id', $member->user_id)->get(), [
                'title' => 'We\'re praying with you', 'body' => Str::limit($body, 120), 'url' => route('account.prayer'), 'tag' => 'prayer-'.$prayerRequest->id,
            ]);
        }

        return back()->with('status', 'Sent to '.$member->name.'.');
    }

    /**
     * Take a request off the members' prayer wall (the member's own page still has it).
     */
    public function offWall(PrayerRequest $prayerRequest): RedirectResponse
    {
        $prayerRequest->forceFill(['on_wall' => false])->save();

        return back()->with('status', 'Taken off the prayer wall.');
    }

    /**
     * Delete a request for good.
     */
    public function destroy(PrayerRequest $prayerRequest): RedirectResponse
    {
        $prayerRequest->delete();

        return back()->with('status', 'Prayer request deleted.');
    }
}
