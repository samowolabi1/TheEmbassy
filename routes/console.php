<?php

use App\Models\Setting;
use App\Models\User;
use App\Services\Care\FollowUps;
use App\Services\Content\InlineImages;
use App\Services\Market\SubscriptionReminders;
use App\Services\Messaging\Broadcaster;
use App\Services\Messaging\Newsletter;
use App\Services\Social\PostPublisher;
use App\Services\Units\DutyReminders;
use App\Services\YouTube\YouTubeBroadcasts;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;
use Minishlink\WebPush\VAPID;

use function Laravel\Prompts\password;

Artisan::command('admin:create {email} {--name= : Display name, e.g. "Pastor Ade"}', function (string $email) {
    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error('That is not a valid email address.');

        return 1;
    }

    $user = User::firstWhere('email', $email);

    $password = password(
        label: $user ? 'New password (leave empty to keep the current one)' : 'Password (leave empty to generate one)',
        hint: 'At least 12 characters.',
        validate: fn (string $value) => $value === '' || strlen($value) >= 12 ? null : 'Use at least 12 characters.',
    );
    $generated = $password === '' && ! $user;
    if ($generated) {
        $password = Str::password(16);
    }

    $user ??= new User(['email' => $email]);
    $user->name = $this->option('name') ?? $user->name ?? Str::before($email, '@');
    if ($password !== '') {
        $user->password = $password;
    }
    $user->role = 'admin';
    $user->save();

    $this->info("{$user->email} can now sign in at ".route('admin.login'));
    if ($generated) {
        $this->line("Password: {$password}");
        $this->warn('Store it somewhere safe; it will not be shown again.');
    }
})->purpose('Create a church admin, or give an existing account admin access');

Artisan::command('admin:revoke {email}', function (string $email) {
    $user = User::firstWhere('email', $email);
    if (! $user) {
        $this->error("No account found for {$email}.");

        return 1;
    }

    $user->forceFill(['role' => null])->save();
    $this->info("{$email} no longer has staff access.");
})->purpose('Remove staff access from an account');

Artisan::command('market:remind', function (SubscriptionReminders $reminders) {
    $sent = $reminders->send();
    $this->info("Sent {$sent['ending']} renewal reminders and {$sent['lapsed']} lapsed notices.");
})->purpose('Email sellers whose market subscription is ending or has just ended');

Artisan::command('care:scan', function (FollowUps $followUps) {
    $result = $followUps->scanAbsences();
    $this->info("Raised {$result['raised']} follow-ups for members not seen lately; closed {$result['closed']} for members who are back.");
})->purpose('Raise follow-ups for members who have missed several Sundays, and close them when they return');

Artisan::command('units:remind', function (DutyReminders $reminders) {
    $this->info('Sent '.$reminders->send().' rota reminders.');
})->purpose('Email members serving at a service in the next two days');

Artisan::command('social:publish', function (PostPublisher $publisher) {
    $this->info('Worked on '.$publisher->publishDue().' social posts.');
})->purpose('Publish approved social posts that are due, and finish Instagram videos that were processing');

Artisan::command('youtube:sync', function (YouTubeBroadcasts $broadcasts) {
    $r = $broadcasts->run();
    $this->info("Created {$r['created']} YouTube broadcasts, checked {$r['synced']}, {$r['errors']} problems.");
})->purpose('Create YouTube broadcasts for coming services and keep the Watch page in step with them');

Artisan::command('newsletter:queue', function (Newsletter $newsletter) {
    $this->info($newsletter->queue() ? 'This week\'s email is queued; messages:send sends it.' : 'This week\'s email has already gone out.');
})->purpose('Queue this week\'s email to subscribers');

Artisan::command('messages:send', function (Newsletter $newsletter, Broadcaster $broadcaster) {
    $emails = $newsletter->sendBatch();
    $busy = $broadcaster->sendBatch();
    $this->info("Sent {$emails} weekly emails".($busy ? ' and worked on an announcement broadcast.' : '.'));
})->purpose('Send the next batch of the weekly email and of announcement texts and notifications');

Artisan::command('push:keys {--force : Replace keys that already exist (everyone has to allow notifications again)}', function () {
    if (config('services.webpush.public_key') && ! $this->option('force')) {
        $this->info('App notification keys already exist. Use --force to replace them.');

        return;
    }
    $keys = VAPID::createVapidKeys();
    Setting::putMany([
        'services.webpush.public_key' => $keys['publicKey'],
        'services.webpush.private_key' => Crypt::encryptString($keys['privateKey']),
    ]);
    $this->info('Created and saved the keys for app notifications.');
})->purpose('Create the keys app notifications (web push) are signed with');

Artisan::command('blog:prune-images', function (InlineImages $images) {
    $this->info('Removed '.$images->prune().' unused article pictures.');
})->purpose('Delete pictures uploaded into articles that no article uses any more');

// Run `php artisan schedule:run` every minute from cron (see the README).
Schedule::command('youtube:sync')->everyMinute()->withoutOverlapping();
Schedule::command('social:publish')->everyMinute()->withoutOverlapping();
Schedule::command('units:remind')->dailyAt('09:00')->timezone('Africa/Lagos');
Schedule::command('care:scan')->dailyAt('07:00')->timezone('Africa/Lagos');
Schedule::command('market:remind')->dailyAt('08:00')->timezone('Africa/Lagos');
Schedule::command('messages:send')->everyMinute()->withoutOverlapping(30);
Schedule::command('newsletter:queue')->weeklyOn(1, (string) config('church.newsletter.send_at', '07:00'))->timezone('Africa/Lagos')
    ->when(fn () => (bool) config('church.newsletter.enabled'));
Schedule::command('blog:prune-images')->weeklyOn(0, '03:00')->timezone('Africa/Lagos');
