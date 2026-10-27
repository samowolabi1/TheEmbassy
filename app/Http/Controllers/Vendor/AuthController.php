<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Sign-in for vendors (members with an approved shop), plus setting and resetting their password.
 */
class AuthController extends Controller
{
    public function create(): View
    {
        return view('vendor.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);

        $key = 'vendor-login:'.Str::lower($credentials['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.']);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember')) || ! $request->user()->can('run-shop')) {
            Auth::logout();
            RateLimiter::hit($key);

            throw ValidationException::withMessages(['email' => 'That email and password don\'t match an approved shop.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        return redirect()->intended(route('vendor.products.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('vendor.login');
    }

    /**
     * Ask for a link to set a new password.
     */
    public function forgot(): View
    {
        return view('vendor.forgot');
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        // Only vendors get a link, but the reply is the same either way so emails can't be probed.
        $user = User::firstWhere('email', $request->input('email'));
        if ($user?->can('run-shop')) {
            Password::broker()->sendResetLink(['email' => $user->email]);
        }

        return back()->with('status', 'If that email belongs to a shop, we\'ve sent a link to set a new password.');
    }

    /**
     * The set-password page from the welcome or reset email.
     */
    public function editPassword(Request $request, string $token): View
    {
        return view('vendor.password', ['token' => $token, 'email' => (string) $request->query('email')]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(10)],
        ]);

        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'That link has expired or was already used. Ask for a new one.']);
        }

        return redirect()->route('vendor.login')->with('status', 'Password set. You can sign in now.');
    }
}
