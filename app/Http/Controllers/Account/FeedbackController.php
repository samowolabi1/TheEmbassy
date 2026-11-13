<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Service;
use App\Models\ServiceFeedback;
use App\Models\Suggestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * A member telling the church how services went, and sharing suggestions.
 */
class FeedbackController extends Controller
{
    public function index(Request $request): View
    {
        $member = $this->member($request);
        $services = self::recent();

        return view('account.feedback', [
            'member' => $member,
            'services' => $services,
            'attended' => $member->services()->whereIn('services.id', $services->modelKeys())->pluck('services.id')->all(),
            'given' => ServiceFeedback::where('member_id', $member->id)->whereIn('service_id', $services->modelKeys())->get()->keyBy('service_id'),
            'suggestions' => Suggestion::where('member_id', $member->id)->latest()->take(20)->get(),
            'focus' => (int) $request->query('service'),
        ]);
    }

    public function rate(Request $request, Service $service): RedirectResponse
    {
        $member = $this->member($request);
        abort_unless(self::recent()->contains('id', $service->id), 404);
        $aspects = array_keys(ServiceFeedback::ASPECTS);
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'went_well' => ['nullable', 'array'],
            'went_well.*' => [Rule::in($aspects)],
            'could_improve' => ['nullable', 'array'],
            'could_improve.*' => [Rule::in($aspects)],
            'comment' => ['nullable', 'string', 'max:2000'],
        ], ['rating.required' => 'Choose how the service was for you.']);

        $feedback = ServiceFeedback::where('service_id', $service->id)->where('member_id', $member->id)->first()
            ?? (new ServiceFeedback)->forceFill(['service_id' => $service->id, 'member_id' => $member->id]);
        $feedback->fill([
            'rating' => (int) $data['rating'],
            'went_well' => array_values($data['went_well'] ?? []),
            'could_improve' => array_values($data['could_improve'] ?? []),
            'comment' => $data['comment'] ?? null,
            'anonymous' => $request->boolean('anonymous'),
        ])->save();

        return redirect()->route('account.feedback')->with('status', 'Thank you! Your feedback on '.$service->label().' helps us serve you better.');
    }

    public function suggest(Request $request): RedirectResponse
    {
        $member = $this->member($request);
        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(Suggestion::CATEGORIES))],
            'subject' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:3000'],
        ]);
        $suggestion = new Suggestion([...$data, 'anonymous' => $request->boolean('anonymous')]);
        $suggestion->member_id = $member->id;
        $suggestion->save();

        return back()->with('status', 'Thank you! Your suggestion is with the church office and pastors. You\'ll see their answer here.');
    }

    /**
     * Services held in the last week, newest first, that feedback can be given on.
     *
     * @return Collection<int, Service>
     */
    public static function recent(): Collection
    {
        return Service::where('held_on', '>=', today()->subDays(ServiceFeedback::DAYS))
            ->where('held_on', '<=', today())
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->orderByDesc('held_on')->orderByDesc('starts_at')->get();
    }

    private function member(Request $request): Member
    {
        $member = $request->user()->member;
        abort_unless($member && $member->status === Member::ACTIVE, 404);

        return $member;
    }
}
