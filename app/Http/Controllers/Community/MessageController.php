<?php

namespace App\Http\Controllers\Community;

use App\Http\Controllers\Controller;
use App\Models\CommunityMessage;
use App\Models\CommunityReaction;
use App\Models\CommunityReport;
use App\Models\CommunityRoom;
use App\Models\CommunityVote;
use App\Models\PushSubscription;
use App\Services\Content\Images;
use App\Services\Messaging\WebPush;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Posting, editing and removing messages, reactions, poll votes and reports.
 * Every action answers with JSON for the chat page, or a redirect without JavaScript.
 */
class MessageController extends Controller
{
    public function store(Request $request, CommunityRoom $room, Images $images, WebPush $push): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        abort_unless($room->isVisibleTo($user), 404);
        if (! $room->canPost($user)) {
            throw ValidationException::withMessages(['body' => $user->isMutedInCommunity()
                ? 'You can\'t post until '.$user->community_muted_until->format('j M, g:i A').'.'
                : 'Only moderators can post in this room.']);
        }

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:15360'],
            'reply_to_id' => ['nullable', 'integer', Rule::exists('community_messages', 'id')->where('community_room_id', $room->id)],
            'poll_question' => ['nullable', 'string', 'max:200'],
            'poll_options' => ['nullable', 'array', 'max:6'],
            'poll_options.*' => ['nullable', 'string', 'max:80'],
            'poll_hours' => ['nullable', 'integer', Rule::in([1, 6, 24, 72, 168])],
        ]);

        $options = collect($data['poll_options'] ?? [])->map(fn ($o) => trim((string) $o))->filter()->unique()->values();
        $hasPoll = filled($data['poll_question'] ?? null);
        if ($hasPoll && $options->count() < 2) {
            throw ValidationException::withMessages(['poll_options' => 'Give the poll at least two different answers.']);
        }
        if (! $hasPoll && blank(trim((string) ($data['body'] ?? ''))) && ! $request->hasFile('image')) {
            throw ValidationException::withMessages(['body' => 'Write a message, add a photo or start a poll.']);
        }

        $message = new CommunityMessage([
            'community_room_id' => $room->id,
            'user_id' => $user->id,
            'reply_to_id' => $data['reply_to_id'] ?? null,
            'body' => filled(trim((string) ($data['body'] ?? ''))) ? trim($data['body']) : null,
            'poll' => $hasPoll ? [
                'question' => trim($data['poll_question']),
                'options' => $options->all(),
                'closes_at' => now()->addHours((int) ($data['poll_hours'] ?? 24))->toIso8601String(),
            ] : null,
        ]);

        if ($request->hasFile('image')) {
            try {
                $saved = $images->store($request->file('image'), 'community', 1600, 480);
            } catch (RuntimeException $e) {
                throw ValidationException::withMessages(['image' => $e->getMessage()]);
            }
            $message->image_path = $saved['path'];
            $message->image_thumb_path = $saved['thumb_path'];
        }
        $message->save();
        $room->touch();

        $this->notifyReply($message, $push);

        return $this->respond($request, $message, 201);
    }

    public function update(Request $request, CommunityMessage $message): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        abort_unless($message->room->isVisibleTo($user) && $message->canEdit($user), 403);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        if (blank(trim($data['body'])) && ! $message->image_path) {
            throw ValidationException::withMessages(['body' => 'Write something, or remove the message instead.']);
        }
        $message->update(['body' => trim($data['body']), 'edited_at' => now()]);

        return $this->respond($request, $message);
    }

    public function destroy(Request $request, CommunityMessage $message, Images $images): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        abort_unless($message->room->isVisibleTo($user) && $message->canRemove($user), 403);

        $images->delete($message->image_path, $message->image_thumb_path);
        $message->update([
            'body' => null, 'image_path' => null, 'image_thumb_path' => null, 'poll' => null,
            'removed_at' => now(), 'removed_by' => $user->id,
        ]);
        $message->reactions()->delete();
        $message->votes()->delete();
        // Someone else's message removed by a moderator closes any reports on it.
        $message->room->touch();
        CommunityReport::where('community_message_id', $message->id)->whereNull('resolved_at')
            ->update(['resolved_at' => now(), 'resolved_by' => $user->id, 'outcome' => 'removed']);

        return $this->respond($request, $message);
    }

    /**
     * Add a reaction, or take it away if it's already there.
     */
    public function react(Request $request, CommunityMessage $message): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        abort_unless($message->room->isVisibleTo($user) && ! $message->isRemoved(), 404);
        $data = $request->validate(['emoji' => ['required', Rule::in(CommunityMessage::REACTIONS)]]);
        abort_if($user->isMutedInCommunity(), 403);

        DB::transaction(function () use ($message, $user, $data) {
            $existing = CommunityReaction::where(['community_message_id' => $message->id, 'user_id' => $user->id, 'emoji' => $data['emoji']])->first();
            $existing ? $existing->delete() : CommunityReaction::create(['community_message_id' => $message->id, 'user_id' => $user->id, 'emoji' => $data['emoji']]);
            $message->touch(); // so other people's pages pick up the change
        });

        return $this->respond($request, $message);
    }

    public function vote(Request $request, CommunityMessage $message): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        abort_unless($message->room->isVisibleTo($user) && $message->poll && ! $message->isRemoved(), 404);
        $data = $request->validate(['option' => ['required', 'integer', 'min:0', 'max:'.(count($message->poll['options']) - 1)]]);
        if ($message->pollClosed()) {
            throw ValidationException::withMessages(['option' => 'This poll has closed.']);
        }

        // Changing your vote replaces it.
        CommunityVote::updateOrCreate(['community_message_id' => $message->id, 'user_id' => $user->id], ['option' => $data['option']]);
        $message->touch();

        return $this->respond($request, $message);
    }

    public function report(Request $request, CommunityMessage $message): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        abort_unless($message->room->isVisibleTo($user) && ! $message->isRemoved() && $message->user_id !== $user->id, 404);
        $data = $request->validate(['reason' => ['required', 'string', 'max:300']]);

        CommunityReport::firstOrCreate(['community_message_id' => $message->id, 'user_id' => $user->id], ['reason' => $data['reason']]);

        if ($request->expectsJson()) {
            return response()->json(['reported' => true]);
        }

        return back()->with('status', 'Thanks. A moderator will look at it.');
    }

    /**
     * The updated message card for the chat page, or back to the room.
     */
    private function respond(Request $request, CommunityMessage $message, int $status = 200): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            $message = CommunityMessage::with(['user.member', 'replyTo.user.member', 'reactions.user.member', 'votes'])->find($message->id);

            return response()->json([
                'id' => $message->id,
                'html' => view('community.partials.message', ['message' => $message, 'viewer' => $request->user(), 'reactions' => CommunityMessage::REACTIONS])->render(),
            ], $status);
        }

        return redirect()->to(route('community.room', $message->room).'#m'.$message->id);
    }

    /**
     * Let the writer of the message being replied to know, on their phone, after the response is sent.
     */
    private function notifyReply(CommunityMessage $message, WebPush $push): void
    {
        $original = $message->replyTo;
        if (! $original || $original->user_id === $message->user_id || $original->isRemoved() || ! $push->configured()) {
            return;
        }
        $room = $message->room;
        $author = $message->user;

        defer(function () use ($original, $room, $message, $author, $push) {
            // Only if they can still see the room.
            if (! $room->isVisibleTo($original->user)) {
                return;
            }
            $subscriptions = PushSubscription::where('user_id', $original->user_id)->get();
            $push->send($subscriptions, [
                'title' => $author->communityName().' replied in '.$room->name,
                'body' => Str::limit($message->body ?? ($message->poll ? 'Started a poll' : 'Sent a photo'), 120),
                'url' => route('community.room', $room).'#m'.$message->id,
                'tag' => 'community-'.$room->id,
            ]);
        });
    }
}
