<?php

namespace App\Services\Library;

use App\Mail\LibraryNotice;
use App\Models\Book;
use App\Models\BookLoan;
use App\Models\Member;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\Messaging\WebPush;
use App\Services\Tokens\Rewards;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Borrowing from the church library. A member reserves a book online; if a copy is free it's set
 * aside for them to collect at church, otherwise they join the queue. The librarian hands it over
 * (the loan starts) and takes it back; the next person in the queue is then told a copy is ready.
 * Members get reminders before the due date and when it's late.
 */
class Lending
{
    public function __construct(private WebPush $push, private Rewards $rewards) {}

    public static function loanDays(): int
    {
        return max(1, (int) config('church.library.loan_days', 14));
    }

    public function reserve(Member $member, Book $book): BookLoan
    {
        if (! $book->is_active) {
            throw ValidationException::withMessages(['book' => 'This book isn\'t available to borrow.']);
        }
        $open = BookLoan::where('member_id', $member->id)->whereIn('status', BookLoan::OPEN)->get();
        if ($open->contains('book_id', $book->id)) {
            throw ValidationException::withMessages(['book' => 'You already have this book, or are waiting for it.']);
        }
        if ($open->contains(fn (BookLoan $l) => $l->isOverdue())) {
            throw ValidationException::withMessages(['book' => 'Please return your overdue book first.']);
        }
        $max = (int) config('church.library.max_books', 3);
        if ($open->count() >= $max) {
            throw ValidationException::withMessages(['book' => "You can have up to {$max} books at a time, counting reservations."]);
        }

        // Inside a transaction, so two people can't take the last copy at once.
        $loan = DB::transaction(function () use ($member, $book) {
            $free = Book::whereKey($book->id)->lockForUpdate()->first()->copiesFree();
            $waiting = $book->queueLength();

            return BookLoan::create([
                'book_id' => $book->id, 'member_id' => $member->id,
                'status' => $free > 0 && $waiting === 0 ? BookLoan::REQUESTED : BookLoan::WAITING,
            ]);
        });

        return $loan;
    }

    /**
     * The member changes their mind (before collecting it).
     */
    public function cancel(BookLoan $loan): void
    {
        if (! in_array($loan->status, [BookLoan::REQUESTED, BookLoan::WAITING], true)) {
            throw ValidationException::withMessages(['loan' => 'Only reservations can be cancelled.']);
        }
        $freed = $loan->status === BookLoan::REQUESTED;
        $loan->update(['status' => BookLoan::CANCELLED]);
        if ($freed) {
            $this->offerNext($loan->book);
        }
    }

    /**
     * The librarian hands the book over: the loan starts now.
     */
    public function lend(BookLoan $loan, User $by): void
    {
        if ($loan->status !== BookLoan::REQUESTED) {
            throw ValidationException::withMessages(['loan' => $loan->status === BookLoan::WAITING
                ? 'No copy is free for this person yet; they\'re on the waiting list.'
                : 'This book isn\'t waiting to be handed over.']);
        }
        $loan->update(['status' => BookLoan::ON_LOAN, 'lent_at' => now(), 'due_on' => today()->addDays(self::loanDays()), 'handled_by' => $by->id]);
    }

    /**
     * The book is back. The next person waiting gets the copy.
     */
    public function receive(BookLoan $loan, User $by): void
    {
        if ($loan->status !== BookLoan::ON_LOAN) {
            throw ValidationException::withMessages(['loan' => 'This book isn\'t out on loan.']);
        }
        $onTime = ! $loan->isOverdue();
        $loan->update(['status' => BookLoan::RETURNED, 'returned_at' => now(), 'handled_by' => $by->id]);
        $this->offerNext($loan->book);
        $earned = $onTime && $loan->member->user ? $this->rewards->bookReturned($loan->member->user, $loan) : 0;
        $this->tell($loan->member, 'Thanks for returning '.$loan->book->title,
            'We\'ve got "'.$loan->book->title.'" back'.($earned ? ", on time, so you've earned {$earned} tokens" : '').'. Enjoyed it? Tell others what you thought: write a review in the community.',
            route('library.show', $loan->book).'#review');
    }

