<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\SettingsController;
use App\Models\BirthdayCelebration;
use App\Models\BirthdayDesign;
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

    public function test_every_design_draws_a_card(): void
    {
        $cards = app(BirthdayCard::class);
        foreach (array_keys(BirthdayCard::DESIGNS) as $design) {
            $size = getimagesizefromstring($cards->render('Sis. Adaeze Okonkwo-Bello', today(), null, $design));
            $this->assertSame([1080, 1350], [$size[0], $size[1]], $design);
        }
        // Old design names still work.
        $this->assertNotEmpty($cards->render('Ada', today(), null, 'ivory'));
    }

    public function test_the_media_unit_chooses_designs_and_words_and_makes_cards_and_posts(): void
    {
        $ada = $this->birthdayToday();
        $team = Member::factory()->active()->create(['unit_id' => $this->media->id]);

        $this->actingAs($team->user)->get(route('unit.birthdays.index', $this->media))->assertOk()->assertSee('Ada Obi')->assertSee('Your HOD chooses the designs and words.');
        $this->put(route('unit.birthdays.template', $this->media), ['designs' => ['neon'], 'headline' => 'Hi', 'message' => 'x'])->assertForbidden();

        $this->actingAs($this->hod->user)->put(route('unit.birthdays.template', $this->media), ['designs' => [], 'headline' => 'Hi', 'message' => 'x'])->assertSessionHasErrors('designs');
        $this->put(route('unit.birthdays.template', $this->media), [
            'designs' => ['neon', 'balloons'], 'headline' => 'Happy Birthday!', 'message' => 'We love you.', 'verse' => 'Psalm 91:16',
        ])->assertSessionHasNoErrors();
        Setting::applyToConfig(); // as on the next request
        $cards = app(BirthdayCard::class);
        $this->assertSame(['neon', 'balloons'], $cards->enabled());
        $this->assertContains($cards->designFor($ada), ['neon', 'balloons']);
        $this->assertFalse((bool) config('church.birthday.community'));
        $this->get(route('unit.birthdays.preview', [$this->media, 'design' => 'gold']))->assertOk()->assertHeader('Content-Type', 'image/png');
        // The choices aren't on the Settings page.
        $this->assertArrayNotHasKey('birthday', SettingsController::groups());

        // With community posts switched off, the card is still made but not posted.
        app(Birthdays::class)->run();
        $this->assertSame(1, BirthdayCelebration::count());
        $this->assertSame(0, CommunityMessage::count());

        // Photo for a member, a download in any design, and a social post draft with the card.
        $this->actingAs($team->user)->post(route('unit.birthdays.photo', [$this->media, $ada]), ['photo' => UploadedFile::fake()->image('ada.jpg', 600, 800)])->assertSessionHasNoErrors();
        $this->assertNotNull($ada->fresh()->photo_path);
        $this->get(route('unit.birthdays.card', [$this->media, $ada, 'download' => 1, 'design' => 'pastel']))->assertOk()->assertDownload('ada-obi-birthday.png');
        $this->post(route('unit.birthdays.social', [$this->media, $ada]));
        $post = SocialPost::sole();
        $this->assertSame(['draft', 'image'], [$post->status, $post->media_type]);
        $this->assertStringContainsString('Happy birthday, Ada Obi!', $post->caption);

        // Outsiders can't get in.
        $stranger = Member::factory()->active()->create();
        $this->actingAs($stranger->user)->get(route('unit.birthdays.index', $this->media))->assertForbidden();
    }

    public function test_the_media_unit_uploads_its_own_design_and_places_the_photo_and_name(): void
    {
        $this->actingAs($this->hod->user)->post(route('unit.birthdays.designs.store', $this->media), [
            'name' => 'Christmas birthdays', 'image' => UploadedFile::fake()->image('xmas.png', 1080, 1350),
        ])->assertRedirect();
        $design = BirthdayDesign::sole();
        Storage::disk('public')->assertExists($design->background_path);
        Setting::applyToConfig();
        $this->assertContains($design->key(), app(BirthdayCard::class)->enabled());

        // The editor, its live preview with unsaved placement, and saving.
        $this->get(route('unit.birthdays.designs.edit', [$this->media, $design]))->assertOk()->assertSee('Place the photo and name');
        $this->get(route('unit.birthdays.preview', [$this->media, 'design' => $design->key(), 'layout' => ['photo_y' => 30, 'name_font' => 'fun', 'photo_border_on' => '0']]))
            ->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->put(route('unit.birthdays.designs.update', [$this->media, $design]), [
            'name' => 'Christmas', 'layout' => ['photo_shape' => 'rounded', 'photo_x' => 50, 'photo_y' => 140, 'photo_size' => 40, 'name_font' => 'nope',
                'name_color' => '#ff0000', 'name_y' => 75, 'name_size' => 90, 'show_date' => '1'],
        ])->assertRedirect(route('unit.birthdays.index', $this->media));
        $layout = $design->fresh()->layout();
        $this->assertEquals(['rounded', 95, 'poppins', '#ff0000', true, false, null], [$layout['photo_shape'], $layout['photo_y'], $layout['name_font'], $layout['name_color'], $layout['show_date'], $layout['name_upper'], $layout['photo_border']]);
        $ada = $this->birthdayToday();
        $png = app(BirthdayCard::class)->forMember($ada, $design->key());
        $this->assertSame(1080, getimagesizefromstring($png)[0]);

        // Team members can't upload; removing a design takes it out of use.
        $team = Member::factory()->active()->create(['unit_id' => $this->media->id]);
        $this->actingAs($team->user)->post(route('unit.birthdays.designs.store', $this->media), ['name' => 'X', 'image' => UploadedFile::fake()->image('x.png')])->assertForbidden();
        $this->actingAs($this->hod->user)->delete(route('unit.birthdays.designs.destroy', [$this->media, $design]));
        $this->assertModelMissing($design);
        Storage::disk('public')->assertMissing($design->background_path);
        Setting::applyToConfig();
        $this->assertNotContains($design->key(), app(BirthdayCard::class)->enabled());
    }
}
