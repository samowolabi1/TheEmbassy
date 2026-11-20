<?php

namespace Tests\Feature;

use App\Http\Middleware\CombineBirthday;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BirthdayInputTest extends TestCase
{
    use RefreshDatabase;

    private function joinData(array $overrides = []): array
    {
        return [
            'name' => 'Ada Obi', 'email' => 'ada@example.com', 'phone' => '0803 000 0000', 'password' => 'a-long-password',
            'password_confirmation' => 'a-long-password', 'agree' => '1', ...$overrides,
        ];
    }

    public function test_joining_with_only_the_day_and_month(): void
    {
        Mail::fake();
        $this->get(route('join'))->assertSee('name="birthday_day"', false)->assertSee('For birthday wishes from the church. The year is optional.');

        $this->post(route('join.store'), $this->joinData(['birthday_day' => '15', 'birthday_month' => '11', 'birthday_year' => '']))
            ->assertSessionHasNoErrors();

        $member = Member::sole();
        $this->assertSame('1904-11-15', $member->birthday->toDateString());
        $this->assertFalse($member->birthdayYearKnown());
        $this->assertSame('15 November', $member->birthdayLabel());
    }

    public function test_a_member_adds_the_year_later_and_the_form_shows_it(): void
    {
        $member = Member::factory()->active()->create(['birthday' => '1904-02-29']);
        $this->actingAs($member->user)->get(route('account'))->assertSee('<option value="29" selected', false);

        $this->put(route('account.update'), ['phone' => '0803', 'birthday_day' => '29', 'birthday_month' => '2', 'birthday_year' => '1992'])
            ->assertSessionHasNoErrors();
        $this->assertSame('29 February 1992', $member->fresh()->birthdayLabel());
        $this->get(route('account'))->assertSee('value="1992"', false);
    }

    public function test_impossible_or_half_filled_birthdays_are_refused(): void
    {
        $this->assertNull(CombineBirthday::combine('', '', ''));
        $this->assertSame('not a date', CombineBirthday::combine('31', '2', ''));
        $this->assertSame('not a date', CombineBirthday::combine('12', '', ''));
        $this->assertSame('not a date', CombineBirthday::combine('12', '5', '1850'));
        $this->assertSame('1904-02-29', CombineBirthday::combine('29', '2', ''));

        $this->post(route('join.store'), $this->joinData(['birthday_day' => '31', 'birthday_month' => '2']))
            ->assertSessionHasErrors(['birthday' => 'Choose both the day and the month of the birthday (the year is optional), and check the date exists.']);
    }

    public function test_no_birthday_is_fine(): void
    {
        Mail::fake();
        $this->post(route('join.store'), $this->joinData(['birthday_day' => '', 'birthday_month' => '', 'birthday_year' => '']))->assertSessionHasNoErrors();
        $this->assertNull(Member::sole()->birthday);
    }
}
