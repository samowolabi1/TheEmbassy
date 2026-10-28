<?php

namespace App\Services\Units;

use App\Mail\DutyReminder;
use App\Models\Duty;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Emails each member on a rota two days before the service. Each duty is reminded once.
 */
class DutyReminders
{
    public function send(): int
    {
        $sent = 0;
        Duty::with('member.user', 'service', 'unit')->whereNull('reminded_at')
            ->whereHas('service', fn ($q) => $q->whereBetween('held_on', [today()->startOfDay(), today()->addDays(2)->endOfDay()]))
            ->each(function (Duty $duty) use (&$sent) {
                $email = $duty->member->email ?? $duty->member->user?->email;
                if ($email) {
                    try {
                        Mail::to($email)->send(new DutyReminder($duty));
                        $sent++;
                    } catch (Throwable $e) {
                        Log::error('Could not send duty reminder', ['duty' => $duty->id, 'error' => $e->getMessage()]);

                        return;
                    }
                }
                $duty->forceFill(['reminded_at' => now()])->save();
            });

        return $sent;
    }
}
