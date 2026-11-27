<?php

namespace App\Services\Content;

use App\Models\User;
use App\Models\Verse;

/**
 * The verse a member sees once a day, the first time they open the app that day.
 * Members switch it off (or on) in My details.
 */
class DailyVerse
{
    /**
     * Today's verse for this person, or null if they've had it, switched it off, or aren't a member.
     * Asking marks it as shown, so it appears once a day.
     */
    public function take(?User $user): ?Verse
    {
        $member = $user?->member;
        if (! $member || ! $member->daily_verse || $member->daily_verse_on?->isToday()) {
            return null;
        }
        $verse = Verse::active()->inRandomOrder()->first();
        if ($verse) {
            $member->forceFill(['daily_verse_on' => today()])->save();
        }

        return $verse;
    }
}
