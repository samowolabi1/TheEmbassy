<?php

namespace App\Http\Controllers\Community;

use App\Http\Controllers\Controller;
use App\Models\CommunityMessage;
use App\Models\CommunityRead;
use App\Models\CommunityRoom;
use App\Models\QuizAttempt;
use App\Services\Community\Feed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * The members' community: the room list and a room's chat.
 */
class RoomController extends Controller
{
    public const PAGE = 50;

    public function index(Request $request, Feed $feed): View
    {
        $user = $request->user();
        $quiz = $feed->quiz();

        return view('community.index', [
            'rooms' => $feed->rooms($user),
            'birthdays' => $feed->birthdays(),
            'quiz' => $quiz,
            'quizAttempt' => $quiz ? QuizAttempt::where('quiz_id', $quiz->id)->where('user_id', $user->id)->first() : null,
        ]);
    }

    public function show(Request $request, CommunityRoom $room, Feed $feed): View
    {
        $user = $request->user();
        abort_unless($room->isVisibleTo($user), 404);
        $request->validate(['before' => ['nullable', 'integer', 'min:1']]);

        $messages = $feed->messages($room)
            ->when($request->integer('before'), fn ($q, $before) => $q->where('id', '<', $before))
            ->orderByDesc('id')->take(self::PAGE + 1)->get();
        $hasEarlier = $messages->count() > self::PAGE;
        $messages = $messages->take(self::PAGE)->reverse()->values();

        if (! $request->filled('before') && $messages->isNotEmpty()) {
            CommunityRead::mark($user, $room, $messages->last()->id);
        }

        return view('community.room', [
            'room' => $room,
            'rooms' => $feed->rooms($user),
            'messages' => $messages,
            'hasEarlier' => $hasEarlier,
            'viewer' => $user,
            'reactions' => CommunityMessage::REACTIONS,
            'now' => now()->toIso8601String(),
        ]);
    }

    /**
     * New and changed messages since the last check (the page asks every few seconds).
     */
    public function updates(Request $request, CommunityRoom $room, Feed $feed): JsonResponse
    {
        $user = $request->user();
        abort_unless($room->isVisibleTo($user), 404);
        $request->validate(['since' => ['required', 'date'], 'after' => ['nullable', 'integer']]);

        // A small overlap so nothing saved in the same second is missed; the page replaces by id.
        $since = Carbon::parse($request->input('since'))->subSeconds(2);
        $now = now();
        $changed = $feed->messages($room)->where('updated_at', '>=', $since)->orderBy('id')->take(100)->get();

        $html = $changed->map(fn (CommunityMessage $m) => [
            'id' => $m->id,
            'html' => view('community.partials.message', ['message' => $m, 'viewer' => $user, 'reactions' => CommunityMessage::REACTIONS])->render(),
        ])->values();

        $newest = $changed->max('id');
        if ($newest && $newest > $request->integer('after') && $request->boolean('visible')) {
            CommunityRead::mark($user, $room, $newest);
        }

        return response()->json([
            'now' => $now->toIso8601String(),
            'messages' => $html,
            'can_post' => $room->fresh()->canPost($user),
        ]);
    }
}
