<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\StaffInvited;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Giving people a staff role, changing it, or taking it away.
 */
class StaffController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in(array_keys(config('roles.roles')))],
        ]);

        $user = User::firstOrNew(['email' => Str::lower($data['email'])]);
        if ($user->isStaff()) {
            return back()->withErrors(['email' => "{$user->email} is already staff. Change their role below."])->withInput();
        }

        $isNew = ! $user->exists;
        $user->name = $user->name ?: $data['name'];
        if ($isNew) {
            $user->password = Str::password(32);
        }
        $user->role = $data['role'];
        $user->save();

        try {
            $url = $isNew ? route('password.reset', ['token' => Password::broker()->createToken($user), 'email' => $user->email]) : null;
            Mail::to($user->email)->send(new StaffInvited($user, $url));
        } catch (Throwable $e) {
            Log::error('Could not email staff invite', ['user' => $user->id, 'error' => $e->getMessage()]);
        }

        return back()->with('status', "{$user->name} added as ".$user->roleName().' and emailed a link to sign in.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->isStaff(), 404);
        $data = $request->validate(['role' => ['required', Rule::in(array_keys(config('roles.roles')))]]);

        if ($user->isAdmin() && $data['role'] !== 'admin' && $this->isLastAdmin($user)) {
            return back()->withErrors(['staff' => 'There must be at least one administrator.']);
        }

        $user->forceFill(['role' => $data['role']])->save();

        return back()->with('status', "{$user->name} is now ".$user->roleName().'.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->isStaff(), 404);
        if ($user->is($request->user())) {
            return back()->withErrors(['staff' => 'You can\'t remove your own access. Ask another administrator.']);
        }
        if ($user->isAdmin() && $this->isLastAdmin($user)) {
            return back()->withErrors(['staff' => 'There must be at least one administrator.']);
        }

        $user->forceFill(['role' => null])->save();

        return back()->with('status', "{$user->name} no longer has staff access.");
    }

    private function isLastAdmin(User $user): bool
    {
        return User::where('role', 'admin')->whereKeyNot($user->id)->doesntExist();
    }
}
