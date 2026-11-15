<?php

namespace App\Mail\Concerns;

use Illuminate\Mail\Mailables\Headers;

/**
 * Marks an email as a notification (a reminder, update or greeting), as opposed to something
 * people must get, like a receipt, ticket or password link. Notifications are only sent while
 * email notifications are on in Settings & roles.
 */
trait IsNotification
{
    public function headers(): Headers
    {
        return new Headers(text: ['X-Church-Notification' => '1']);
    }
}
