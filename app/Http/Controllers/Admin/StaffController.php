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

        $this->guardSuper($request, $data['role']);
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
        // Making someone the pastor in charge, or changing one, is for the pastor in charge alone.
        $this->guardSuper($request, $data['role'], $user);

        if ($user->isSuperAdmin() && $data['role'] !== 'super' && $this->isLastSuper($user)) {
            return back()->withErrors(['staff' => 'There must be at least one pastor in charge (super admin).']);
        }
        if ($user->isAdmin() && ! in_array($data['role'], ['admin', 'super'], true) && $this->isLastAdmin($user)) {
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
        $this->guardSuper($request, null, $user);
        if ($user->isSuperAdmin() && $this->isLastSuper($user)) {
            return back()->withErrors(['staff' => 'There must be at least one pastor in charge (super admin).']);
        }
        if ($user->isAdmin() && $this->isLastAdmin($user)) {
            return back()->withErrors(['staff' => 'There must be at least one administrator.']);
        }

        $user->forceFill(['role' => null])->save();

        return back()->with('status', "{$user->name} no longer has staff access.");
    }

    private function isLastAdmin(User $user): bool
    {
        return User::whereIn('role', ['admin', 'super'])->whereKeyNot($user->id)->doesntExist();
    }

    private function isLastSuper(User $user): bool
    {
        return User::where('role', 'super')->whereKeyNot($user->id)->doesntExist();
    }

    private function guardSuper(Request $request, ?string $role, ?User $user = null): void
    {
        if (($role === 'super' || $user?->isSuperAdmin()) && ! $request->user()->isSuperAdmin()) {
            abort(403, 'Only the pastor in charge can make or change a pastor in charge.');
        }
    }
}
