<?php

namespace App\Services\Pastoral;

use App\Mail\TestimonyUpdate;
use App\Models\Testimony;
use App\Models\User;
use App\Services\Tokens\Rewards;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Publishing members' testimonies once a pastor or the office has read them.
 */
class Testimonies
{
    public function __construct(private Rewards $rewards) {}

    /**
     * Publish it (with any light edits already saved). The member is told, and earns tokens.
     */
    public function publish(Testimony $testimony, User $by): void
    {
        if (! in_array($testimony->status, ['pending', 'declined'], true)) {
            throw ValidationException::withMessages(['status' => 'This testimony is '.mb_strtolower($testimony->statusLabel()).'.']);
        }
        $testimony->forceFill(['status' => 'published', 'published_at' => now(), 'reviewed_by' => $by->id, 'review_note' => null])->save();

        $user = $testimony->member->user;
        $tokens = $user ? $this->rewards->testimony($user) : 0;
        $where = $testimony->share_publicly ? ' It\'s now on the testimonies page for everyone to read.' : '';
        $this->tell($testimony, 'Your testimony is published', 'Thank you for sharing what God has done!'.$where.($tokens ? " You've earned {$tokens} tokens." : ''));
    }

    public function decline(Testimony $testimony, User $by, string $note): void
    {
        if ($testimony->status !== 'pending') {
            throw ValidationException::withMessages(['review_note' => 'Only testimonies waiting to be read can be declined.']);
        }
        $testimony->forceFill(['status' => 'declined', 'review_note' => $note, 'reviewed_by' => $by->id])->save();
        $this->tell($testimony, 'About your testimony', 'Thank you for sharing "'.$testimony->title.'". It hasn\'t been published: '.$note);
    }

    /**
     * Take a published testimony down (it goes back to waiting).
     */
    public function unpublish(Testimony $testimony): void
    {
        $testimony->forceFill(['status' => 'pending', 'published_at' => null])->save();
    }

    private function tell(Testimony $testimony, string $subject, string $message): void
    {
        $email = $testimony->member->user?->email ?? $testimony->member->email;
        if (! $email) {
            return;
        }
        try {
            Mail::to($email)->send(new TestimonyUpdate($testimony, $subject, $message));
        } catch (Throwable $e) {
            Log::error('Could not send testimony email', ['testimony' => $testimony->id, 'error' => $e->getMessage()]);
        }
    }
}
