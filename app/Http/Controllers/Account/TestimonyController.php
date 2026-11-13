<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Testimony;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * A member sharing a testimony, and seeing what became of the ones they shared.
 */
class TestimonyController extends Controller
{
    public function index(Request $request): View
    {
        $member = $this->member($request);

        return view('account.testimonies', [
            'member' => $member,
            'mine' => Testimony::where('member_id', $member->id)->latest()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $member = $this->member($request);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'category' => ['required', Rule::in(array_keys(Testimony::CATEGORIES))],
            'body' => ['required', 'string', 'min:40', 'max:5000'],
            'name_display' => ['required', Rule::in(array_keys(Testimony::NAME_DISPLAYS))],
        ], ['body.min' => 'Tell a little more of the story (at least a couple of sentences).']);

        $testimony = new Testimony([...$data, 'share_publicly' => $request->boolean('share_publicly'), 'share_in_church' => $request->boolean('share_in_church')]);
        $testimony->member_id = $member->id;
        $testimony->save();

        return back()->with('status', 'Thank you for sharing! A pastor will read it before it\'s published.');
    }

    public function withdraw(Request $request, Testimony $testimony): RedirectResponse
    {
        abort_unless($testimony->member_id === $this->member($request)->id, 404);
        $testimony->forceFill(['status' => 'withdrawn', 'published_at' => null])->save();

        return back()->with('status', 'Testimony withdrawn. It\'s no longer shown anywhere.');
    }

    private function member(Request $request): Member
    {
        $member = $request->user()->member;
        abort_unless($member && $member->status === Member::ACTIVE, 404);

        return $member;
    }
}
