<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Declaration;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContentController extends Controller
{
    /**
     * Announcements and the weekly declaration in one place.
     */
    public function __invoke(Request $request): View
    {
        $request->validate(['week' => ['nullable', 'date']]);
        $filter = in_array($request->query('show'), ['live', 'scheduled', 'draft'], true) ? $request->query('show') : 'all';

        $announcements = Announcement::query()
            ->when($filter === 'live', fn ($q) => $q->live())
            ->when($filter === 'scheduled', fn ($q) => $q->where('published_at', '>', now()))
            ->when($filter === 'draft', fn ($q) => $q->whereNull('published_at'))
            // Drafts first, then newest.
            ->orderByRaw('published_at is null desc')
            ->latest('published_at')
            ->latest('id')
            ->get();

        // Edit next week's declaration by default once this week's exists.
        $thisWeek = Declaration::weekStarting(today());
        $weekOf = $request->filled('week')
            ? Declaration::weekStarting($request->query('week'))
            : (Declaration::where('week_of', $thisWeek)->exists() ? $thisWeek->copy()->addWeek() : $thisWeek);
        $declaration = Declaration::firstWhere('week_of', $weekOf)
            // Start from the latest lines so only what changes needs typing.
            ?? new Declaration(['week_of' => $weekOf, 'lines' => Declaration::latest('week_of')->first()?->lines ?? ['', '', '', '']]);

        return view('admin.content', [
            'announcements' => $announcements,
            'filter' => $filter,
            'declaration' => $declaration,
            'recentDeclarations' => Declaration::latest('week_of')->take(6)->get(),
            'thisWeek' => $thisWeek,
        ]);
    }
}
