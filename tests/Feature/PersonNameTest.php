<?php

namespace Tests\Feature;

use App\Models\FirstTimer;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PersonNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_joining_asks_for_first_and_last_name_and_gender(): void
    {
        Mail::fake();
        $this->get(route('join'))->assertSee('name="first_name"', false)->assertSee('name="last_name"', false)
            ->assertSee('<select name="gender" required>', false)->assertDontSee('Prefer not to say');

        $base = ['email' => 'ada@example.com', 'phone' => '0803 000 0000', 'password' => 'a-long-password', 'password_confirmation' => 'a-long-password', 'agree' => '1'];
        $this->post(route('join.store'), [...$base, 'first_name' => 'Ada'])->assertSessionHasErrors(['last_name', 'gender']);

        $this->post(route('join.store'), [...$base, 'first_name' => ' Ada ', 'last_name' => 'Obi', 'gender' => 'female'])->assertSessionHasNoErrors();
        $member = Member::sole();
        $this->assertSame(['Ada', 'Obi', 'Ada Obi', 'female'], [$member->first_name, $member->last_name, $member->name, $member->gender]);
    }

    public function test_the_office_edits_a_name_and_the_full_name_follows(): void
    {
        $member = Member::factory()->active()->create(['name' => 'Sis. Adaeze Obi', 'gender' => 'female']);
        $this->assertSame(['Sis. Adaeze', 'Obi'], [$member->first_name, $member->last_name]);
        $this->assertSame('Adaeze', $member->firstName());

        $office = User::factory()->create(['role' => 'secretary']);
        $this->actingAs($office)->get(route('admin.members.edit', $member))->assertSee('value="Sis. Adaeze"', false);
        $this->put(route('admin.members.update', $member), ['first_name' => 'Adaeze', 'last_name' => 'Okafor', 'gender' => 'female', 'status' => 'active'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Adaeze Okafor', $member->fresh()->name);

        $this->put(route('admin.members.update', $member), ['first_name' => 'Adaeze', 'last_name' => 'Okafor', 'gender' => '', 'status' => 'active'])
            ->assertSessionHasErrors('gender');
    }

    public function test_first_timer_cards_carry_names_and_gender_onto_the_register(): void
    {
        $this->post(route('welcome.store'), ['first_name' => 'Bisi', 'last_name' => 'Eze', 'gender' => 'female', 'phone' => '0803 555 0000'])->assertSessionHasNoErrors();
        $card = FirstTimer::sole();
        $this->assertSame(['Bisi Eze', 'female'], [$card->name, $card->gender]);

        $office = User::factory()->create(['role' => 'secretary']);
        $this->actingAs($office)->post(route('admin.first-timers.register', $card));
        $this->assertSame(['Bisi', 'Eze', 'female'], [Member::sole()->first_name, Member::sole()->last_name, Member::sole()->gender]);
    }

    public function test_a_member_list_can_have_first_and_last_name_columns(): void
    {
        $office = User::factory()->create(['role' => 'secretary']);
        $csv = UploadedFile::fake()->createWithContent('members.csv', "first_name,last_name,phone\nTunde,Bello,0809 111 2222\n");
        $this->actingAs($office)->post(route('admin.members.import'), ['file' => $csv]);
        $this->assertSame(['Tunde Bello', 'Bello'], [Member::sole()->name, Member::sole()->last_name]);
    }
}