    /**
     * The member keeps it longer, if nobody is waiting for it and they haven't renewed too often.
     */
    public function renew(BookLoan $loan): void
    {
        $max = (int) config('church.library.renewals', 1);
        if ($loan->status !== BookLoan::ON_LOAN) {
            throw ValidationException::withMessages(['loan' => 'Only books you have can be renewed.']);
        }
        if ($loan->isOverdue()) {
            throw ValidationException::withMessages(['loan' => 'This book is overdue, so it can\'t be renewed. Please bring it back.']);
        }
        if ($loan->renewals >= $max) {
            throw ValidationException::withMessages(['loan' => "You've already renewed this book {$max} ".str('time')->plural($max).'.']);
        }
        if ($loan->book->queueLength() > 0) {
            throw ValidationException::withMessages(['loan' => 'Someone is waiting for this book, so it can\'t be renewed.']);
        }
        $loan->update(['due_on' => $loan->due_on->copy()->addDays(self::loanDays()), 'renewals' => $loan->renewals + 1, 'reminded_at' => null]);
    }

    /**
     * Staff give more time, whatever the queue.
     */
    public function extend(BookLoan $loan, int $days): void
    {
        if ($loan->status !== BookLoan::ON_LOAN) {
            throw ValidationException::withMessages(['loan' => 'Only books out on loan can be given more time.']);
        }
        $from = $loan->due_on->max(today());
        $loan->update(['due_on' => $from->copy()->addDays($days), 'reminded_at' => null, 'overdue_reminded_at' => null]);
    }

    /**
     * Daily: remind people two days before the due date and when a book is late (then weekly),
     * and release reservations nobody collected within a week.
     *
     * @return array{due: int, overdue: int, released: int}
     */
    public function remind(): array
    {
        $due = $overdue = $released = 0;
        foreach (BookLoan::with('book', 'member.user')->where('status', BookLoan::ON_LOAN)->whereNull('reminded_at')->whereBetween('due_on', [today(), today()->addDays(2)])->get() as $loan) {
            $this->tell($loan->member, 'Due back soon: '.$loan->book->title, '"'.$loan->book->title.'" is due back on '.$loan->due_on->format('l j M').'. You can renew it from the library page if nobody is waiting for it.', route('library.index'));
            $loan->update(['reminded_at' => now()]);
            $due++;
        }
        foreach (BookLoan::with('book', 'member.user')->where('status', BookLoan::ON_LOAN)->where('due_on', '<', today())
            ->where(fn ($q) => $q->whereNull('overdue_reminded_at')->orWhere('overdue_reminded_at', '<', now()->subWeek()))->get() as $loan) {
            $this->tell($loan->member, 'Overdue: '.$loan->book->title, '"'.$loan->book->title.'" was due back on '.$loan->due_on->format('l j M').'. Please bring it to church this Sunday so others can read it too.', route('library.index'));
            $loan->update(['overdue_reminded_at' => now()]);
            $overdue++;
        }
        $days = (int) config('church.library.collect_days', 7);
        foreach (BookLoan::with('book', 'member.user')->where('status', BookLoan::REQUESTED)->where('updated_at', '<', now()->subDays($days))->get() as $loan) {
            $loan->update(['status' => BookLoan::CANCELLED, 'note' => "Not collected within {$days} days"]);
            $this->tell($loan->member, 'Reservation released: '.$loan->book->title, 'Your reservation for "'.$loan->book->title.'" was released because it wasn\'t collected within '.$days.' days. You can reserve it again any time.', route('library.show', $loan->book));
            $this->offerNext($loan->book);
            $released++;
        }

        return compact('due', 'overdue', 'released');
    }

    /**
     * A copy is free: give it to the first person waiting, and tell them.
     */
    private function offerNext(Book $book): void
    {
        if ($book->copiesFree() < 1) {
            return;
        }
        $next = BookLoan::with('member.user')->where('book_id', $book->id)->where('status', BookLoan::WAITING)->orderBy('id')->first();
        if ($next) {
            $next->update(['status' => BookLoan::REQUESTED]);
            $this->tell($next->member, 'Ready for you: '.$book->title, 'A copy of "'.$book->title.'" is now set aside for you. Collect it from the church library within '.config('church.library.collect_days', 7).' days.', route('library.show', $book));
        }
    }

    private function tell(Member $member, string $subject, string $message, string $url): void
    {
        $email = $member->user?->email ?? $member->email;
        if ($email) {
            try {
                Mail::to($email)->send(new LibraryNotice($member, $subject, $message, $url));
            } catch (Throwable $e) {
                Log::error('Could not send library email', ['member' => $member->id, 'error' => $e->getMessage()]);
            }
        }
        if ($member->user && $this->push->configured()) {
            $this->push->send(PushSubscription::where('user_id', $member->user_id)->get(), ['title' => $subject, 'body' => $message, 'url' => $url, 'tag' => 'library']);
        }
    }
}
