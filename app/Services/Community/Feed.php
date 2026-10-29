<?php

namespace App\Services\Community;

use App\Models\CommunityMessage;
use App\Models\CommunityRead;
use App\Models\CommunityRoom;
use App\Models\Member;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * What a member sees around the chat: rooms with unread counts, birthdays, the quiz.
 */
class Feed
{
    /**
     * Everything a message card needs, in one go.
     *
     * @return Builder<CommunityMessage>
     */
    public function messages(CommunityRoom $room): Builder
    {
        return $room->messages()->getQuery()->with([
            'user.member', 'replyTo.user.member', 'reactions.user.member', 'votes',
        ]);
    }

    /**
     * Rooms this person can see, each with its unread count and latest message.
     *
     * @return Collection<int, CommunityRoom>
     */
    public function rooms(User $user): Collection
    {
        $rooms = CommunityRoom::visibleTo($user)->get();
        $reads = CommunityRead::where('user_id', $user->id)->pluck('last_read_id', 'community_room_id');

        foreach ($rooms as $room) {
            $room->unread = CommunityMessage::where('community_room_id', $room->id)
                ->where('id', '>', $reads[$room->id] ?? 0)
                ->where('user_id', '!=', $user->id)
                ->whereNull('removed_at')
                ->count();
            $room->latest = CommunityMessage::with('user.member')->where('community_room_id', $room->id)->whereNull('removed_at')->latest('id')->first();
        }

        return $rooms;
    }

    public function unreadTotal(User $user): int
    {
        return $this->rooms($user)->sum('unread');
    }

    /**
     * Members whose birthday is in the next week, today first. Only the day is shown, never the year.
     *
     * @return Collection<int, array{member: Member, date: Carbon, today: bool}>
     */
    public function birthdays(): Collection
    {
        $days = collect(range(0, 6))->map(fn ($i) => today()->addDays($i));

        return Member::where('status', Member::ACTIVE)->whereNotNull('birthday')->get(['id', 'name', 'birthday', 'unit_id'])
            ->map(function (Member $m) use ($days) {
                $date = $days->first(fn ($d) => $d->month === $m->birthday->month && $d->day === $m->birthday->day);

                return $date ? ['member' => $m, 'date' => $date, 'today' => $date->isToday()] : null;
            })
            ->filter()
            ->sortBy(fn ($b) => $b['date']->timestamp)
            ->values();
    }

    public function quiz(): ?Quiz
    {
        return Quiz::open()->withCount('questions')->orderBy('closes_at')->first();
    }
}
