<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Expense;
use App\Models\Income;
use App\Models\MoneyCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IncomeTest extends TestCase
{
    use RefreshDatabase;

    private function income(array $overrides = []): array
    {
        return ['title' => 'Sunday offering, first service', 'category' => 'Sunday offering', 'amount' => '84500', 'received_on' => today()->toDateString(), 'method' => 'cash', ...$overrides];
    }

    public function test_any_staff_member_records_money_in_and_out(): void
    {
        Storage::fake('local');
        $media = User::factory()->create(['role' => 'media']);
        $this->actingAs($media)->get(route('admin.money.index'))->assertOk()->assertSee('Record income &amp; spending', false)->assertSee('Sunday offering')->assertDontSee('See the summary');

        $this->post(route('admin.money.income.store'), $this->income(['receipt' => UploadedFile::fake()->image('count-sheet.jpg')]))
            ->assertRedirect(route('admin.money.index', ['type' => 'in']))->assertSessionHasNoErrors();
        $income = Income::firstOrFail();
        $this->assertSame([8_450_000, Income::PENDING, $media->id, 'cash'], [$income->amount, $income->status, $income->recorded_by, $income->method]);
        $this->assertTrue($income->hasReceipt());

        $this->get(route('admin.money.index', ['type' => 'out']))->assertOk()->assertSee('Generator diesel');
        $this->post(route('admin.expenses.store'), ['title' => 'Diesel', 'category' => 'Venue & utilities', 'amount' => '25000', 'spent_on' => today()->toDateString()])
            ->assertRedirect(route('admin.money.index', ['type' => 'out']));
        $this->get(route('admin.money.index'))->assertOk()->assertSee('Sunday offering, first service')->assertSee('Diesel')->assertSee('Being checked');

        // Checks: categories must exist, no future dates.
        $this->post(route('admin.money.income.store'), $this->income(['category' => 'Made up', 'received_on' => today()->addDay()->toDateString(), 'method' => 'cheque']))
            ->assertSessionHasErrors(['category', 'received_on', 'method']);

        // They can't confirm, see the summary or open receipts.
        $this->get(route('admin.income.index'))->assertForbidden();
        $this->post(route('admin.income.confirm', $income))->assertForbidden();
        $this->get(route('admin.income.receipt', $income))->assertForbidden();
        $this->get(route('admin.expenses.index'))->assertForbidden();

        // Members and the public can't record anything.
        $this->post(route('admin.logout'));
        $this->post(route('admin.money.income.store'), $this->income())->assertRedirect();
        $this->assertSame(1, Income::count());
    }

    public function test_finance_confirms_and_sees_the_month(): void
    {
        $usher = User::factory()->create(['role' => 'media']);
        $this->actingAs($usher)->post(route('admin.money.income.store'), $this->income());
        $this->post(route('admin.money.income.store'), $this->income(['title' => 'Hall hire for a wedding', 'category' => 'Hall & equipment hire', 'amount' => '50000', 'method' => 'transfer']));
        $this->post(route('admin.money.income.store'), $this->income(['title' => 'Miscounted', 'amount' => '999']));
        $this->post(route('admin.expenses.store'), ['title' => 'Diesel', 'category' => 'Venue & utilities', 'amount' => '25000', 'spent_on' => today()->toDateString()]);
        [$offering, $hire, $wrong] = Income::orderBy('id')->get()->all();

        $finance = User::factory()->create(['role' => 'finance']);
        $this->actingAs($finance)->get(route('admin.income.index'))->assertOk()->assertSee('Hall hire for a wedding')->assertSee('To confirm');
        $this->post(route('admin.income.confirm', $offering))->assertSessionHasNoErrors();
        $this->post(route('admin.income.confirm', $hire));
        $this->post(route('admin.income.decline', $wrong), ['reason' => 'Recount: it was ₦9,990'])->assertSessionHasNoErrors();
        $this->post(route('admin.income.confirm', $wrong))->assertStatus(422);
        $this->post(route('admin.expenses.approve', Expense::firstOrFail()));

        $this->assertSame([Income::CONFIRMED, Income::DECLINED], [$offering->fresh()->status, $wrong->fresh()->status]);
        $this->get(route('admin.income.index'))->assertOk()
            ->assertSee('₦134,500')->assertSee('₦25,000')->assertSee('₦109,500')->assertSee('Left over');
        $this->get(route('admin.income.index', ['month' => now()->subMonth()->format('Y-m')]))->assertOk()->assertSee('No confirmed income this month.');

        // The person who recorded it sees the outcome.
        $this->actingAs($usher)->get(route('admin.money.index'))->assertOk()->assertSee('Confirmed')->assertSee('Recount: it was ₦9,990');

        // Nobody confirms their own, except an administrator.
        $this->actingAs($finance)->post(route('admin.money.income.store'), $this->income(['title' => 'Own entry']));
        $this->post(route('admin.income.confirm', Income::where('title', 'Own entry')->first()))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.income.confirm', Income::where('title', 'Own entry')->first()))->assertSessionHasNoErrors();

        $csv = $this->actingAs($finance)->get(route('admin.income.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString('Hall hire for a wedding', $csv);
        $this->assertStringContainsString(',out,Diesel,', $csv);
    }

    public function test_categories_can_be_added_renamed_and_hidden(): void
    {
        $pastor = User::factory()->create(['role' => 'pastor']);
        $this->actingAs($pastor)->get(route('admin.income.index'))->assertOk()->assertSee('Welfare contributions');
        $this->post(route('admin.money-categories.store'), ['kind' => 'in', 'name' => 'Youth camp fees'])->assertSessionHasNoErrors();
        $this->post(route('admin.money-categories.store'), ['kind' => 'in', 'name' => 'Youth camp fees'])->assertSessionHasErrors('name', null, 'categories');
        $this->post(route('admin.money.income.store'), $this->income(['category' => 'Youth camp fees']))->assertSessionHasNoErrors();

        // Renaming carries entries and budgets along.
        $this->post(route('admin.expenses.store'), ['title' => 'Bulbs', 'category' => 'Venue & utilities', 'amount' => '5000', 'spent_on' => today()->toDateString()]);
        Budget::create(['category' => 'Venue & utilities', 'month' => now()->startOfMonth(), 'amount' => 1_000_000]);
        $out = MoneyCategory::where('kind', 'out')->where('name', 'Venue & utilities')->firstOrFail();
        $this->put(route('admin.money-categories.update', $out), ['name' => 'Rent, power & water', 'is_active' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('Rent, power & water', Expense::first()->category);
        $this->assertSame('Rent, power & water', Budget::first()->category);
        $this->get(route('admin.expenses.index'))->assertOk()->assertSee('Rent, power &amp; water', false);

        // Hidden ones can't be chosen but keep their history.
        $camp = MoneyCategory::where('name', 'Youth camp fees')->firstOrFail();
        $this->put(route('admin.money-categories.update', $camp), ['name' => 'Youth camp fees']);
        $this->assertFalse($camp->fresh()->is_active);
        $this->post(route('admin.money.income.store'), $this->income(['category' => 'Youth camp fees']))->assertSessionHasErrors('category');
        $this->assertSame(1, Income::where('category', 'Youth camp fees')->count());

        // Only finance and pastors manage categories.
        $this->actingAs(User::factory()->create(['role' => 'media']))->post(route('admin.money-categories.store'), ['kind' => 'out', 'name' => 'Nope'])->assertForbidden();
    }
}
