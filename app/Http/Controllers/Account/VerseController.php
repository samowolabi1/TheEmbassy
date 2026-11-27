<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Verse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The members' verse scroll: swipe up or tap for another verse, and save the ones that speak.
 */
class VerseController extends Controller
{
    private const BATCH = 25;

    public function index(Request $request): View
    {
        $member = $this->member($request);

        return view('account.verses', [
            'verses' => $this->batch([]),
            'saved' => $member->savedVerses()->pluck('verses.id')->all(),
            'savedCount' => $member->savedVerses()->count(),
        ]);
    }

    /**
     * More verses for the scroll, skipping the ones already shown (until all have been seen).
     */
    public function more(Request $request): JsonResponse
    {
        $this->member($request);
        $seen = collect(explode(',', (string) $request->query('seen')))->map(fn ($id) => (int) $id)->filter()->take(1000)->all();

        return response()->json(['verses' => $this->batch($seen)]);
    }

    public function save(Request $request, Verse $verse): JsonResponse|RedirectResponse
    {
        $member = $this->member($request);
        $saved = ! $member->savedVerses()->whereKey($verse->id)->exists();
        $saved ? $member->savedVerses()->attach($verse->id, ['created_at' => now()]) : $member->savedVerses()->detach($verse->id);

        return $request->expectsJson()
            ? response()->json(['saved' => $saved])
            : back()->with('status', $saved ? 'Saved to your verses.' : 'Removed from your verses.');
    }

    public function saved(Request $request): View
    {
        return view('account.verses-saved', ['verses' => $this->member($request)->savedVerses()->get()]);
    }

    /**
     * @param  list<int>  $seen
     * @return list<array{id: int, reference: string, text: string, theme: ?string}>
     */
    private function batch(array $seen): array
    {
        $verses = Verse::active()->whereNotIn('id', $seen)->inRandomOrder()->take(self::BATCH)->get();
        if ($verses->isEmpty()) {
            // They've seen every verse: start again.
            $verses = Verse::active()->inRandomOrder()->take(self::BATCH)->get();
        }

        return $verses->map->toCard()->all();
    }

    private function member(Request $request): Member
    {
        return $request->user()->member ?? abort(403, 'The verse scroll is for church members.');
    }
}
