<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Fellowship;
use App\Models\Member;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Members join the church register, sign in, and reset their password here.
 * Staff and sellers can sign in here too; each lands on their own area.
 */
class AuthController extends Controller
{
    public function create(): View
    {
        return view('account.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);

        $key = 'login:'.Str::lower($credentials['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.']);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($key);

            throw ValidationException::withMessages(['email' => 'That email and password don\'t match an account.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        return redirect()->intended($this->home($request->user()));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    /**
     * The membership form. Signed-in people (e.g. sellers or staff) can join without a new password.
     */
    public function join(Request $request): View|RedirectResponse
    {
        if ($request->user()?->member) {
            return redirect()->route('account');
        }

        return view('account.join', [
            'units' => Unit::orderBy('name')->get(),
            'fellowships' => Fellowship::orderBy('name')->get(),
        ]);
    }

    public function storeJoin(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user?->member) {
            return redirect()->route('account');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => $user ? ['nullable'] : ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['required', 'string', 'max:40'],
            'gender' => ['nullable', Rule::in(['female', 'male'])],
            'birthday' => ['nullable', 'date', 'before:today'],
            'address' => ['nullable', 'string', 'max:255'],
            'unit_id' => ['nullable', Rule::exists('units', 'id')],
            'fellowship_id' => ['nullable', Rule::exists('fellowships', 'id')],
            'password' => $user ? ['nullable'] : ['required', 'confirmed', PasswordRule::min(10)],
            'agree' => ['accepted'],
        ], [
            'email.unique' => 'There is already an account with that email. Sign in instead.',
            'agree.accepted' => 'Please agree to how we use your details.',
        ]);

        DB::transaction(function () use (&$user, $data) {
            if (! $user) {
                $user = User::create(['name' => $data['name'], 'email' => Str::lower($data['email']), 'password' => $data['password']]);
            }

            // Someone the office already added to the register is linked, not added twice.
            $details = collect($data)->only(['name', 'phone', 'gender', 'birthday', 'address', 'unit_id', 'fellowship_id'])->filter(fn ($v) => filled($v))->all();
            $member = Member::whereNull('user_id')->where('email', $user->email)->first();
            $member ? $member->fill(array_diff_key($details, array_filter($member->only(array_keys($details))))) : $member = new Member($details);
            $member->email = $user->email;
            $member->user()->associate($user)->save();
        });

        if (! $request->user()) {
            Auth::login($user);
            $request->session()->regenerate();
        }

        return redirect()->route('account')->with('status', 'Welcome to the family! The church office will confirm your membership soon.');
    }

    public function forgot(): View
    {
        return view('account.forgot');
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        Password::broker()->sendResetLink(['email' => $request->input('email')]);

        // The same reply either way, so emails can't be probed.
        return back()->with('status', 'If that email has an account, we\'ve sent a link to set a new password.');
    }

    public function editPassword(Request $request, string $token): View
    {
        return view('account.password', ['token' => $token, 'email' => (string) $request->query('email')]);
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

        return redirect()->route('login')->with('status', 'Password set. You can sign in now.');
    }

    /**
     * Where someone goes after signing in.
     */
    public static function home(User $user): string
    {
        return match (true) {
            $user->member !== null => route('account'),
            $user->isStaff() => route('admin.dashboard'),
            $user->can('run-shop') => route('vendor.products.index'),
            default => route('account'),
        };
    }
}
