<?php

namespace App\Services\People;

use App\Mail\ReminderMail;
use App\Models\Member;
use App\Models\User;
use App\Services\Messaging\Reminders;
use App\Services\Messaging\WhatsApp;
use Illuminate\Validation\ValidationException;

/**
 * Member ranks. A unit's head (or assistant) makes a member of their unit a worker, and can
 * make them a member again; staff who look after units can do the same. Pastors make workers
 * ministers, and can change any rank. The member is told by email (and WhatsApp if that's on).
 */
class Ranks
{
    public function canChange(User $by, Member $member, string $to): bool
    {
        if ($by->isPastor()) {
            return true;
        }
        // Becoming or stopping being a minister is for pastors only.
        if ($to === 'minister' || $member->rank === 'minister') {
            return false;
        }

        return $member->unit?->isLedBy($by->member) || $by->canManage('units');
    }

    public function change(Member $member, string $to, User $by): void
    {
        abort_unless(array_key_exists($to, Member::RANKS), 422);
        abort_unless($this->canChange($by, $member, $to), 403, $to === 'minister' || $member->rank === 'minister'
            ? 'Only a pastor can make someone a minister or change a minister.'
            : 'Only the head of their unit (or a pastor) can change this.');
        if ($member->status !== Member::ACTIVE) {
            throw ValidationException::withMessages(['rank' => 'Only approved members can become workers or ministers.']);
        }
        if ($to === 'worker' && ! $member->unit_id) {
            throw ValidationException::withMessages(['rank' => 'Add them to a unit first: workers serve in a unit.']);
        }
        if ($to === 'minister' && $member->rank !== 'worker' && $member->rank !== 'minister') {
            throw ValidationException::withMessages(['rank' => 'Make them a worker first: ministers are chosen from the workers.']);
        }
        if ($member->rank === $to) {
            return;
        }

        $from = $member->rank;
        $member->forceFill(['rank' => $to, 'rank_changed_at' => now(), 'rank_changed_by' => $by->id])->save();
        $this->tell($member->fresh('unit', 'user'), $from, $to);
    }

    private function tell(Member $member, string $from, string $to): void
    {
        $first = Member::firstNameOf($member->name);
        [$title, $body] = match (true) {
            $to === 'worker' && $from === 'member' => ["You're now a worker 🎉", "Congratulations, {$first}! You're now a worker in ".($member->unit?->name ?? 'your unit').'. Thank you for serving. Your unit head will be in touch about what\'s next.'],
            $to === 'minister' => ["You're now a minister 🙌", "Congratulations, {$first}! The pastors have made you a minister of ".config('church.short_name').'. Thank you for your faithfulness; they\'ll be in touch about working together.'],
            default => ['Your role at church has changed', "Hi {$first}, your role at ".config('church.short_name').' is now: '.$member->rankLine().'. Speak to your unit head or a pastor if you have any questions.'],
        };
        if ($email = $member->user?->email ?? $member->email) {
            app(Reminders::class)->mail($email, new ReminderMail($title, $body, route('account')));
        }
        app(WhatsApp::class)->toMember($member, $title, $body, route('account'));
    }
}
