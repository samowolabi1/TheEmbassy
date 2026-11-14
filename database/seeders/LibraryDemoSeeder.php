<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\BookLoan;
use App\Models\BookReview;
use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Demo books for trying the church library on a local copy: 14 books across the categories, with
 * drawn covers. When the demo members are there (run MemberDemoSeeder first), some books are out
 * on loan (one overdue, one due in two days), some are set aside to collect, one has a waiting
 * list, and members have written reviews.
 *
 *     php artisan db:seed --class=LibraryDemoSeeder
 *
 * Safe to run again: books already there are left alone. Refuses to run on a live site.
 */
class LibraryDemoSeeder extends Seeder
{
    private const BOOKS = [
        ['The Purpose Driven Life', 'Rick Warren', 'christian-living', 3, 'A1', 'Forty short chapters on why you are here, written to be read one a day.'],
        ['Mere Christianity', 'C. S. Lewis', 'christian-living', 2, 'A1', 'A clear, thoughtful case for the Christian faith, first given as radio talks.'],
        ['My Utmost for His Highest', 'Oswald Chambers', 'devotional', 2, 'A2', 'A reading for every day of the year, honest and searching.'],
        ['Streams in the Desert', 'L. B. Cowman', 'devotional', 1, 'A2', 'Daily encouragement for hard seasons.'],
        ['How to Read the Bible for All Its Worth', 'Gordon Fee & Douglas Stuart', 'bible-study', 2, 'B1', 'How to read each kind of book in the Bible: letters, stories, prophecy, poetry and more.'],
        ['The Power of a Praying Woman', 'Stormie Omartian', 'prayer', 2, 'B2', 'Short chapters with prayers for every part of life.'],
        ['The Necessity of Prayer', 'E. M. Bounds', 'prayer', 1, 'B2', 'A classic call to pray with faith and persistence.'],
        ['Hudson Taylor\'s Spiritual Secret', 'Howard & Geraldine Taylor', 'biography', 1, 'C1', 'The life of the missionary to China and what he learned about trusting God.'],
        ['The Five Love Languages', 'Gary Chapman', 'relationships', 2, 'C2', 'How people give and receive love differently, and how to speak your spouse\'s language.'],
        ['Spiritual Leadership', 'J. Oswald Sanders', 'leadership', 1, 'D1', 'What the Bible says about leading others, for anyone who serves in a unit.'],
        ['Money, Possessions and Eternity', 'Randy Alcorn', 'money-career', 1, 'D2', 'A thorough look at what the Bible says about money and giving.'],
        ['Do Hard Things', 'Alex & Brett Harris', 'youth', 2, 'E1', 'Two teenagers challenge their generation to aim higher.'],
        ['The Jesus Storybook Bible', 'Sally Lloyd-Jones', 'children', 3, 'E2', 'Bible stories for children, each one pointing to Jesus.'],
        ['The Screwtape Letters', 'C. S. Lewis', 'fiction', 1, 'F1', 'Letters from a senior demon to his nephew: funny and uncomfortably true.'],
    ];

    private const REVIEWS = [
        [5, 'I read one chapter every morning for forty days. It changed how I think about my work and my unit.'],
        [4, 'Clear and easy to follow. The chapter on rest was exactly what I needed this season.'],
        [5, 'Practical and full of scripture. I bought my own copy after returning this one.'],
        [3, 'Good, though a bit slow in the middle. Worth finishing.'],
        [5, 'My husband and I read it together. We now understand each other much better.'],
        [4, 'Short chapters, perfect for the bus to work.'],
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('The library demo is for local copies only, not a live site.');
        }

        $books = collect();
        foreach (self::BOOKS as [$title, $author, $category, $copies, $shelf, $about]) {
            $books->push(Book::firstOrCreate(['title' => $title], [
                'author' => $author, 'category' => $category, 'copies' => $copies, 'shelf' => "Shelf {$shelf}", 'description' => $about, 'is_active' => true,
            ]));
        }

        $members = Member::where('status', Member::ACTIVE)->whereHas('user', fn ($u) => $u->where('email', 'like', '%@demo.test'))->orderBy('id')->get();
        if ($members->count() < 8 || BookLoan::exists()) {
            return;
        }
        $librarian = User::whereIn('role', ['admin', 'pastor', 'librarian'])->orderBy('id')->first();
        $loan = fn (int $book, int $member, string $status, array $extra = []) => (new BookLoan)->forceFill([
            'book_id' => $books[$book]->id, 'member_id' => $members[$member]->id, 'status' => $status, 'handled_by' => $librarian?->id, ...$extra,
        ])->save();

        // Out on loan: one overdue, one due soon, others with time left.
        $loan(0, 0, BookLoan::ON_LOAN, ['lent_at' => now()->subDays(20), 'due_on' => today()->subDays(6)]);
        $loan(3, 1, BookLoan::ON_LOAN, ['lent_at' => now()->subDays(12), 'due_on' => today()->addDays(2)]);
        $loan(6, 2, BookLoan::ON_LOAN, ['lent_at' => now()->subDays(4), 'due_on' => today()->addDays(10)]);
        $loan(13, 3, BookLoan::ON_LOAN, ['lent_at' => now()->subDays(17), 'due_on' => today()->addDays(11), 'renewals' => 1]);
        // The only copy of Streams in the Desert is out, so two people wait for it.
        $loan(3, 4, BookLoan::WAITING);
        $loan(3, 5, BookLoan::WAITING);
        // Set aside to collect this Sunday.
        $loan(8, 6, BookLoan::REQUESTED);
        $loan(12, 7, BookLoan::REQUESTED);

        // Read and returned, with reviews.
        foreach ([[0, 4], [2, 5], [8, 1], [11, 2], [8, 3], [5, 6], [9, 0], [0, 7]] as $i => [$book, $member]) {
            $loan($book, $member, BookLoan::RETURNED, ['lent_at' => now()->subDays(60 - $i * 5), 'due_on' => today()->subDays(46 - $i * 5), 'returned_at' => now()->subDays(48 - $i * 5)]);
            if ($i < count(self::REVIEWS)) {
                [$rating, $body] = self::REVIEWS[$i];
                $review = (new BookReview)->forceFill(['book_id' => $books[$book]->id, 'member_id' => $members[$member]->id, 'rating' => $rating, 'body' => $body]);
                $review->created_at = $review->updated_at = now()->subDays(47 - $i * 5);
                $review->save();
            }
        }
    }
}
