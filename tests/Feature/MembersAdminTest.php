<?php

namespace Tests\Feature;

use App\Models\Fellowship;
use App\Models\Member;
use App\Models\TokenPlan;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MembersAdminTest extends TestCase
{
    use RefreshDatabase;

    private function office(): User
    {
        return User::factory()->staff('secretary')->create();
    }

    public function test_register_lists_members_with_tabs_search_and_unit_filter(): void
    {
        $choir = Unit::create(['name' => 'Choir']);
        Member::factory()->active()->create(['name' => 'Tolu Adeyemi', 'unit_id' => $choir->id]);
        Member::factory()->active()->create(['name' => 'Jide Kolawole']);
        Member::factory()->create(['name' => 'Funmi Nwosu']); // pending

        $this->actingAs($this->office());
        $this->get(route('admin.members.index'))->assertOk()->assertSee('Tolu Adeyemi')->assertSee('Jide Kolawole')->assertDontSee('Funmi Nwosu');
        $this->get(route('admin.members.index', ['tab' => 'pending']))->assertSee('Funmi Nwosu')->assertSee('Approve');
        $this->get(route('admin.members.index', ['q' => 'tolu']))->assertSee('Tolu Adeyemi')->assertDontSee('Jide Kolawole');
        $this->get(route('admin.members.index', ['unit' => $choir->id]))->assertSee('Tolu Adeyemi')->assertDontSee('Jide Kolawole');
    }

    public function test_approving_starts_tokens_on_the_default_plan(): void
    {
        $standard = TokenPlan::create(['name' => 'Standard', 'amount' => 200_000, 'is_default' => true]);
        $member = Member::factory()->create();

        $this->actingAs($this->office())->post(route('admin.members.approve', $member))->assertRedirect();

        $member->refresh();
        $this->assertSame(Member::ACTIVE, $member->status);
        $this->assertSame($standard->id, $member->token_plan_id);
        $this->assertTrue($member->tokens_from->isSameDay(today()->startOfMonth()));
        $this->assertSame(1, $member->tokenMonthsOwed());
    }

    public function test_staff_add_and_edit_members_with_milestones(): void
    {
        $house = Fellowship::create(['name' => 'House 4']);
        $this->actingAs($this->office());

        $this->post(route('admin.members.store'), [
            'name' => 'David Musa', 'phone' => '0803 111 2222', 'fellowship_id' => $house->id, 'baptised' => '1',
        ])->assertRedirect();

        $member = Member::sole();
        $this->assertSame(Member::ACTIVE, $member->status);
        $this->assertNull($member->user_id);
        $this->assertTrue($member->baptised);
        $this->assertFalse($member->workers_training);

        $this->get(route('admin.members.show', $member))->assertOk()->assertSee('House 4')->assertSee('Baptised');

        $this->put(route('admin.members.update', $member), ['name' => 'David Musa', 'status' => 'inactive', 'workers_training' => '1'])->assertRedirect();
        $member->refresh();
        $this->assertSame(Member::INACTIVE, $member->status);
        $this->assertTrue($member->workers_training);
        $this->assertFalse($member->baptised);
    }

    public function test_import_adds_members_and_skips_duplicates_and_bad_rows(): void
    {
        Unit::create(['name' => 'Media & tech']);
        Member::factory()->active()->create(['email' => 'ada@example.com']);
        $csv = "Name,Phone,Email,Unit,Birthday\n"
            ."Bisi Eze,0803 000 1111,bisi@example.com,media & tech,14/02/1999\n"
            ."Ada Again,,ada@example.com,,\n"
            .",0803,,,\n"
            ."Chidi Okeke,0803 000 2222,not-an-email,,\n"
            ."Kunle Ade,0803 000 3333,,Unknown unit,\n";

        $this->actingAs($this->office())->post(route('admin.members.import'), [
            'file' => UploadedFile::fake()->createWithContent('members.csv', $csv),
        ])->assertRedirect(route('admin.members.index'))->assertSessionHas('status', 'Imported 2 members. Skipped 3 (no name, a bad email, or already on the register).');

        $bisi = Member::firstWhere('name', 'Bisi Eze');
        $this->assertSame('Media & tech', $bisi->unit->name);
        $this->assertSame('1999-02-14', $bisi->birthday->toDateString());
        $this->assertNull(Member::firstWhere('name', 'Kunle Ade')->unit_id);
    }

    public function test_import_needs_a_name_heading(): void
    {
        $this->actingAs($this->office())->post(route('admin.members.import'), [
            'file' => UploadedFile::fake()->createWithContent('members.csv', "full,phone\nA,1\n"),
        ])->assertSessionHasErrors('file');
    }

    public function test_export_is_spreadsheet_safe(): void
    {
        Member::factory()->active()->create(['name' => '=HYPERLINK("http://evil")']);

        $response = $this->actingAs($this->office())->get(route('admin.members.export'));
        $this->assertStringContainsString("'=HYPERLINK", $response->streamedContent());
    }

    public function test_units_and_fellowships_are_managed(): void
    {
        $this->actingAs($this->office());

        $this->post(route('admin.units.store'), ['name' => 'Choir', 'leader_name' => 'Bro. Femi'])->assertRedirect();
        $this->post(route('admin.units.store'), ['name' => 'Choir'])->assertSessionHasErrors('name', null, 'unit');
        $this->post(route('admin.fellowships.store'), ['name' => 'House 1', 'area' => 'Yaba'])->assertRedirect();

        $unit = Unit::sole();
        $member = Member::factory()->active()->create(['unit_id' => $unit->id]);
        $this->get(route('admin.units.index'))->assertSee('Choir')->assertSee('1 member')->assertSee('House 1');

        $this->delete(route('admin.units.destroy', $unit))->assertRedirect();
        $this->assertNull($member->fresh()->unit_id);
    }

    public function test_finance_staff_cannot_open_the_register(): void
    {
        $this->actingAs(User::factory()->staff('finance')->create())->get(route('admin.members.index'))->assertForbidden();
    }
}
