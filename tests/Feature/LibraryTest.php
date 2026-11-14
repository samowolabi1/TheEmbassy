<?php

namespace Tests\Feature;

use App\Mail\LibraryNotice;
use App\Models\Book;
use App\Models\BookLoan;
use App\Models\BookReview;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LibraryTest extends TestCase
{
    use RefreshDatabase;

    private Member $member;

    private User $librarian;

    private Book $book;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->fundTreasury(100_000);
        $this->member = Member::factory()->active()->create(['name' => 'Bro. Tunde Bakare']);
        $this->librarian = User::factory()->create(['role' => 'librarian']);
        $this->book = Book::create(['title' => 'The Purpose of Prayer', 'author' => 'E. M. Bounds', 'category' => 'prayer', 'copies' => 1, 'shelf' => 'Shelf A1', 'is_active' => true]);
    }

    private function reserve(Member $member, ?Book $book = null): BookLoan
    {
        $this->actingAs($member->user)->post(route('library.reserve', $book ?? $this->book))->assertSessionHasNoErrors();

        return BookLoan::where('member_id', $member->id)->latest('id')->firstOrFail();
    }

    public function test_a_member_reserves_collects_and_returns_and_the_queue_moves(): void
    {
        $this->actingAs($this->member->user)->get(route('library.index'))->assertOk()->assertSee('The Purpose of Prayer');
        $this->get(route('library.show', $this->book))->assertOk()->assertSee('E. M. Bounds')->assertSee('Shelf A1');

        $loan = $this->reserve($this->member);
        $this->assertSame(BookLoan::REQUESTED, $loan->status);
        $this->assertSame(0, $this->book->copiesFree());

        // The only copy is set aside, so the next person waits.
        $friend = Member::factory()->active()->create(['name' => 'Sis. Ngozi Eze']);
        $waiting = $this->reserve($friend);
        $this->assertSame([BookLoan::WAITING, 1], [$waiting->status, $waiting->queuePosition()]);
        $this->get(route('library.show', $this->book))->assertOk()->assertSee('waiting');

        // The librarian hands it over: the loan runs two weeks.
        $this->actingAs($this->librarian)->get(route('admin.library.desk'))->assertOk()->assertSee('Bro. Tunde Bakare')->assertSee('Hand over');
        $this->actingAs($this->librarian)->post(route('admin.library.lend', $loan))->assertSessionHasNoErrors();
        $loan->refresh();
        $this->assertSame([BookLoan::ON_LOAN, today()->addDays(14)->toDateString()], [$loan->status, $loan->due_on->toDateString()]);
        $this->actingAs($this->librarian)->post(route('admin.library.lend', $waiting))->assertSessionHasErrors('loan');
        $this->get(route('admin.library.desk', ['tab' => 'out']))->assertOk()->assertSee('The Purpose of Prayer');

        $this->actingAs($this->member->user)->get(route('library.show', $this->book))->assertOk()->assertSee('Bring it back by then to earn 5 tokens');

        // Someone is waiting, so the member can't renew.
        $this->actingAs($this->member->user)->post(route('library.renew', $loan))->assertSessionHasErrors('loan');

        // Back: the friend is offered the copy, and the member is thanked and asked for a review.
        $this->actingAs($this->librarian)->post(route('admin.library.receive', $loan))->assertSessionHasNoErrors();
        $this->assertSame(BookLoan::RETURNED, $loan->fresh()->status);
        $this->assertSame(BookLoan::REQUESTED, $waiting->fresh()->status);
        Mail::assertSent(LibraryNotice::class, fn ($m) => $m->hasTo($friend->user->email) && str_contains($m->headline, 'Ready for you'));
        Mail::assertSent(LibraryNotice::class, fn ($m) => $m->hasTo($this->member->user->email) && str_contains($m->body, 'review') && str_contains($m->body, 'earned 5 tokens'));
        $this->assertSame(5, $this->member->user->tokenBalance());
        $this->get(route('admin.library.desk', ['tab' => 'history']))->assertOk()->assertSee('returned');
    }

    public function test_renewing_and_extending(): void
    {
        $loan = $this->reserve($this->member);
        $this->actingAs($this->librarian)->post(route('admin.library.lend', $loan));
        $due = $loan->fresh()->due_on;

        $this->actingAs($this->member->user)->post(route('library.renew', $loan))->assertSessionHasNoErrors();
        $this->assertSame($due->copy()->addDays(14)->toDateString(), $loan->fresh()->due_on->toDateString());
        $this->post(route('library.renew', $loan))->assertSessionHasErrors('loan'); // once only

        // Someone else can't renew it.
        $other = Member::factory()->active()->create();
        $this->actingAs($other->user)->post(route('library.renew', $loan))->assertNotFound();

        // Staff can give more time; overdue loans count from today.
        $loan->update(['due_on' => today()->subDays(3)]);
        $this->actingAs($this->librarian)->post(route('admin.library.extend', $loan), ['days' => 7])->assertSessionHasNoErrors();
        $this->assertSame(today()->addDays(7)->toDateString(), $loan->fresh()->due_on->toDateString());
        $this->post(route('admin.library.extend', $loan), ['days' => 100])->assertSessionHasErrors('days');

        // Brought back late: thanked, but no tokens.
        $loan->update(['due_on' => today()->subDay()]);
        $this->post(route('admin.library.receive', $loan))->assertSessionHasNoErrors();
        $this->assertSame(0, $this->member->user->tokenBalance());
    }

    public function test_limits_overdue_books_and_cancelling(): void
    {
        config(['church.library.max_books' => 2]);
        $books = collect(range(1, 3))->map(fn ($i) => Book::create(['title' => "Book {$i}", 'author' => 'A. Writer', 'category' => 'devotional', 'copies' => 2, 'is_active' => true]));

        $first = $this->reserve($this->member, $books[0]);
        $this->actingAs($this->member->user)->post(route('library.reserve', $books[0]))->assertSessionHasErrors('book'); // twice
        $this->reserve($this->member, $books[1]);
        $this->post(route('library.reserve', $books[2]))->assertSessionHasErrors('book'); // limit

        // Cancelling frees the slot.
        $this->post(route('library.cancel', $first))->assertSessionHasNoErrors();
        $this->assertSame(BookLoan::CANCELLED, $first->fresh()->status);
        $third = $this->reserve($this->member, $books[2]);

        // An overdue book blocks new reservations.
        $this->actingAs($this->librarian)->post(route('admin.library.lend', $third));
        $third->update(['due_on' => today()->subDay()]);
        $this->actingAs($this->member->user)->post(route('library.cancel', $third))->assertSessionHasErrors('loan');
        $this->post(route('library.cancel', BookLoan::where('book_id', $books[1]->id)->first()));
        $this->post(route('library.reserve', $books[0]))->assertSessionHasErrors('book');
        $this->get(route('library.index'))->assertOk()->assertSee('Was due');

        // Hidden books can't be borrowed or seen.
        $books[0]->update(['is_active' => false]);
        $this->get(route('library.show', $books[0]))->assertNotFound();
    }

    public function test_reminders_and_releasing_uncollected_reservations(): void
    {
        $friend = Member::factory()->active()->create();
        $soon = $this->reserve($this->member);
        $this->actingAs($this->librarian)->post(route('admin.library.lend', $soon));
        $soon->update(['due_on' => today()->addDay()]);

        $spare = Book::create(['title' => 'Uncollected', 'author' => 'C', 'category' => 'youth', 'copies' => 1, 'is_active' => true]);
        $uncollected = $this->reserve($friend, $spare);
        $next = $this->reserve($this->member, $spare);

        $late = Book::create(['title' => 'Late Book', 'author' => 'B', 'category' => 'fiction', 'copies' => 1, 'is_active' => true]);
        $lateLoan = $this->reserve($friend, $late);
        $this->actingAs($this->librarian)->post(route('admin.library.lend', $lateLoan));
        $lateLoan->update(['due_on' => today()->subDays(4)]);
        BookLoan::whereKey($uncollected->id)->toBase()->update(['updated_at' => now()->subDays(8)]);

        $this->artisan('library:remind')->assertSuccessful();
        $this->assertNotNull($soon->fresh()->reminded_at);
        $this->assertNotNull($lateLoan->fresh()->overdue_reminded_at);
        $this->assertSame(BookLoan::CANCELLED, $uncollected->fresh()->status);
        $this->assertSame(BookLoan::REQUESTED, $next->fresh()->status);
        Mail::assertSent(LibraryNotice::class, fn ($m) => str_contains($m->headline, 'Due back soon'));
        Mail::assertSent(LibraryNotice::class, fn ($m) => str_contains($m->headline, 'Overdue'));
        Mail::assertSent(LibraryNotice::class, fn ($m) => str_contains($m->headline, 'released'));

        // Not again the next day.
        Mail::fake();
        $this->artisan('library:remind');
        Mail::assertNothingSent();
    }

    public function test_reviews_in_the_community(): void
    {
        $this->actingAs($this->member->user)->post(route('community.reviews.store', $this->book), ['rating' => 5, 'body' => 'Changed how I pray every morning.'])->assertSessionHasNoErrors();
        $this->post(route('community.reviews.store', $this->book), ['rating' => 4, 'body' => 'Still great on a second read.']);
        $this->assertSame(1, BookReview::count());
        $this->assertSame(2, $this->member->user->tokenBalance()); // the first review only
        $review = BookReview::first();
        $this->assertSame(4, $review->rating);
        $this->post(route('community.reviews.store', $this->book), ['rating' => 9, 'body' => 'x'])->assertSessionHasErrors(['rating', 'body']);

        $this->get(route('community.reviews'))->assertOk()->assertSee('Still great on a second read.')->assertSee('The Purpose of Prayer');
        $this->get(route('community.index'))->assertOk()->assertSee('BOOK REVIEWS')->assertSee('The Purpose of Prayer');
        $this->get(route('library.show', $this->book))->assertOk()->assertSee('Still great on a second read.');

        // Moderators take reviews down; others can't.
        $other = Member::factory()->active()->create();
        $this->actingAs($other->user)->delete(route('community.reviews.destroy', $review))->assertForbidden();
        $pastor = User::factory()->create(['role' => 'pastor']);
        $this->actingAs($pastor)->delete(route('community.reviews.destroy', $review));
        $this->assertNotNull($review->fresh()->removed_at);
        $this->get(route('community.reviews'))->assertOk()->assertDontSee('Still great on a second read.');

        // Writers delete their own.
        $this->actingAs($other->user)->post(route('community.reviews.store', $this->book), ['rating' => 3, 'body' => 'Good, a bit long in places.']);
        $this->delete(route('community.reviews.destroy', BookReview::where('member_id', $other->id)->first()));
        $this->assertSame(1, BookReview::count());

        // Muted members can't review.
        $other->user->forceFill(['community_muted_until' => now()->addDay()])->save();
        $this->actingAs($other->user)->post(route('community.reviews.store', $this->book), ['rating' => 3, 'body' => 'Trying while muted.'])->assertForbidden();
    }

    public function test_who_can_get_in(): void
    {
        $this->get(route('library.index'))->assertRedirect(route('login'));
        $pending = Member::factory()->create();
        $this->actingAs($pending->user)->get(route('library.index'))->assertForbidden();
        $this->post(route('library.reserve', $this->book))->assertForbidden();
        $this->post(route('community.reviews.store', $this->book), ['rating' => 5, 'body' => 'Not a member yet.'])->assertForbidden();

        // Staff can look but not borrow; finance staff can't run the desk.
        $finance = User::factory()->create(['role' => 'finance']);
        $this->actingAs($finance)->get(route('library.index'))->assertOk();
        $this->post(route('library.reserve', $this->book))->assertForbidden();
        $this->get(route('admin.library.desk'))->assertForbidden();
        $this->actingAs($this->member->user)->get(route('admin.library.desk'))->assertForbidden();
    }

    public function test_the_librarian_keeps_the_catalogue(): void
    {
        Storage::fake('public');
        $this->actingAs($this->librarian)->get(route('admin.library.books.create'))->assertOk();
        $this->post(route('admin.library.books.store'), [
            'title' => 'Boundaries in Marriage', 'author' => 'H. Cloud', 'category' => 'relationships', 'copies' => 3, 'is_active' => '1',
            'cover' => UploadedFile::fake()->image('cover.jpg', 600, 900),
        ])->assertSessionHasNoErrors();
        $book = Book::where('title', 'Boundaries in Marriage')->firstOrFail();
        $this->assertSame([3, true, 'boundaries-in-marriage'], [$book->copies, $book->is_active, $book->slug]);
        $this->assertNotNull($book->cover_path);
        $this->get(route('admin.library.books'))->assertOk()->assertSee('Boundaries in Marriage');
        $this->get(route('admin.library.books.edit', $book))->assertOk();

        // Copies can't drop below what's out.
        $loan = $this->reserve($this->member, $book);
        $this->actingAs($this->librarian)->put(route('admin.library.books.update', $book), ['title' => $book->title, 'author' => 'H. Cloud', 'category' => 'relationships', 'copies' => 0])->assertSessionHasErrors('copies');
        $this->put(route('admin.library.books.update', $book), ['title' => $book->title, 'author' => 'H. Cloud', 'category' => 'relationships', 'copies' => 1, 'remove_cover' => '1'])->assertSessionHasNoErrors();
        $book->refresh();
        $this->assertSame([1, false, null], [$book->copies, $book->is_active, $book->cover_path]);
        $this->assertSame(BookLoan::REQUESTED, $loan->fresh()->status);

        // The pages draw covers when there's no photo.
        $this->actingAs($this->member->user)->get(route('library.index'))->assertOk()->assertSee('The Purpose of Prayer');
        $this->get(route('library.show', $book))->assertNotFound();
    }
}
