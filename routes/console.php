<?php

use App\Models\Member;
use App\Models\Setting;
use App\Models\User;
use App\Services\Care\FollowUps;
use App\Services\Celebrations\Birthdays;
use App\Services\Content\InlineImages;
use App\Services\Library\Lending;
use App\Services\Market\SubscriptionReminders;
use App\Services\Messaging\Broadcaster;
use App\Services\Messaging\Campaigns;
use App\Services\Messaging\Newsletter;
use App\Services\Messaging\Reminders;
use App\Services\Pastoral\Appointments;
use App\Services\Social\PostPublisher;
use App\Services\Tokens\Ledger;
use App\Services\Tokens\Rewards;
use App\Services\Tokens\TokenShop;
use App\Services\Units\DutyReminders;
use App\Services\YouTube\YouTubeBroadcasts;
use App\Support\ServerCheck;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;
use Minishlink\WebPush\VAPID;

use function Laravel\Prompts\password;

Artisan::command('admin:create {email} {--name= : Display name, e.g. "Pastor Ade"} {--super : Make them the pastor in charge (super admin): giving and the token currency}', function (string $email) {
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
    $user->role = $this->option('super') ? 'super' : 'admin';
    $user->save();

    $this->info("{$user->email} can now sign in at ".route('admin.login').' as '.$user->roleName().'.');
    if ($generated) {
        $this->line("Password: {$password}");
        $this->warn('Store it somewhere safe; it will not be shown again.');
    }
})->purpose('Create a church admin (or with --super, the pastor in charge), or give an existing account that access');

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

Artisan::command('messages:send', function (Newsletter $newsletter, Broadcaster $broadcaster, Campaigns $campaigns) {
    $emails = $newsletter->sendBatch();
    $busy = $broadcaster->sendBatch();
    $campaign = $campaigns->sendBatch();
    $this->info("Sent {$emails} weekly emails".($busy ? ' and worked on an announcement broadcast' : '').($campaign ? ' and on a Message centre message' : '').'.');
})->purpose('Send the next batch of the weekly email, announcements and Message centre messages');

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

Artisan::command('tokens:daily', function (Rewards $rewards, TokenShop $shop, Ledger $ledger) {
    // Birthday tokens for members whose birthday is today (29 February birthdays get theirs on the 28th in other years).
    $given = 0;
    $isLeapDay = today()->format('m-d') === '02-29' || (! today()->isLeapYear() && today()->format('m-d') === '02-28');
    Member::active()->whereNotNull('user_id')->whereNotNull('birthday')->with('user')->get()
        ->filter(fn (Member $m) => $m->birthday->format('m-d') === today()->format('m-d') || ($isLeapDay && $m->birthday->format('m-d') === '02-29'))
        ->each(function (Member $m) use ($rewards, &$given) {
            $given += $rewards->birthday($m->user) ? 1 : 0;
        });
    $expired = $shop->expireOld();
    $this->info("Birthday tokens for {$given} members; {$expired} token orders expired and refunded.");

    // The books must balance: every balance against its transfers, and the total against what was minted.
    $audit = $ledger->audit();
    if (! $audit['ok']) {
        Log::error('Token books do not balance', $audit);
        $this->error('Token books do not balance: '.implode(' ', $audit['problems']));
    }
})->purpose('Give birthday tokens, return tokens for orders never handed over, and check the token books balance');

// Run `php artisan schedule:run` every minute from cron (see the README).
Artisan::command('library:remind', function (Lending $lending) {
    $r = $lending->remind();
    $this->info("Library: {$r['due']} due-soon reminders, {$r['overdue']} overdue reminders, {$r['released']} uncollected reservations released.");
})->purpose('Remind members about library books due soon or overdue, and release reservations nobody collected');

Artisan::command('app:check', function (ServerCheck $check) {
    $results = $check->run();
    $this->table(['', 'Check', 'Result', 'How to fix'], collect($results)->map(fn ($c) => [
        ['ok' => 'OK', 'warn' => 'WARN', 'fail' => 'FIX'][$c['status']], $c['label'], $c['detail'], $c['status'] === 'ok' ? '' : (string) $c['fix'],
    ])->all());
    $failed = collect($results)->where('status', 'fail')->count();
    $failed ? $this->error("{$failed} problem(s) to fix.") : $this->info('Everything needed is in place.');

    return $failed ? 1 : 0;
})->purpose('Check the server has what the app needs (PHP, extensions, folders, cron, email, settings)');

Artisan::command('reminders:send', function (Reminders $reminders) {
    $r = $reminders->run();
    $this->info("Service reminders reached {$r['service']}, thank-you greetings {$r['greeting']}, feedback requests {$r['feedback']}.");
})->purpose('Send service reminders, thank-you greetings and feedback requests when they are due');

Artisan::command('appointments:remind', function (Appointments $appointments) {
    $this->info('Reminded '.$appointments->remind().' members of tomorrow\'s visits.');
})->purpose('Remind members of tomorrow\'s visits with a pastor, and send each pastor their day');

Artisan::command('birthdays:celebrate', function (Birthdays $birthdays) {
    $this->info('Celebrated '.$birthdays->run().' birthdays.');
})->purpose('Make today\'s birthday cards, post them in the community and send birthday wishes');

// Each job runs inside the scheduler's own PHP process rather than a new one, because shared
// hosts often switch off proc_open, which starting a new process needs.
$task = fn (string $command) => Schedule::call(fn () => Artisan::call($command))->name($command);

// Lets the server check show the cron job is running.
Schedule::call(fn () => ServerCheck::beat())->everyMinute()->name('heartbeat');
$task('youtube:sync')->everyMinute()->withoutOverlapping();
$task('social:publish')->everyMinute()->withoutOverlapping();
$task('units:remind')->dailyAt('09:00')->timezone('Africa/Lagos');
$task('care:scan')->dailyAt('07:00')->timezone('Africa/Lagos');
$task('market:remind')->dailyAt('08:00')->timezone('Africa/Lagos');
$task('messages:send')->everyMinute()->withoutOverlapping(30);
$task('newsletter:queue')->weeklyOn(1, (string) config('church.newsletter.send_at', '07:00'))->timezone('Africa/Lagos')
    ->when(fn () => (bool) config('church.newsletter.enabled'));
$task('tokens:daily')->dailyAt('06:30')->timezone('Africa/Lagos');
$task('birthdays:celebrate')->dailyAt('06:45')->timezone('Africa/Lagos');
$task('appointments:remind')->dailyAt('17:00')->timezone('Africa/Lagos');
$task('reminders:send')->everyFiveMinutes()->withoutOverlapping(10);
$task('library:remind')->dailyAt('10:00')->timezone('Africa/Lagos');
$task('blog:prune-images')->weeklyOn(0, '03:00')->timezone('Africa/Lagos');
