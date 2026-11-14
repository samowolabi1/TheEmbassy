<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\BookLoan;
use App\Models\BookReview;
use App\Models\Member;
use App\Services\Library\Lending;
use App\Services\Tokens\Rewards;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The church library for members: browse and search the books, reserve one, and see what you
 * have borrowed. Approved members and staff can look; members borrow.
 */
class LibraryController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        $category = array_key_exists($request->query('category'), Book::CATEGORIES) ? $request->query('category') : null;
        $member = $this->member($request);

        return view('library.index', [
            'returnTokens' => $this->tokens('book_returned'),
            'books' => Book::active()
                ->withCount(['loans as taken' => fn ($l) => $l->whereIn('status', [BookLoan::REQUESTED, BookLoan::ON_LOAN])])
                ->withAvg(['reviews as rating' => fn ($r) => $r->whereNull('removed_at')], 'rating')
                ->when($category, fn ($b) => $b->where('category', $category))
                ->when($q !== '', fn ($b) => $b->where(fn ($w) => $w->where('title', 'like', "%{$q}%")->orWhere('author', 'like', "%{$q}%")))
                ->orderBy('title')->paginate(24)->withQueryString(),
            'q' => $q,
            'category' => $category,
            'mine' => $member ? BookLoan::with('book')->where('member_id', $member->id)->whereIn('status', BookLoan::OPEN)->orderByRaw("case status when 'on_loan' then 0 when 'requested' then 1 else 2 end")->get() : collect(),
        ]);
    }

    public function show(Request $request, Book $book): View
    {
        abort_unless($book->is_active, 404);
        $member = $this->member($request);

        return view('library.show', [
            'returnTokens' => $this->tokens('book_returned'),
            'reviewTokens' => $this->tokens('book_review'),
            'book' => $book,
            'free' => $book->copiesFree(),
            'queue' => $book->queueLength(),
            'myLoan' => $member ? BookLoan::where('book_id', $book->id)->where('member_id', $member->id)->whereIn('status', BookLoan::OPEN)->first() : null,
            'borrowedBefore' => $member && BookLoan::where('book_id', $book->id)->where('member_id', $member->id)->whereIn('status', [BookLoan::ON_LOAN, BookLoan::RETURNED])->exists(),
            'reviews' => $book->reviews()->visible()->with('member')->latest()->get(),
            'myReview' => $member ? BookReview::where('book_id', $book->id)->where('member_id', $member->id)->first() : null,
            'canBorrow' => (bool) $member,
        ]);
    }

    public function reserve(Request $request, Book $book, Lending $lending): RedirectResponse
    {
        $member = $this->member($request) ?? abort(403, 'Only church members can borrow books.');
        $loan = $lending->reserve($member, $book);

        return back()->with('status', $loan->status === BookLoan::REQUESTED
            ? 'Reserved! Collect "'.$book->title.'" from the church library within '.config('church.library.collect_days', 7).' days.'
            : 'All copies are out, so you\'re on the waiting list (number '.$loan->queuePosition().'). We\'ll tell you when it\'s ready.');
    }

    public function cancel(Request $request, BookLoan $loan, Lending $lending): RedirectResponse
    {
        $this->own($request, $loan);
        $lending->cancel($loan);

        return back()->with('status', 'Reservation cancelled.');
    }

    public function renew(Request $request, BookLoan $loan, Lending $lending): RedirectResponse
    {
        $this->own($request, $loan);
        $lending->renew($loan);

        return back()->with('status', 'Renewed. It\'s now due back on '.$loan->fresh()->due_on->format('l j M').'.');
    }

    private function own(Request $request, BookLoan $loan): void
    {
        abort_unless($loan->member_id === $this->member($request)?->id, 404);
    }

    /**
     * What a library rule earns, for the hints on the pages (0 when tokens are off).
     */
    private function tokens(string $rule): int
    {
        $rewards = app(Rewards::class);

        return $rewards->enabled() ? $rewards->amount($rule) : 0;
    }

    private function member(Request $request): ?Member
    {
        $member = $request->user()->member;

        return $member && $member->status === Member::ACTIVE ? $member : null;
    }
}
