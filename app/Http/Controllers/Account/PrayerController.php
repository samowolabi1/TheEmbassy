<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\PrayerRequest;
use App\Models\PrayerRequestPrayer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * A member's prayer requests, and the prayer wall where members pray for one another.
 */
class PrayerController extends Controller
{
    public function index(Request $request): View
    {
        $member = $this->member($request);

        return view('account.prayer', [
            'member' => $member,
            'mine' => PrayerRequest::with('replies.author.member')->where('member_id', $member->id)->latest()->take(30)->get(),
            'wallCount' => PrayerRequest::wall()->whereNull('answered_at')->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $member = $this->member($request);
        $data = $request->validate([
            'request' => ['required', 'string', 'max:5000'],
            'category' => ['required', Rule::in(array_keys(PrayerRequest::CATEGORIES))],
            'audience' => ['required', Rule::in(array_keys(PrayerRequest::AUDIENCES))],
        ]);

        $prayer = new PrayerRequest([
            'name' => $member->name,
            'contact' => $member->phone ?: $member->user?->email,
            'request' => $data['request'],
            'category' => $data['category'],
            'is_private' => $data['audience'] === 'pastors',
            'on_wall' => $data['audience'] === 'church',
            'anonymous' => $data['audience'] === 'church' && $request->boolean('anonymous'),
        ]);
        $prayer->member_id = $member->id;
        $prayer->save();

        return back()->with('status', $data['audience'] === 'church'
            ? 'Sent. The prayer team has it, and it\'s on the prayer wall so the church can pray with you.'
            : 'Sent. We\'re praying with you.');
    }

    /**
     * God answered: say what happened (optional), and perhaps share it as a testimony.
     */
    public function answered(Request $request, PrayerRequest $prayer): RedirectResponse
    {
        $this->own($request, $prayer);
        $note = $request->validate(['answer_note' => ['nullable', 'string', 'max:500']])['answer_note'] ?? null;
        $prayer->forceFill(['answered_at' => now(), 'answer_note' => $note])->save();

        return back()->with('status', 'Praise God! 🎉')->with('answered', $prayer->id);
    }

    public function destroy(Request $request, PrayerRequest $prayer): RedirectResponse
    {
        $this->own($request, $prayer);
        $prayer->delete();

        return back()->with('status', 'Prayer request removed.');
    }

    public function wall(Request $request): View
    {
        $this->member($request);
        $requests = PrayerRequest::wall()->with('member')->orderByRaw('answered_at is not null')->latest()->paginate(20);

        return view('account.prayer-wall', [
            'requests' => $requests,
            'prayed' => PrayerRequestPrayer::where('user_id', $request->user()->id)->whereIn('prayer_request_id', $requests->pluck('id'))->pluck('prayer_request_id')->all(),
        ]);
    }

    /**
     * "I prayed" — once each.
     */
    public function pray(Request $request, PrayerRequest $prayer): RedirectResponse
    {
        $this->member($request);
        abort_unless($prayer->on_wall && $prayer->member_id, 404);
        try {
            PrayerRequestPrayer::create(['prayer_request_id' => $prayer->id, 'user_id' => $request->user()->id]);
            $prayer->increment('prayed_count');
        } catch (UniqueConstraintViolationException) {
            // Already counted.
        }

        return redirect()->to(url()->previous().'#p'.$prayer->id);
    }

    private function own(Request $request, PrayerRequest $prayer): void
    {
        abort_unless($prayer->member_id && $prayer->member_id === $this->member($request)->id, 404);
    }

    private function member(Request $request): Member
    {
        $member = $request->user()->member;
        abort_unless($member && $member->status === Member::ACTIVE, 404);

        return $member;
    }
}
