<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Testimony;
use App\Services\Pastoral\Testimonies;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Reading members' testimonies, tidying them, and publishing them.
 */
class TestimonyController extends Controller
{
    public const TABS = ['pending' => 'To read', 'published' => 'Published', 'declined' => 'Not published'];

    public function index(Request $request): View
    {
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'pending';

        return view('admin.testimonies', [
            'tab' => $tab,
            'testimonies' => Testimony::with('member')->where('status', $tab)
                ->when($tab === 'published', fn ($q) => $q->latest('published_at'), fn ($q) => $q->latest())
                ->paginate(15)->withQueryString(),
            'counts' => collect(array_keys(self::TABS))->mapWithKeys(fn ($s) => [$s => Testimony::where('status', $s)->count()])->all(),
        ]);
    }

    /**
     * Light edits (spelling, length), and publish if asked.
     */
    public function update(Request $request, Testimony $testimony, Testimonies $testimonies): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'category' => ['required', Rule::in(array_keys(Testimony::CATEGORIES))],
            'body' => ['required', 'string', 'max:5000'],
        ]);
        $testimony->update($data);
        if ($request->input('action') === 'publish') {
            $testimonies->publish($testimony, $request->user());

            return back()->with('status', 'Published. '.$testimony->member->name.' has been told.');
        }

        return back()->with('status', 'Saved.');
    }

    public function decline(Request $request, Testimony $testimony, Testimonies $testimonies): RedirectResponse
    {
        $note = $request->validate(['review_note' => ['required', 'string', 'max:300']], ['review_note.required' => 'Say why, kindly; the member sees it.'])['review_note'];
        $testimonies->decline($testimony, $request->user(), $note);

        return back()->with('status', 'Not published. '.$testimony->member->name.' has been told.');
    }

    public function unpublish(Testimony $testimony, Testimonies $testimonies): RedirectResponse
    {
        $testimonies->unpublish($testimony);

        return back()->with('status', 'Taken down. It\'s back under "To read".');
    }
}
