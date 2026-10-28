<?php

namespace App\Services\Welfare;

use App\Mail\WelfareDecision;
use App\Mail\WelfareNeedsAttention;
use App\Models\Expense;
use App\Models\Unit;
use App\Models\User;
use App\Models\WelfareCase;
use App\Models\WelfarePayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The welfare workflow: a request comes in, the welfare HOD looks into it and approves support
 * up to their limit (a pastor approves anything above it), support is given and recorded, and
 * the case is closed. Every step goes in the case log.
 */
class WelfareDesk
{
    public function open(WelfareCase $case, ?User $by, string $source): WelfareCase
    {
        $case->source = $source;
        $case->raised_by = $by?->id;
        $case->save();
        $this->log($case, $by, 'status', 'Case opened'.($source === 'member' ? ' by the member' : '').'.');

        // The welfare unit's leaders hear about every new request.
        $this->notify($this->leaders(), new WelfareNeedsAttention($case, 'A new welfare request'));

        return $case;
    }

    public function review(WelfareCase $case, User $by): void
    {
        $this->ensure($case, ['new'], 'This case is already being looked into.');
        $case->forceFill(['status' => 'in_review'])->save();
        $this->log($case, $by, 'status', 'Started looking into it.');
    }

    /**
     * Approve support. Money above the HOD's limit goes to a pastor instead, unless a pastor is approving.
     */
    public function approve(WelfareCase $case, User $by, int $amountKobo, ?string $note, bool $isPastor): void
    {
        $this->ensure($case, ['new', 'in_review', 'awaiting_pastor'], 'This case has already been decided.');

        if (WelfareCase::needsPastor($amountKobo) && ! $isPastor) {
            $case->forceFill(['status' => 'awaiting_pastor', 'amount_approved' => $amountKobo, 'escalated_at' => now(), 'decision_note' => $note])->save();
            $this->log($case, $by, 'status', 'Recommended ₦'.number_format($amountKobo / 100).' and sent to a pastor to approve (above the HOD\'s limit).');
            $this->notify($this->pastors(), new WelfareNeedsAttention($case, 'A welfare case needs a pastor\'s approval'));

            return;
        }

        $case->forceFill(['status' => 'approved', 'amount_approved' => $amountKobo, 'decided_by' => $by->id, 'decided_at' => now(), 'decision_note' => $note])->save();
        $this->log($case, $by, 'status', 'Approved'.($amountKobo ? ' ₦'.number_format($amountKobo / 100) : ' (support without money)').($note ? ': '.$note : '.'));
        $this->tellRequester($case);
    }

    public function decline(WelfareCase $case, User $by, string $reason): void
    {
        $this->ensure($case, ['new', 'in_review', 'awaiting_pastor'], 'This case has already been decided.');
        $case->forceFill(['status' => 'declined', 'decided_by' => $by->id, 'decided_at' => now(), 'decision_note' => $reason, 'closed_at' => now()])->save();
        $this->log($case, $by, 'status', 'Declined: '.$reason);
        $this->tellRequester($case);
    }

    /**
     * Record money given. It can't go over what was approved, and it's added to the church's
     * expenses under the case reference (not the person's name).
     */
    public function pay(WelfareCase $case, User $by, int $amountKobo, string $method, string $paidOn, ?string $reference): WelfarePayment
    {
        $this->ensure($case, ['approved', 'supported'], 'Approve the case before recording payments.');
        if ($case->paid() + $amountKobo > (int) $case->amount_approved) {
            throw ValidationException::withMessages(['amount' => 'That would be more than the ₦'.number_format($case->amount_approved / 100).' approved. Approve a higher amount first.']);
        }

        return DB::transaction(function () use ($case, $by, $amountKobo, $method, $paidOn, $reference) {
            $expense = new Expense([
                'title' => 'Welfare support, case '.$case->reference,
                'category' => 'Welfare support',
                'amount' => $amountKobo,
                'spent_on' => $paidOn,
                'description' => $case->categoryLabel().'. Approved by '.($case->decider?->name ?? 'the welfare team').'.',
            ]);
            $expense->forceFill(['status' => Expense::APPROVED, 'requested_by' => $by->id, 'decided_by' => $case->decided_by, 'decided_at' => $case->decided_at ?? now()])->save();

            $payment = $case->payments()->make(['amount' => $amountKobo, 'method' => $method, 'paid_on' => $paidOn, 'reference' => $reference]);
            $payment->forceFill(['recorded_by' => $by->id, 'expense_id' => $expense->id])->save();

            $case->forceFill(['status' => 'supported'])->save();
            $this->log($case, $by, 'support', '₦'.number_format($amountKobo / 100).' given ('.config("welfare.payment_methods.{$method}", $method).').');

            return $payment;
        });
    }

    public function close(WelfareCase $case, User $by, ?string $note): void
    {
        $this->ensure($case, ['approved', 'supported', 'new', 'in_review'], 'This case is already closed.');
        $case->forceFill(['status' => 'closed', 'closed_at' => now(), 'next_check_on' => null])->save();
        $this->log($case, $by, 'status', 'Closed'.($note ? ': '.$note : '.'));
    }

    public function reopen(WelfareCase $case, User $by): void
    {
        $this->ensure($case, ['closed', 'declined'], 'This case is still open.');
        $case->forceFill(['status' => 'in_review', 'closed_at' => null])->save();
        $this->log($case, $by, 'status', 'Reopened.');
    }

    public function log(WelfareCase $case, ?User $by, string $kind, string $body, ?string $on = null): void
    {
        $note = $case->notes()->make(['kind' => $kind, 'body' => $body, 'happened_on' => $on ?? today()->toDateString()]);
        $note->user_id = $by?->id;
        $note->save();
    }

    /**
     * @param  list<string>  $allowed
     */
    private function ensure(WelfareCase $case, array $allowed, string $message): void
    {
        if (! in_array($case->status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => $message]);
        }
    }

    private function tellRequester(WelfareCase $case): void
    {
        $email = $case->member?->email ?? $case->member?->user?->email;
        if ($case->source === 'member' && $email) {
            $this->notify([$email], new WelfareDecision($case));
        }
    }

    /**
     * @return list<string>
     */
    private function leaders(): array
    {
        $unit = Unit::ofKind('welfare')?->load('hod.user', 'assistant.user');

        return collect([$unit?->hod, $unit?->assistant])->filter()->map(fn ($m) => $m->email ?? $m->user?->email)->filter()->unique()->values()->all();
    }

    /**
     * @return list<string>
     */
    private function pastors(): array
    {
        return User::whereNotNull('role')->get()->filter(fn (User $u) => $u->canManage('welfare'))->pluck('email')->all();
    }

    /**
     * @param  list<string>  $emails
     */
    private function notify(array $emails, object $mailable): void
    {
        foreach ($emails as $email) {
            try {
                Mail::to($email)->send(clone $mailable);
            } catch (Throwable $e) {
                Log::error('Could not send welfare email', ['error' => $e->getMessage()]);
            }
        }
    }
}
