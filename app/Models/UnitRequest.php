<?php

namespace App\Models;

use App\Mail\ReminderMail;
use App\Services\Messaging\Reminders;
use App\Services\Messaging\WhatsApp;
use App\Services\People\Ranks;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member asking to join a unit. The pastors or the church office approve it (and the member
 * joins the unit, so becomes a worker) or decline it, with a reason the member sees.
 */
#[Fillable(['member_id', 'unit_id', 'message', 'training'])]
class UnitRequest extends Model
{
    public const PENDING = 'pending';

    protected function casts(): array
    {
        return ['training' => 'array', 'decided_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function approve(User $by): void
    {
        $member = $this->member;
        $from = $member->rank;
        if ($this->training) {
            $member->answerTraining($this->training, $member->rank === 'minister' ? 'minister' : 'worker');
        }
        $member->joinUnit($this->unit);
        $this->forceFill(['status' => 'approved', 'decided_by' => $by->id, 'decided_at' => now()])->save();

        if ($member->status !== Member::ACTIVE) {
            return; // they hear about their unit with their membership
        }
        if ($from === 'member' && $member->rank === 'worker') {
            app(Ranks::class)->tell($member->fresh('unit', 'user'), $from);
        } else {
            $this->tell("You've joined {$this->unit->name}", 'Hi '.$member->firstName().", your request to join {$this->unit->name} has been approved. Welcome to the team!");
        }
    }

    public function decline(User $by, ?string $reason): void
    {
        $this->forceFill(['status' => 'declined', 'decided_by' => $by->id, 'decided_at' => now(), 'reason' => $reason])->save();
        $this->tell("About joining {$this->unit->name}", 'Hi '.$this->member->firstName().", your request to join {$this->unit->name} wasn't approved this time.".($reason ? " {$reason}" : '').' Speak to the church office if you have any questions.');
    }

    private function tell(string $title, string $body): void
    {
        if ($email = $this->member->user?->email ?? $this->member->email) {
            app(Reminders::class)->mail($email, new ReminderMail($title, $body, route('account')));
        }
        app(WhatsApp::class)->toMember($this->member, $title, $body, route('account'));
    }
}
