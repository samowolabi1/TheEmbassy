<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\SettingsController;
use App\Models\BirthdayCelebration;
use App\Models\CommunityMessage;
use App\Models\CommunityRoom;
use App\Models\Member;
use App\Models\Setting;
use App\Models\SocialPost;
use App\Models\Unit;
use App\Services\Celebrations\BirthdayCard;
use App\Services\Celebrations\Birthdays;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BirthdaysTest extends TestCase
{
    use RefreshDatabase;

    private Unit $media;

    private Member $hod;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->media = Unit::factory()->media()->create();
        $this->hod = Member::factory()->active()->create(['unit_id' => $this->media->id]);
        $this->media->update(['hod_member_id' => $this->hod->id]);
    }

    private function birthdayToday(array $attributes = []): Member
    {
        return Member::factory()->active()->create(['name' => 'Ada Obi', 'birthday' => today()->subYears(24), ...$attributes]);
    }

    public function test_the_card_is_a_designed_png_without_the_year(): void
    {
        $member = $this->birthdayToday();
        $png = app(BirthdayCard::class)->forMember($member);
        $size = getimagesizefromstring($png);
        $this->assertSame([1080, 1350, 'image/png'], [$size[0], $size[1], $size['mime']]);

        // The member can preview their own card.
        $this->actingAs($member->user)->get(route('account.birthday-card'))->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    public function test_birthdays_are_celebrated_once_in_the_community_inbox_and_account(): void
    {
        $ada = $this->birthdayToday();
        $notToday = Member::factory()->active()->create(['birthday' => today()->addDays(3)->subYears(30)]);
        $private = $this->birthdayToday(['name' => 'Quiet Person', 'celebrate_birthday' => false]);

        $this->artisan('birthdays:celebrate')->expectsOutput('Celebrated 1 birthdays.')->assertSuccessful();
        $this->artisan('birthdays:celebrate')->expectsOutput('Celebrated 0 birthdays.');

        $celebration = BirthdayCelebration::sole();
        $this->assertSame($ada->id, $celebration->member_id);
        Storage::disk('public')->assertExists($celebration->card_path);

        // A post in the general room, from the church, carrying the card.
        $post = CommunityMessage::sole();
        $this->assertSame(['birthday', CommunityRoom::firstWhere('slug', 'general')->id, $celebration->card_path], [$post->kind, $post->community_room_id, $post->image_path]);
        $this->assertStringContainsString('Ada Obi', $post->body);
        $this->assertFalse($post->canEdit($ada->user));

        // Their account shows the card and the wishes; the inbox has a birthday message.
        $this->actingAs($ada->user)->get(route('account'))->assertOk()->assertSee('Happy birthday, Ada!')->assertSee($celebration->cardUrl(), false);
        $this->get(route('account.inbox'))->assertSee('Happy birthday! 🎉');
        $this->actingAs($notToday->user)->get(route('account'))->assertDontSee('Happy birthday, ');

        // The community shows it as from the church; members who opted out aren't listed.
        $friend = Member::factory()->active()->create();
        $this->actingAs($friend->user)->get(route('community.room', $post->room))->assertSee('Birthday card for Ada Obi', false);
        $this->get(route('community.index'))->assertSee('Ada Obi')->assertDontSee('Quiet Person');
        $this->assertNull(BirthdayCelebration::where('member_id', $private->id)->first());
    }

    public function test_members_add_a_photo_and_can_opt_out(): void
    {
        $member = Member::factory()->active()->create(['birthday' => '1999-05-04']);
        $this->actingAs($member->user)->put(route('account.birthday'), [
            'photo' => UploadedFile::fake()->image('me.jpg', 900, 1200), 'celebrate_birthday' => '1',
        ])->assertSessionHasNoErrors();
        $member->refresh();
        Storage::disk('public')->assertExists($member->photo_path);
        $this->assertTrue($member->celebrate_birthday);

        $this->put(route('account.birthday'), ['remove_photo' => '1']);
        $member->refresh();
        $this->assertNull($member->photo_path);
        $this->assertFalse($member->celebrate_birthday);
    }

    public function test_the_media_unit_sets_the_template_and_makes_cards_and_posts(): void
    {
        $ada = $this->birthdayToday();
        $team = Member::factory()->active()->create(['unit_id' => $this->media->id]);

        $this->actingAs($team->user)->get(route('unit.birthdays.index', $this->media))->assertOk()->assertSee('Ada Obi')->assertSee('Your HOD changes the template.');
        $this->put(route('unit.birthdays.template', $this->media), ['design' => 'ivory', 'headline' => 'Hi', 'message' => 'x'])->assertForbidden();

        $this->actingAs($this->hod->user)->put(route('unit.birthdays.template', $this->media), [
            'design' => 'ivory', 'headline' => 'Happy Birthday!', 'message' => 'We love you.', 'verse' => 'Psalm 91:16',
            'background' => UploadedFile::fake()->image('bg.jpg', 1080, 1350),
        ])->assertSessionHasNoErrors();
        Setting::applyToConfig(); // as on the next request
        $this->assertSame('ivory', config('church.birthday.design'));
        $this->assertFalse((bool) config('church.birthday.community'));
        Storage::disk('public')->assertExists(config('church.birthday.background'));
        $this->get(route('unit.birthdays.preview', $this->media))->assertOk()->assertHeader('Content-Type', 'image/png');
        // The template isn't on the Settings page.
        $this->assertArrayNotHasKey('birthday', SettingsController::groups());

        // With community posts switched off, the card is still made but not posted.
        app(Birthdays::class)->run();
        $this->assertSame(1, BirthdayCelebration::count());
        $this->assertSame(0, CommunityMessage::count());

        // Photo for a member, a download, and a social post draft with the card.
        $this->actingAs($team->user)->post(route('unit.birthdays.photo', [$this->media, $ada]), ['photo' => UploadedFile::fake()->image('ada.jpg', 600, 800)])->assertSessionHasNoErrors();
        $this->assertNotNull($ada->fresh()->photo_path);
        $this->get(route('unit.birthdays.card', [$this->media, $ada, 'download' => 1]))->assertOk()->assertDownload('ada-obi-birthday.png');
        $this->post(route('unit.birthdays.social', [$this->media, $ada]));
        $post = SocialPost::sole();
        $this->assertSame(['draft', 'image'], [$post->status, $post->media_type]);
        $this->assertStringContainsString('Happy birthday, Ada Obi!', $post->caption);

        // Outsiders can't get in.
        $stranger = Member::factory()->active()->create();
        $this->actingAs($stranger->user)->get(route('unit.birthdays.index', $this->media))->assertForbidden();
    }
}
