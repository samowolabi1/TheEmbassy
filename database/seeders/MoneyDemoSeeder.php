<?php

namespace Database\Seeders;

use App\Models\Budget;
use App\Models\Expense;
use App\Models\Income;
use App\Models\MoneyCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Demo income and expenses for trying the money pages on a local copy: this month and the three
 * before it. Offerings after every Sunday and midweek service, thanksgiving, pledges, donations,
 * hall hire, sales and an event; spending on power, diesel, data, refreshments, transport,
 * welfare and outreach, with budgets for this month and last. Older entries are confirmed or
 * approved (one of each declined); the last week's are still waiting to be checked.
 *
 * Four demo staff record and check them. They sign in at /admin/login with the password
 * "demo-password":
 *   finance@demo.test  Finance (confirms and approves)
 *   office@demo.test   Church office
 *   usher@demo.test    Ushering (counts the offerings)
 *   media@demo.test    Media
 *
 *     php artisan db:seed --class=MoneyDemoSeeder
 *
 * Dates are set from today. Safe to run again: nothing is added twice. Refuses to run on a live site.
 */
class MoneyDemoSeeder extends Seeder
{
    public const PASSWORD = 'demo-password';

    private const STAFF = [
        'finance' => ['Deaconess Funke Adeyemi', 'finance'],
        'office' => ['Sis. Ronke Balogun', 'secretary'],
        'usher' => ['Bro. Segun Alabi', 'usher'],
        'media' => ['Bro. Kunle Ojo', 'media'],
    ];

