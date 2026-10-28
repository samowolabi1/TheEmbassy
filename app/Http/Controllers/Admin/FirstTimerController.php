<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FirstTimer;
use App\Models\Member;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Visitors' first-timer cards, and calling each one back.
 */
class FirstTimerController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->query('tab') === 'done' ? 'done' : 'todo';

        return view('admin.first-timers', [
            'tab' => $tab,
            'counts' => ['todo' => FirstTimer::whereNull('followed_up_at')->count(), 'done' => FirstTimer::whereNotNull('followed_up_at')->count()],
            'thisMonth' => FirstTimer::where('visited_on', '>=', now()->startOfMonth()->startOfDay())->count(),
            'firstTimers' => FirstTimer::with('follower', 'member')
                ->when($tab === 'todo', fn ($q) => $q->whereNull('followed_up_at')->oldest('visited_on'))
                ->when($tab === 'done', fn ($q) => $q->whereNotNull('followed_up_at')->latest('followed_up_at'))
                ->paginate(20)->withQueryString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        FirstTimer::create($this->validated($request));

        return back()->with('status', 'Card added.');
    }

    /**
     * Mark them as called, with a note of how it went.
     */
    public function followUp(Request $request, FirstTimer $firstTimer): RedirectResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:255']]);
        $firstTimer->forceFill(['followed_up_at' => now(), 'followed_up_by' => $request->user()->id, 'follow_up_note' => $data['note'] ?? null])->save();

        return back()->with('status', "{$firstTimer->name} marked as followed up.");
    }

    /**
     * Put them on the register, waiting for approval like anyone who joins.
     */
    public function addToRegister(FirstTimer $firstTimer): RedirectResponse
    {
        if (! $firstTimer->member_id) {
            $member = Member::create([
                'name' => $firstTimer->name,
                'phone' => $firstTimer->phone,
                'email' => $firstTimer->email,
                'notes' => trim('First visit '.$firstTimer->visited_on->format('j M Y').($firstTimer->invited_by ? ', invited by '.$firstTimer->invited_by : '').'. '.$firstTimer->notes),
            ]);
            $firstTimer->forceFill(['member_id' => $member->id])->save();
        }

        return redirect()->route('admin.members.index', ['tab' => 'pending'])->with('status', "{$firstTimer->name} added to the register, waiting for approval.");
    }

    public function destroy(FirstTimer $firstTimer): RedirectResponse
    {
        $firstTimer->delete();

        return back()->with('status', 'Card deleted.');
    }

    public function export(): StreamedResponse
    {
        return Csv::download('first-timers-'.now()->format('Y-m-d').'.csv',
            ['visited_on', 'name', 'phone', 'email', 'invited_by', 'wants_to_join', 'followed_up_on', 'note'],
            function ($write) {
                FirstTimer::orderBy('visited_on')->each(fn (FirstTimer $f) => $write([
                    $f->visited_on->toDateString(), $f->name, $f->phone, $f->email, $f->invited_by, $f->wants_to_join ? 'yes' : 'no', $f->followed_up_at?->toDateString(), $f->follow_up_note,
                ]));
            });
    }

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'invited_by' => ['nullable', 'string', 'max:100'],
            'wants_to_join' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([...self::rules(), 'visited_on' => ['required', 'date', 'before_or_equal:today']]);

        return [...$data, 'wants_to_join' => $request->boolean('wants_to_join')];
    }
}
