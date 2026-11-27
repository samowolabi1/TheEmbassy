<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Verse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The verses members see in the verse scroll and the daily verse.
 */
class VerseController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        $theme = in_array($request->query('theme'), Verse::THEMES, true) ? $request->query('theme') : null;
        $hidden = $request->query('show') === 'hidden';

        $verses = Verse::query()
            ->where('is_active', ! $hidden)
            ->when($theme, fn ($query) => $query->where('theme', $theme))
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('reference', 'like', '%'.addcslashes($q, '%_\\').'%')
                ->orWhere('text', 'like', '%'.addcslashes($q, '%_\\').'%')))
            ->orderBy('reference')
            ->paginate(40)->withQueryString();

        return view('admin.verses', [
            'verses' => $verses,
            'q' => $q,
            'theme' => $theme,
            'hidden' => $hidden,
            'activeCount' => Verse::active()->count(),
            'hiddenCount' => Verse::where('is_active', false)->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $verse = Verse::create($this->validated($request));

        return back()->with('status', "{$verse->reference} added. Members will start seeing it.");
    }

    public function update(Request $request, Verse $verse): RedirectResponse
    {
        $verse->update($this->validated($request));

        return back()->with('status', "{$verse->reference} saved.");
    }

    /**
     * Hide a verse from members, or show it again.
     */
    public function toggle(Verse $verse): RedirectResponse
    {
        $verse->update(['is_active' => ! $verse->is_active]);

        return back()->with('status', $verse->is_active ? "{$verse->reference} is showing again." : "{$verse->reference} is hidden from members.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'reference' => ['required', 'string', 'max:60'],
            'text' => ['required', 'string', 'max:1500'],
            'theme' => ['nullable', Rule::in(Verse::THEMES)],
        ], ['reference.required' => 'Add the reference, e.g. John 3:16.', 'text.required' => 'Add the words of the verse.']);
    }
}
