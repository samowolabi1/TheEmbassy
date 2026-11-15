<?php

namespace App\Services\People;

use App\Mail\ReminderMail;
use App\Models\Member;
use App\Models\User;
use App\Services\Messaging\Reminders;
use App\Services\Messaging\WhatsApp;
use Illuminate\Validation\ValidationException;

/**
 * Member ranks: member, then worker, then minister (pastors are above them). Everyone in a unit
 * is a worker, so a unit's head makes someone a worker by adding them to the unit (see Member).
 * A pastor makes a worker a minister, or a minister a worker again. Whoever does it answers
 * which of the required classes are done, and the member is told by email (and WhatsApp).
 */
class Ranks
{
    public function canChange(User $by, Member $member, string $to): bool
    {
        return $by->isPastor();
    }

    /**
     * Who can record a member's training: pastors, staff who manage members or units, and the
     * head or assistant of the member's unit.
     */
    public function canRecordTraining(User $by, Member $member): bool
    {
        return $by->isPastor() || $by->canManage('members') || $by->canManage('units') || (bool) $member->unit?->isLedBy($by->member);
    }

    /**
     * Make a worker a minister ('minister'), or a minister not one ('worker'), with the answers
     * to the minister's training questions.
     *
     * @param  array<string, mixed>  $answers
     */
    public function change(Member $member, string $to, User $by, array $answers = []): void
    {
        abort_unless(in_array($to, ['minister', 'worker'], true), 422);
        abort_unless($this->canChange($by, $member, $to), 403, 'Only a pastor can make someone a minister or change a minister.');
        if ($member->status !== Member::ACTIVE) {
            throw ValidationException::withMessages(['rank' => 'Only approved members can become ministers.']);
        }
        if ($to === 'minister' && $member->rank !== 'worker') {
            throw ValidationException::withMessages(['rank' => $member->rank === 'minister' ? 'They\'re already a minister.' : 'Ministers are chosen from the workers: they need to serve in a unit first.']);
        }
        if ($to === 'worker' && $member->rank !== 'minister') {
            return;
        }

        $from = $member->rank;
        $member->rank = $to === 'worker' && ! $member->unit_id ? 'member' : $to;
        if ($to === 'minister') {
            $member->answerTraining($answers, 'minister');
        }
        $member->rank_changed_by = $by->id;
        $member->save();
        $this->tell($member->fresh('unit', 'user'), $from);
    }

    /**
     * Let someone know they're now a worker or minister (or no longer a minister).
     */
    public function tell(Member $member, string $from): void
    {
        $first = Member::firstNameOf($member->name);
        $missing = $member->missingRequirements();
        $todo = $missing ? ' Still to do: '.implode(', ', $missing).'. Speak to your unit head about the next classes.' : '';
        $ask = $member->needsTrainingAnswers() ? ' Please open the app and tell us which of the classes you\'ve done.' : '';
        [$title, $body] = match (true) {
            $member->rank === 'worker' && $from === 'member' => ["You're now a worker 🎉", "Congratulations, {$first}! You're now a worker in ".($member->unit?->name ?? 'your unit').'. Workers need the baptismal class, believers\' class and workers\' in training class.'.($ask ?: $todo)],
            $member->rank === 'minister' => ["You're now a minister 🙌", "Congratulations, {$first}! The pastors have made you a minister of ".config('church.short_name').'. Thank you for your faithfulness; they\'ll be in touch about working together.'.($ask ?: $todo)],
            default => ['Your role at church has changed', "Hi {$first}, your role at ".config('church.short_name').' is now: '.$member->rankLine().'. Speak to your unit head or a pastor if you have any questions.'],
        };
        if ($email = $member->user?->email ?? $member->email) {
            app(Reminders::class)->mail($email, new ReminderMail($title, $body, route('account')));
        }
        app(WhatsApp::class)->toMember($member, $title, $body, route('account'));
    }
}
