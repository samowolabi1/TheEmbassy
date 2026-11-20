<?php

namespace App\Services\People;

use App\Mail\ReminderMail;
use App\Models\Member;
use App\Models\User;
use App\Services\Messaging\Reminders;
use App\Services\Messaging\WhatsApp;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Logins for members the church office adds. The office can send a link to set a password (by
 * email, and WhatsApp when it's on; it works for a week and can be sent again), or give a
 * temporary password to pass on, which the member must change the first time they sign in.
 * Members sign in with their email or phone number.
 */
class Logins
{
    public function __construct(private Reminders $reminders, private WhatsApp $whatsapp) {}

    /**
     * The member's login, made now if they don't have one.
     */
    public function userFor(Member $member): User
    {
        if ($member->user) {
            return $member->user;
        }
        $email = $member->email ? Str::lower(trim($member->email)) : null;
        if ($email && ($taken = User::where('email', $email)->first())) {
            if ($taken->member && ! $taken->member->is($member)) {
                throw ValidationException::withMessages(['login' => 'Another person on the register already signs in with '.$email.'.']);
            }
            $user = $taken; // an account made with the same email (e.g. a seller): link it
        } else {
            if (! $email && ! Member::internationalPhone($member->phone)) {
                throw ValidationException::withMessages(['login' => 'Add their email or phone number first: that\'s what they sign in with.']);
            }
            $user = User::create(['name' => $member->name, 'email' => $email, 'password' => Hash::make(Str::random(40))]);
        }
        $member->forceFill(['user_id' => $user->id])->save();
        $member->setRelation('user', $user);

        return $user;
    }

    /**
     * Send a link to set their password. Returns the ways it went, e.g. ['email', 'WhatsApp'].
     *
     * @return list<string>
     */
    public function invite(Member $member): array
    {
        $user = $this->userFor($member);
        if (! $user->email) {
            throw ValidationException::withMessages(['login' => 'A link needs an email address. Add their email, or give them a temporary password instead.']);
        }
        $token = Password::broker('invites')->createToken($user);
        $url = route('password.reset', ['token' => $token, 'email' => $user->email, 'invite' => 1]);
        $first = Member::firstNameOf($member->name);
        $title = 'Your '.config('church.short_name').' login';
        $body = "Hi {$first}, you're on the ".config('church.short_name').' app. Tap the button to choose your password, then sign in with your email ('
            .$user->email.')'.($member->phone ? ' or your phone number' : '').'. The link works for 7 days.';

        $went = [];
        if ($this->reminders->mail($user->email, new ReminderMail($title, $body, $url, 'Choose my password'))) {
            $went[] = 'email';
        }
        if ($this->whatsapp->ready() && ($number = Member::internationalPhone($member->phone)) && $member->notify_sms) {
            $this->whatsapp->send([$number], $title, $body, $url) && $went[] = 'WhatsApp';
        }
        $user->forceFill(['invited_at' => now()])->save();

        return $went;
    }

    /**
     * Give them a temporary password, which they must change when they first sign in.
     * Returns it, to show the office once.
     */
    public function temporaryPassword(Member $member): string
    {
        $user = $this->userFor($member);
        $words = ['Royal', 'Crown', 'Grace', 'Faith', 'Light', 'Praise', 'Glory', 'Shine', 'Hope', 'Peace'];
        $password = $words[random_int(0, count($words) - 1)].'-'.random_int(1000, 9999).'-'.Str::lower(Str::random(3));
        $user->forceFill(['password' => Hash::make($password), 'must_change_password' => true, 'invited_at' => now()])->save();

        return $password;
    }

    /**
     * Who to sign in, from what they typed: an email, or a phone number on the register.
     */
    public static function findForLogin(string $login): ?User
    {
        $login = trim($login);
        if (str_contains($login, '@')) {
            return User::where('email', Str::lower($login))->first();
        }
        $phone = Member::internationalPhone($login);
        if (! $phone) {
            return null;
        }
        $matches = Member::whereNotNull('user_id')->whereNotNull('phone')->with('user')->get(['id', 'phone', 'user_id'])
            ->filter(fn (Member $m) => Member::internationalPhone($m->phone) === $phone);

        // A number shared by two accounts can't say who's signing in: they use their email.
        return $matches->count() === 1 ? $matches->first()->user : null;
    }
}
