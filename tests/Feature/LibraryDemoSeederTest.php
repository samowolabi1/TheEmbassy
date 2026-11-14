<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookLoan;
use App\Models\BookReview;
use App\Models\User;
use Database\Seeders\LibraryDemoSeeder;
use Database\Seeders\MemberDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class LibraryDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_demo_fills_the_library_once_with_loans_and_reviews(): void
    {
        $this->seed(MemberDemoSeeder::class);
        $this->seed(LibraryDemoSeeder::class);
        $this->seed(LibraryDemoSeeder::class); // a second run adds nothing

        $this->assertSame(14, Book::count());
        $this->assertSame(4, BookLoan::where('status', BookLoan::ON_LOAN)->count());
        $this->assertSame(1, BookLoan::where('status', BookLoan::ON_LOAN)->where('due_on', '<', today())->count());
        $this->assertSame(2, BookLoan::where('status', BookLoan::WAITING)->count());
        $this->assertSame(6, BookReview::count());

        $member = User::where('email', 'member1@demo.test')->firstOrFail();
        $this->actingAs($member)->get(route('library.index'))->assertOk()->assertSee('Mere Christianity');
        $this->get(route('community.reviews'))->assertOk()->assertSee('forty days');

        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('admin.library.desk', ['tab' => 'overdue']))->assertOk()->assertSee('The Purpose Driven Life');
    }

    public function test_books_alone_without_demo_members(): void
    {
        $this->seed(LibraryDemoSeeder::class);
        $this->assertSame([14, 0], [Book::count(), BookLoan::count()]);
    }

    public function test_it_refuses_to_run_on_a_live_site(): void
    {
        $this->app['env'] = 'production';
        $this->expectException(RuntimeException::class);
        $this->app->make(LibraryDemoSeeder::class)->run();
    }
}
