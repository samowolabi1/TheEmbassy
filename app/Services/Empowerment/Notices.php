<?php

namespace App\Services\Empowerment;

use App\Mail\ReminderMail;
use App\Models\EmpowermentApplication;
use App\Models\Member;
use App\Services\Messaging\Reminders;
use App\Services\Messaging\WhatsApp;
use Illuminate\Support\Facades\URL;

/**
 * Telling applicants: a confirmation when they apply, and news when their status changes.
 * By email, and WhatsApp too when it's switched on.
 */
class Notices
{
    public function __construct(private Reminders $reminders, private WhatsApp $whatsapp) {}

    public function statusUrl(EmpowermentApplication $application): string
    {
        return URL::signedRoute('empowerment.status', $application);
    }

    public function received(EmpowermentApplication $application): void
    {
        $p = $application->programme;
        $choices = $application->choices->pluck('name')->join(', ', ' and ');
        $this->send($application, 'We\'ve received your application',
            'Hi '.Member::firstNameOf($application->name).", thank you for applying to {$p->title}"
            .($choices ? " for {$choices}" : '').". Your reference is {$application->reference}. We'll let you know what happens next."
            .($p->thank_you ? ' '.$p->thank_you : ''));
    }

    public function statusChanged(EmpowermentApplication $application): void
    {
        $p = $application->programme;
        $first = Member::firstNameOf($application->name);
        $track = $application->track?->name;
        $body = match ($application->status) {
            'shortlisted' => "Good news, {$first}! You've been shortlisted for {$p->title}. We'll be in touch with the next step soon.",
            'accepted' => "Congratulations, {$first}! You've been accepted into {$p->title}".($track ? " for {$track}" : '').'. We\'ll send you the details of when and where to start.',
            'waitlisted' => "Hi {$first}, thank you for applying to {$p->title}. The places are full for now, so you're on the waiting list. We'll contact you if a place opens.",
            'declined' => "Hi {$first}, thank you for applying to {$p->title}. We couldn't offer you a place this time, but please apply again next time. We're praying with you.",
            'completed' => "Well done, {$first}! You've completed {$p->title}".($track ? " ({$track})" : '').'. We\'re proud of you and excited to see what you do next.',
            default => null,
        };
        if ($body) {
            $this->send($application, 'Your '.$p->title.' application', $body);
        }
    }

    private function send(EmpowermentApplication $application, string $title, string $body): void
    {
        $url = $this->statusUrl($application);
        if ($application->email) {
            $this->reminders->mail($application->email, new ReminderMail($title, $body, $url, 'See my application'));
        }
        if ($this->whatsapp->ready() && ($number = Member::internationalPhone($application->phone))) {
            $this->whatsapp->send([$number], $title, $body);
        }
    }
}
