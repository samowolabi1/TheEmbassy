<?php

namespace App\Support;

/**
 * The church-wide switches for how members are notified: email (on to start), WhatsApp and app
 * notifications. Set in admin › Settings & roles › Notifications.
 */
class Notify
{
    public const CHANNELS = ['email' => 'Email', 'whatsapp' => 'WhatsApp', 'push' => 'App notification'];

    public static function on(string $channel): bool
    {
        return filter_var(config("church.notify.{$channel}", false), FILTER_VALIDATE_BOOLEAN);
    }
}
