<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

/**
 * Changing your password. Someone signed in with a temporary password from the church office
 * comes here first and chooses their own.
 */
class PasswordController extends Controller
{
    public function edit(Request $request): View
    {
        return view('account.change-password', ['forced' => $request->user()->must_change_password]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', PasswordRule::min(10), 'different:current_password'],
        ], [
            'current_password.current_password' => $user->must_change_password ? 'That isn\'t the temporary password you were given.' : 'That isn\'t your current password.',
            'password.different' => 'Choose a password that\'s different from the old one.',
        ]);

        $forced = $user->must_change_password;
        $user->forceFill(['password' => $request->input('password'), 'must_change_password' => false, 'remember_token' => Str::random(60)])->save();
        // A new remember token signs other phones out of "keep me signed in"; this one stays signed in.
        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->to($forced ? AuthController::home($user) : route('account.password.edit'))
            ->with('status', $forced ? 'Welcome! Your password is set.' : 'Password changed.');
    }
}
