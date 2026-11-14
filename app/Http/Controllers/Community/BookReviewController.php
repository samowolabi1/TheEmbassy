<?php

namespace App\Http\Controllers\Community;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\BookReview;
use App\Models\Member;
use App\Services\Tokens\Rewards;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Members' reviews of the church library's books, in the community.
 */
class BookReviewController extends Controller
{
    public function index(): View
    {
        return view('community.reviews', [
            'reviews' => BookReview::visible()->with('book', 'member')->latest()->paginate(15),
            'topRated' => Book::active()->withAvg(['reviews as rating' => fn ($r) => $r->whereNull('removed_at')], 'rating')
                ->withCount(['reviews as review_count' => fn ($r) => $r->whereNull('removed_at')])
                ->whereHas('reviews', fn ($r) => $r->whereNull('removed_at'))->orderByDesc('rating')->orderByDesc('review_count')->take(5)->get(),
        ]);
    }

    /**
     * Write or change your review of a book.
     */
    public function store(Request $request, Book $book, Rewards $rewards): RedirectResponse
    {
        $member = $request->user()->member;
        abort_unless($member && $member->status === Member::ACTIVE && $book->is_active, 403, 'Only church members can review books.');
        abort_if($request->user()->isMutedInCommunity(), 403, 'You can\'t post in the community right now.');
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'body' => ['required', 'string', 'min:10', 'max:2000'],
        ], ['rating.required' => 'Give it a star rating.', 'body.min' => 'Say a little more about it.']);

        $review = BookReview::where('book_id', $book->id)->where('member_id', $member->id)->first()
            ?? (new BookReview)->forceFill(['book_id' => $book->id, 'member_id' => $member->id]);
        $review->fill($data)->save();
        $earned = $rewards->bookReview($request->user(), $book);

        return redirect()->to(route('library.show', $book).'#reviews')->with('status', 'Thanks for your review!'.($earned ? " You've earned {$earned} tokens." : ''));
    }

    /**
     * Writers delete their own; moderators take any down.
     */
    public function destroy(Request $request, BookReview $review): RedirectResponse
    {
        $user = $request->user();
        if ($review->member_id === $user->member?->id) {
            $review->delete();

            return back()->with('status', 'Review deleted.');
        }
        abort_unless($user->moderatesCommunity(), 403);
        $review->forceFill(['removed_at' => now(), 'removed_by' => $user->id])->save();

        return back()->with('status', 'Review taken down.');
    }
}
