<?php

namespace App\Http\Controllers;

use App\Models\Testimony;
use App\Models\TestimonyPraise;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Testimonies members have shared and agreed to put on the website.
 */
class TestimonyController extends Controller
{
    public function index(Request $request): View
    {
        $category = array_key_exists($request->query('category'), Testimony::CATEGORIES) ? $request->query('category') : null;

        return view('testimonies.index', [
            'testimonies' => Testimony::public()->with('member')->when($category, fn ($q) => $q->where('category', $category))
                ->latest('published_at')->paginate(12)->withQueryString(),
            'category' => $category,
        ]);
    }

    public function show(Request $request, Testimony $testimony): View
    {
        abort_unless($testimony->status === 'published' && $testimony->share_publicly, 404);

        return view('testimonies.show', [
            'testimony' => $testimony->load('member'),
            'praised' => $request->user() && TestimonyPraise::where('testimony_id', $testimony->id)->where('user_id', $request->user()->id)->exists(),
            'more' => Testimony::public()->with('member')->whereKeyNot($testimony->id)->latest('published_at')->take(3)->get(),
        ]);
    }

    /**
     * "Praise God!" — once each, for signed-in people.
     */
    public function praise(Request $request, Testimony $testimony): RedirectResponse
    {
        abort_unless($testimony->status === 'published' && $testimony->share_publicly, 404);
        try {
            TestimonyPraise::create(['testimony_id' => $testimony->id, 'user_id' => $request->user()->id]);
            $testimony->increment('praises');
        } catch (UniqueConstraintViolationException) {
            // Already praised.
        }

        return back()->with('status', 'Praise God! 🙌');
    }
}
