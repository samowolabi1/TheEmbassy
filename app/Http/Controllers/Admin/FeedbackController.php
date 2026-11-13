<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\SuggestionAnswered;
use App\Models\PushSubscription;
use App\Models\Service;
use App\Models\ServiceFeedback;
use App\Models\Suggestion;
use App\Services\Messaging\WebPush;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * What members say: how each service went, and their suggestions, which staff answer.
 */
class FeedbackController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->query('tab') === 'suggestions' ? 'suggestions' : 'services';
        $status = array_key_exists($request->query('status'), Suggestion::STATUSES) ? $request->query('status') : null;

        $services = Service::whereHas('feedback')->withCount('feedback')->withAvg('feedback', 'rating')
            ->with('feedback:id,service_id,went_well,could_improve')
            ->orderByDesc('held_on')->orderByDesc('starts_at')->take(20)->get();

        return view('admin.feedback.index', [
            'tab' => $tab,
            'status' => $status,
            'services' => $services,
            'suggestions' => Suggestion::with('member', 'responder')
                ->when($status, fn ($q) => $q->where('status', $status), fn ($q) => $q->whereIn('status', ['new', 'reviewing', 'planned']))
                ->latest()->paginate(20)->withQueryString(),
            'counts' => [
                'new' => Suggestion::where('status', 'new')->count(),
                'open' => Suggestion::whereIn('status', ['new', 'reviewing', 'planned'])->count(),
            ],
        ]);
    }

    public function service(Service $service): View
    {
        $feedback = $service->feedback()->with('member')->latest()->get();

        return view('admin.feedback.service', [
            'service' => $service,
            'feedback' => $feedback,
            'tally' => self::tally($feedback),
            'ratings' => collect(range(5, 1))->mapWithKeys(fn ($n) => [$n => $feedback->where('rating', $n)->count()]),
        ]);
    }

    /**
     * Change where a suggestion is up to, and answer the member (they're emailed and notified).
     */
    public function respond(Request $request, Suggestion $suggestion, WebPush $push): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Suggestion::STATUSES))],
            'response' => ['nullable', 'string', 'max:2000'],
        ]);
        $answered = filled($data['response']) && $data['response'] !== $suggestion->response;
        $suggestion->forceFill([
            'status' => $data['status'],
            'response' => $data['response'] ?? $suggestion->response,
            ...($answered ? ['responded_by' => $request->user()->id, 'responded_at' => now()] : []),
        ])->save();

        if ($answered) {
            $member = $suggestion->member;
            if ($email = $member->user?->email ?? $member->email) {
                try {
                    Mail::to($email)->send(new SuggestionAnswered($suggestion));
                } catch (Throwable $e) {
                    Log::error('Could not send suggestion answer', ['suggestion' => $suggestion->id, 'error' => $e->getMessage()]);
                }
            }
            if ($member->user && $push->configured()) {
                $push->send(PushSubscription::where('user_id', $member->user_id)->get(), [
                    'title' => 'About your suggestion', 'body' => Str::limit($data['response'], 120), 'url' => route('account.feedback').'#suggest', 'tag' => 'suggestion-'.$suggestion->id,
                ]);
            }
        }

        return back()->with('status', $answered ? 'Saved, and the member has been told.' : 'Saved.');
    }

    /**
     * How often each part of the service was praised or flagged.
     *
     * @param  iterable<ServiceFeedback>  $feedback
     * @return array<string, array{well: int, improve: int}>
     */
    public static function tally(iterable $feedback): array
    {
        $tally = array_fill_keys(array_keys(ServiceFeedback::ASPECTS), ['well' => 0, 'improve' => 0]);
        foreach ($feedback as $f) {
            foreach ($f->went_well ?? [] as $a) {
                isset($tally[$a]) && $tally[$a]['well']++;
            }
            foreach ($f->could_improve ?? [] as $a) {
                isset($tally[$a]) && $tally[$a]['improve']++;
            }
        }

        return array_filter($tally, fn ($t) => $t['well'] + $t['improve'] > 0);
    }
}
