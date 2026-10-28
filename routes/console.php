<?php

use App\Models\User;
use App\Services\Care\FollowUps;
use App\Services\Market\SubscriptionReminders;
use App\Services\Units\DutyReminders;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;

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

// Run `php artisan schedule:run` every minute from cron (see the README).
Schedule::command('units:remind')->dailyAt('09:00')->timezone('Africa/Lagos');
Schedule::command('care:scan')->dailyAt('07:00')->timezone('Africa/Lagos');
Schedule::command('market:remind')->dailyAt('08:00')->timezone('Africa/Lagos');