    /** @var array<string, User> */
    private array $staff = [];

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('The money demo is for local copies only, not a live site.');
        }
        mt_srand(2626);

        foreach (self::STAFF as $key => [$name, $role]) {
            $user = User::firstOrCreate(['email' => "{$key}@demo.test"], ['name' => $name, 'password' => Hash::make(self::PASSWORD)]);
            $user->forceFill(['role' => $role])->save();
            $this->staff[$key] = $user;
        }
        $this->command?->line('Staff logins: finance@demo.test, office@demo.test, usher@demo.test, media@demo.test, password '.self::PASSWORD.' (at /admin/login).');

        $recorders = collect($this->staff)->except('finance')->pluck('id');
        if (Income::whereIn('recorded_by', $recorders)->exists() || Expense::whereIn('requested_by', $recorders)->exists()) {
            return;
        }
        MoneyCategory::ensureDefaults();

        $start = now()->startOfMonth()->subMonths(3);
        for ($day = $start->copy(); $day->lte(today()); $day->addDay()) {
            $this->day($day->copy());
        }
        foreach ([1, 7, 14, 21] as $n) {
            $this->lastDays($start->copy()->addMonths(3)->addDays($n - 1));
        }
        $this->monthly($start);
        $this->budgets();
        $this->declined();
    }

    /**
     * Offerings and the weekly costs that come with services.
     */
    private function day(Carbon $day): void
    {
        if ($day->isSunday()) {
            $this->in($day, 'Sunday offering', 'Sunday offering', $this->naira(70_000, 145_000), 'cash', 'usher', 'Counted after service by the ushers and banked on Monday.');
            if (mt_rand(1, 3) === 1) {
                $this->in($day, 'Thanksgiving: '.$this->pick(['the Okafor family', 'a new job', 'a safe delivery', 'graduation', 'a wedding anniversary', 'a healed mother']), 'Thanksgiving', $this->naira(20_000, 100_000), $this->pick(['cash', 'transfer']), 'usher');
            }
            if (mt_rand(1, 4) === 1) {
                $this->in($day, 'Books and CDs at the bookstand', 'Sales (books, media, merchandise)', $this->naira(8_000, 30_000), $this->pick(['cash', 'pos']), 'media');
            }
            $this->out($day->copy()->subDay(), 'Diesel for Sunday service', 'Venue & utilities', $this->naira(25_000, 42_000), 'office', 'For the generator.');
            if (mt_rand(1, 2) === 1) {
                $this->out($day, 'Refreshments for workers', 'Hospitality', $this->naira(12_000, 28_000), 'office');
            }
        }
        if ($day->isWednesday()) {
            $this->in($day, 'Midweek service offering', 'Midweek offering', $this->naira(15_000, 38_000), 'cash', 'usher');
        }
    }

    /**
     * Things that happen once a month.
     */
    private function monthly(Carbon $start): void
    {
        for ($m = 0; $m <= 3; $m++) {
            $month = $start->copy()->addMonths($m);
            $on = fn (int $day) => $month->copy()->addDays($day - 1);
            $this->in($on(3), 'Building fund pledges redeemed', 'Special seed & pledges', $this->naira(60_000, 220_000), 'transfer', 'office');
            $this->in($on(9), 'Hall hire for a wedding reception', 'Hall & equipment hire', $this->naira(50_000, 80_000), 'transfer', 'office');
            $this->in($on(12), 'Welfare contributions from units', 'Welfare contributions', $this->naira(35_000, 70_000), 'cash', 'office');
            $this->out($on(2), 'Electricity (prepaid meter)', 'Venue & utilities', $this->naira(45_000, 62_000), 'office');
            $this->out($on(5), 'Internet data for the live stream', 'Sound & media', 30_000_00, 'media', 'Monthly router subscription.');
            $this->out($on(8), 'Office supplies and printing', 'Admin & supplies', $this->naira(8_000, 24_000), 'office', 'Bulletins, paper and toner.');
            $this->out($on(15), 'Welfare support for a member family', 'Welfare support', $this->naira(40_000, 80_000), 'office', 'Approved by the welfare committee.');
            $this->out($on(18), 'Transport for the outreach team', 'Transport', $this->naira(10_000, 22_000), 'office');
            if ($m === 1) {
                $this->in($on(20), 'Donation from an alumni group', 'Donations', 250_000_00, 'transfer', 'office', 'For the youth sanctuary building.');
                $this->out($on(16), 'Replacement XLR cables and a mic stand', 'Sound & media', 38_500_00, 'media');
            }
            if ($m === 2) {
                $this->in($on(26), 'Youth conference registrations (at the door)', 'Event income', 365_000_00, 'pos', 'usher');
                $this->out($on(22), 'Youth conference: chairs, canopy and sound hire', 'Events', 240_000_00, 'office');
                $this->out($on(24), 'Campus outreach: tracts and water', 'Outreach & welfare', 62_000_00, 'media');
            }
        }
    }

    /**
     * A couple of extra entries early in this month, so it's never empty.
     */
    private function lastDays(Carbon $day): void
    {
        if ($day->gt(today())) {
            return;
        }
        $this->in($day, 'Online transfer: '.$this->pick(['seed for a new car', 'first salary', 'house dedication']), 'Thanksgiving', $this->naira(15_000, 50_000), 'transfer', 'office');
    }

    private function budgets(): void
    {
        $amounts = ['Venue & utilities' => 250_000, 'Sound & media' => 60_000, 'Outreach & welfare' => 80_000, 'Welfare support' => 80_000, 'Events' => 50_000,
            'Hospitality' => 60_000, 'Transport' => 25_000, 'Admin & supplies' => 25_000];
        foreach ([now()->startOfMonth()->subMonth(), now()->startOfMonth()] as $month) {
            foreach ($amounts as $category => $naira) {
                Budget::firstOrCreate(['category' => $category, 'month' => $month], ['amount' => $naira * 100]);
            }
        }
    }

    private function declined(): void
    {
        $day = now()->startOfMonth()->subMonth()->addDays(10);
        $this->in($day, 'Sunday offering (second count)', 'Sunday offering', 9_990_00, 'cash', 'usher', null, Income::DECLINED, 'Already recorded: this was a recount of the same offering.');
        $this->out($day, 'New office chair', 'Admin & supplies', 85_000_00, 'office', null, Expense::DECLINED, 'Not in this month\'s budget. Please bring it back next quarter.');
    }

    private function in(Carbon $on, string $title, string $category, int $kobo, string $method, string $by, ?string $note = null, ?string $status = null, ?string $reason = null): void
    {
        $status ??= $on->gt(today()->subDays(7)) ? Income::PENDING : Income::CONFIRMED;
        $income = new Income(['title' => $title, 'category' => $category, 'amount' => $kobo, 'received_on' => $on, 'method' => $method, 'description' => $note]);
        $income->forceFill([...$this->decision($on, $status, $reason), 'recorded_by' => $this->staff[$by]->id, 'created_at' => $on->copy()->setTime(14, 0), 'updated_at' => $on->copy()->setTime(14, 0)])->save();
    }

    private function out(Carbon $on, string $title, string $category, int $kobo, string $by, ?string $note = null, ?string $status = null, ?string $reason = null): void
    {
        if ($on->gt(today())) {
            return;
        }
        $status ??= $on->gt(today()->subDays(7)) ? Expense::PENDING : Expense::APPROVED;
        $expense = new Expense(['title' => $title, 'category' => $category, 'amount' => $kobo, 'spent_on' => $on, 'description' => $note]);
        $expense->forceFill([...$this->decision($on, $status, $reason), 'requested_by' => $this->staff[$by]->id, 'created_at' => $on->copy()->setTime(15, 0), 'updated_at' => $on->copy()->setTime(15, 0)])->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function decision(Carbon $on, string $status, ?string $reason): array
    {
        return $status === 'pending' ? ['status' => $status]
            : ['status' => $status, 'decided_by' => $this->staff['finance']->id, 'decided_at' => $on->copy()->addDays(2)->setTime(10, 0), 'decline_reason' => $reason];
    }

    /**
     * A round amount in naira between the two, as kobo.
     */
    private function naira(int $min, int $max): int
    {
        return intdiv(mt_rand($min, $max), 500) * 500 * 100;
    }

    private function pick(array $options): string
    {
        return $options[mt_rand(0, count($options) - 1)];
    }
}
