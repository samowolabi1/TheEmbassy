<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Expense;
use App\Models\Income;
use App\Models\User;
use Database\Seeders\MoneyDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class MoneyDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_demo_fills_four_months_of_income_and_expenses_once(): void
    {
        $this->seed(MoneyDemoSeeder::class);
        $counts = [Income::count(), Expense::count(), Budget::count()];
        $this->seed(MoneyDemoSeeder::class); // a second run adds nothing
        $this->assertSame($counts, [Income::count(), Expense::count(), Budget::count()]);

        $this->assertGreaterThan(40, Income::count());
        $this->assertGreaterThan(30, Expense::count());
        $this->assertSame(16, Budget::count());
        $this->assertSame(1, Income::where('status', Income::DECLINED)->count());
        $this->assertSame(1, Expense::where('status', Expense::DECLINED)->count());
        $this->assertGreaterThan(0, Income::where('status', Income::PENDING)->count());
        $this->assertSame(0, Income::where('received_on', '>', today())->count() + Expense::where('spent_on', '>', today())->count());
        $this->assertSame(0, Income::whereColumn('recorded_by', 'decided_by')->count());

        $finance = User::where('email', 'finance@demo.test')->firstOrFail();
        $this->actingAs($finance)->get(route('admin.income.index', ['month' => now()->subMonth()->format('Y-m'), 'tab' => 'confirmed']))->assertOk()->assertSee('Sunday offering')->assertSee('Money in by category');
        $this->get(route('admin.expenses.index', ['tab' => 'approved']))->assertOk()->assertSee('Diesel for Sunday service');
        $this->actingAs(User::where('email', 'usher@demo.test')->firstOrFail())->get(route('admin.money.index'))->assertOk()->assertSee('Sunday offering');
    }

    public function test_it_refuses_to_run_on_a_live_site(): void
    {
        $this->app['env'] = 'production';
        $this->expectException(RuntimeException::class);
        $this->app->make(MoneyDemoSeeder::class)->run();
    }
}
