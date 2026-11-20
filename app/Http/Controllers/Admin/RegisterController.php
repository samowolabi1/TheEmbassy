<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Fellowship;
use App\Models\FirstTimer;
use App\Models\Member;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Any staff member can register a first-timer's card or a new member, at the door or after a
 * service. New members added by staff who don't look after the register wait for the office to
 * approve them, like anyone who joins online; a unit chosen for them is a request.
 */
class RegisterController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.register', [
            'units' => Unit::orderBy('name')->get(['id', 'name']),
            'fellowships' => Fellowship::orderBy('name')->get(['id', 'name']),
            'approves' => $request->user()->canManage('members'),
            'today' => FirstTimer::where('visited_on', today())->count(),
        ]);
    }

    public function firstTimer(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('visitor', [...FirstTimerController::rules(), 'visited_on' => ['required', 'date', 'before_or_equal:today']]);
        $card = FirstTimer::create([...$data, 'wants_to_join' => $request->boolean('wants_to_join')]);
        $second = $card->fresh()->followUps()->where('reason', 'second_visit')->exists();

        return redirect()->route('admin.register.index')->with('status', $second
            ? "{$card->name} has visited before. Welcome back! The care team will follow up."
            : "Welcome card added for {$card->name}. The care team will call within ".(int) config('church.care.first_timer_days').' days.');
    }

    public function member(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validateWithBag('member', [
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('members', 'email')],
            'gender' => ['nullable', Rule::in(['female', 'male'])],
            'birthday' => ['nullable', 'date', 'before:today'],
            'address' => ['nullable', 'string', 'max:255'],
            'fellowship_id' => ['nullable', Rule::exists('fellowships', 'id')],
            'unit_id' => ['nullable', Rule::exists('units', 'id')],
        ], ['email.unique' => 'Someone on the register already has that email.']);

        $phone = Member::internationalPhone($data['phone']);
        if ($phone && Member::get(['phone'])->contains(fn ($m) => Member::internationalPhone($m->phone) === $phone)) {
            return back()->withErrors(['phone' => 'Someone on the register already has that phone number.'], 'member')->withInput();
        }

        $approves = $user->canManage('members');
        $member = new Member(collect($data)->except('unit_id')->all());
        $member->notes = 'Registered by '.$user->name.' on '.now()->format('j M Y').'.';
        $member->status = Member::PENDING;
        $member->save();
        if ($approves) {
            $member->approve(welcome: true);
        }
        if ($unit = ($data['unit_id'] ?? null) ? Unit::find($data['unit_id']) : null) {
            ($approves || $user->canManage('units')) ? $member->joinUnit($unit) : $member->unitRequests()->create(['unit_id' => $unit->id]);
        }

        if ($approves && in_array($request->input('login'), ['link', 'temporary'], true)) {
            return app(MemberController::class)->giveLogin($request, $member, "{$member->name} is on the register.".($unit ? " They're in {$unit->name}." : ''));
        }

        return redirect()->route('admin.register.index')->with('status', $approves
            ? "{$member->name} is on the register.".($unit ? " They're in {$unit->name}." : '')
            : "{$member->name} is on the register, waiting for the church office to approve them.");
    }
}
