<?php

namespace Tests\Feature;

use App\Mail\WelfareDecision;
use App\Mail\WelfareNeedsAttention;
use App\Models\Expense;
use App\Models\Member;
use App\Models\Unit;
use App\Models\User;
use App\Models\WelfareCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class WelfareTest extends TestCase
{
    use RefreshDatabase;

    private Unit $unit;

    private Member $hod;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        config(['church.welfare.approval_limit' => 50000]);
        $this->unit = Unit::factory()->welfare()->create();
        $this->hod = Member::factory()->active()->create(['name' => 'Sis. Funke', 'unit_id' => $this->unit->id]);
        $this->unit->update(['hod_member_id' => $this->hod->id]);
    }

    private function request(Member $member, array $overrides = [])
    {
        return $this->actingAs($member->user)->post(route('account.welfare.store'), [
            'category' => 'medical', 'summary' => 'Hospital bill after surgery', 'details' => 'Surgery on Monday.', 'urgency' => 'urgent', 'amount_requested' => '120000', ...$overrides,
        ]);
    }

    public function test_a_member_asks_for_help_and_the_hod_is_told_without_details(): void
    {
        $ada = Member::factory()->active()->create();

        $this->request($ada)->assertSessionHas('status');

        $case = WelfareCase::sole();
        $this->assertSame('new', $case->status);
        $this->assertSame(12_000_000, $case->amount_requested);
        $this->assertStringStartsWith('WF-', $case->reference);
        Mail::assertSent(WelfareNeedsAttention::class, function ($mail) {
            return $mail->hasTo($this->hod->email) && ! str_contains($mail->render(), 'Surgery on Monday');
        });
        $this->get(route('account.welfare'))->assertSee('The welfare team is looking into it');
    }

    public function test_cases_are_private_to_the_welfare_team_and_pastors(): void
    {
        $ada = Member::factory()->active()->create();
        $this->request($ada);
        $case = WelfareCase::sole();
        $choirHod = Member::factory()->active()->create();
        $choir = Unit::factory()->create(['hod_member_id' => $choirHod->id]);

        $this->actingAs($this->hod->user)->get(route('unit.welfare.show', [$this->unit, $case]))->assertOk()->assertSee('Surgery on Monday');
        $this->actingAs(User::factory()->staff('pastor')->create())->get(route('unit.welfare.show', [$this->unit, $case]))->assertOk();

        $this->actingAs($choirHod->user)->get(route('unit.welfare.index', $this->unit))->assertForbidden();
        $this->actingAs($choirHod->user)->get(route('unit.welfare.index', $choir))->assertForbidden();
        $this->actingAs(User::factory()->staff('secretary')->create())->get(route('unit.welfare.show', [$this->unit, $case]))->assertForbidden();
        $this->actingAs(User::factory()->staff('finance')->create())->get(route('unit.welfare.index', $this->unit))->assertForbidden();
        $this->actingAs($ada->user)->get(route('unit.welfare.show', [$this->unit, $case]))->assertForbidden();
    }

    public function test_the_hod_approves_within_their_limit_and_payments_become_expenses(): void
    {
        $ada = Member::factory()->active()->create();
        $this->request($ada, ['amount_requested' => '30000']);
        $case = WelfareCase::sole();
        $this->actingAs($this->hod->user);

        $this->post(route('unit.welfare.act', [$this->unit, $case]), ['action' => 'approve', 'amount' => '30000'])->assertRedirect();
        $case->refresh();
        $this->assertSame('approved', $case->status);
        Mail::assertSent(WelfareDecision::class, fn ($mail) => $mail->hasTo($ada->email));

        $this->post(route('unit.welfare.pay', [$this->unit, $case]), ['amount' => '20000', 'method' => 'paid_direct', 'paid_on' => today()->toDateString(), 'reference' => 'Hospital receipt 4471'])->assertSessionHasNoErrors();
        $this->post(route('unit.welfare.pay', [$this->unit, $case]), ['amount' => '20000', 'method' => 'cash', 'paid_on' => today()->toDateString()])->assertSessionHasErrors('amount');

        $expense = Expense::sole();
        $this->assertSame(Expense::APPROVED, $expense->status);
        $this->assertSame(2_000_000, $expense->amount);
        $this->assertSame('Welfare support, case '.$case->reference, $expense->title);
        $this->assertStringNotContainsString($ada->name, $expense->title.$expense->description);
        $this->assertSame('supported', $case->fresh()->status);
    }

    public function test_amounts_above_the_limit_go_to_a_pastor(): void
    {
        $ada = Member::factory()->active()->create();
        $this->request($ada);
        $case = WelfareCase::sole();
        $pastor = User::factory()->staff('pastor')->create();

        $this->actingAs($this->hod->user)->post(route('unit.welfare.act', [$this->unit, $case]), ['action' => 'approve', 'amount' => '120000'])
            ->assertSessionHas('status', 'Above your limit, so it\'s gone to a pastor to approve.');
        $this->assertSame('awaiting_pastor', $case->fresh()->status);
        Mail::assertSent(WelfareNeedsAttention::class, fn ($mail) => $mail->hasTo($pastor->email));

        // The HOD can't record payments yet, or approve it themselves again.
        $this->post(route('unit.welfare.pay', [$this->unit, $case]), ['amount' => '1000', 'method' => 'cash', 'paid_on' => today()->toDateString()])->assertSessionHasErrors('status');
        $this->post(route('unit.welfare.act', [$this->unit, $case]), ['action' => 'approve', 'amount' => '120000']);
        $this->assertSame('awaiting_pastor', $case->fresh()->status);

        $this->actingAs($pastor)->get('/admin')->assertSee('Welfare');
        $this->post(route('unit.welfare.act', [$this->unit, $case]), ['action' => 'approve', 'amount' => '100000', 'note' => 'Church covers most of it'])->assertRedirect();
        $case->refresh();
        $this->assertSame('approved', $case->status);
        $this->assertSame(10_000_000, $case->amount_approved);
        $this->assertSame($pastor->id, $case->decided_by);
    }

    public function test_declining_needs_a_reason_and_tells_the_member(): void
    {
        $ada = Member::factory()->active()->create();
        $this->request($ada);
        $case = WelfareCase::sole();
        $this->actingAs($this->hod->user);

        $this->post(route('unit.welfare.act', [$this->unit, $case]), ['action' => 'decline'])->assertSessionHasErrors('reason');
        $this->post(route('unit.welfare.act', [$this->unit, $case]), ['action' => 'decline', 'reason' => 'Already supported this month by the parish'])->assertRedirect();
        $this->assertSame('declined', $case->fresh()->status);
        Mail::assertSent(WelfareDecision::class);
        $this->actingAs($ada->user)->get(route('account.welfare'))->assertSee('We couldn&#039;t help this time', false);
    }

    public function test_the_hod_opens_cases_for_outsiders_and_assigns_a_case_worker(): void
    {
        $worker = Member::factory()->active()->create(['name' => 'Bro. Kunle', 'unit_id' => $this->unit->id]);
        $outsider = Member::factory()->active()->create();
        $this->actingAs($this->hod->user);

        $this->post(route('unit.welfare.store', $this->unit), ['beneficiary_name' => 'Mama Grace', 'beneficiary_phone' => '0803 777 0000', 'category' => 'food', 'summary' => 'Widow, needs foodstuff', 'urgency' => 'normal'])->assertRedirect();
        $case = WelfareCase::sole();
        $this->assertSame('leader', $case->source);

        $this->put(route('unit.welfare.update', [$this->unit, $case]), ['assigned_member_id' => $outsider->id, 'urgency' => 'normal'])->assertSessionHasErrors('assigned_member_id');
        $this->put(route('unit.welfare.update', [$this->unit, $case]), ['assigned_member_id' => $worker->id, 'urgency' => 'normal', 'next_check_on' => today()->addWeek()->toDateString()]);

        // The worker sees it from their account and logs a visit; others can't open it.
        $this->actingAs($worker->user)->get(route('account.welfare'))->assertSee('Mama Grace');
        $this->get(route('account.welfare.case', $case))->assertOk()->assertSee('0803 777 0000');
        $this->post(route('account.welfare.note', $case), ['kind' => 'visit', 'body' => 'Took rice and oil', 'happened_on' => today()->toDateString()])->assertSessionHas('status');
        $this->actingAs($outsider->user)->get(route('account.welfare.case', $case))->assertNotFound();

        $this->actingAs($this->hod->user)->get(route('unit.welfare.show', [$this->unit, $case]))->assertSee('Took rice and oil')->assertSee('Assigned to Bro. Kunle');
    }

    public function test_cases_due_a_check_in_are_listed(): void
    {
        $case = WelfareCase::create(['beneficiary_name' => 'A', 'category' => 'bereavement', 'summary' => 'Lost her father', 'urgency' => 'normal', 'next_check_on' => today()]);

        $this->actingAs($this->hod->user)->get(route('unit.welfare.index', [$this->unit, 'tab' => 'check']))->assertSee('Lost her father');
    }

    public function test_export_leaves_out_names(): void
    {
        $ada = Member::factory()->active()->create(['name' => 'Ada Secret']);
        $this->request($ada);

        $csv = $this->actingAs($this->hod->user)->get(route('unit.welfare.export', $this->unit))->streamedContent();
        $this->assertStringContainsString('Medical or hospital', $csv);
        $this->assertStringNotContainsString('Ada Secret', $csv);
        $this->assertStringNotContainsString('Surgery', $csv);
    }

    public function test_celebrations_list_birthdays_and_anniversaries(): void
    {
        Member::factory()->active()->create(['name' => 'Birthday Person', 'birthday' => today()->subYears(25)->addDays(3), 'phone' => '08030000000']);
        Member::factory()->active()->create(['name' => 'Married Couple', 'anniversary' => today()->subYears(10)->addDays(5)]);
        Member::factory()->active()->create(['name' => 'Not Soon', 'birthday' => today()->subYears(30)->addDays(60)]);

        $this->actingAs($this->hod->user)->get(route('unit.welfare.celebrations', $this->unit))
            ->assertSee('Birthday Person')->assertSee('Married Couple')->assertSee('10 years')->assertDontSee('Not Soon')->assertSee('wa.me/2348030000000', false);
    }

    public function test_pending_members_cannot_request_yet(): void
    {
        $pending = Member::factory()->create();

        $this->request($pending)->assertForbidden();
    }
}
