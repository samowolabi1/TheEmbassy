<?php

namespace Tests\Feature;

use App\Models\PageContent;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\User;
use App\Services\Content\LegacyContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PageContentTest extends TestCase
{
    use RefreshDatabase;

    private function aboutForm(array $overrides = []): array
    {
        return [
            'hero_title' => 'Raising royals.',
            'hero_text' => ':church is home.',
            'story_heading' => 'How it started',
            'story' => "We began in 2019.\n\nNow we are many.",
            'mission' => 'Know Christ.',
            'vision' => 'Lead with excellence.',
            'values' => "Worship\n\nFamily\n",
            'beliefs' => "One God.\nThe Bible.",
            'leaders' => [
                ['from' => 0, 'name' => 'Pastor Ade Bello', 'role' => 'Pastor in Charge', 'swatch' => 'navy'],
                ['from' => '', 'name' => '', 'role' => '', 'swatch' => 'ice'],
            ],
            'faqs' => [
                ['q' => 'What should I wear?', 'a' => 'Anything.'],
                ['q' => '', 'a' => ''],
            ],
            ...$overrides,
        ];
    }

    public function test_staff_edit_the_about_page(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->staff('secretary')->create());
        $this->get(route('admin.pages.edit', 'about'))->assertOk()->assertSee("[Pastor's name]");

        $this->put(route('admin.pages.update', 'about'), $this->aboutForm([
            'story_photo' => UploadedFile::fake()->image('tribe.jpg', 1800, 1200),
            'leaders' => [['from' => 0, 'name' => 'Pastor Ade Bello', 'role' => 'Pastor in Charge', 'swatch' => 'navy', 'photo' => UploadedFile::fake()->image('ade.jpg', 600, 800)]],
        ]))->assertRedirect(route('admin.pages.edit', 'about'));

        $about = PageContent::for('about');
        $this->assertSame(['Worship', 'Family'], $about['values']);
        $this->assertCount(1, $about['leaders']);
        $this->assertCount(1, $about['faqs']);
        Storage::disk('public')->assertExists([$about['story_photo'], $about['leaders'][0]['photo']]);

        $this->get('/about')
            ->assertSee('Raising royals.')
            ->assertSee(config('church.short_name').' is home.')
            ->assertSee('We began in 2019.')
            ->assertSee('Pastor Ade Bello')
            ->assertDontSee('[Name]')
            ->assertSee('What should I wear?')
            ->assertSee($about['story_photo']);
    }

    public function test_leader_photos_follow_their_row_and_are_cleaned_up(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->admin()->create());
        $this->put(route('admin.pages.update', 'about'), $this->aboutForm([
            'leaders' => [
                ['from' => 0, 'name' => 'A', 'role' => 'Pastor', 'photo' => UploadedFile::fake()->image('a.jpg', 300, 300)],
                ['from' => 1, 'name' => 'B', 'role' => 'Assistant'],
            ],
        ]));
        $photo = PageContent::for('about')['leaders'][0]['photo'];

        // Swap the order: A's photo moves with A.
        $this->put(route('admin.pages.update', 'about'), $this->aboutForm([
            'leaders' => [['from' => 1, 'name' => 'B', 'role' => 'Assistant'], ['from' => 0, 'name' => 'A', 'role' => 'Pastor']],
        ]));
        $leaders = PageContent::for('about')['leaders'];
        $this->assertSame(['B', 'A'], array_column($leaders, 'name'));
        $this->assertSame($photo, $leaders[1]['photo']);

        // Removing A deletes the photo.
        $this->put(route('admin.pages.update', 'about'), $this->aboutForm(['leaders' => [['from' => 0, 'name' => 'B', 'role' => 'Assistant']]]));
        Storage::disk('public')->assertMissing($photo);
    }

    public function test_about_page_is_validated(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->put(route('admin.pages.update', 'about'), $this->aboutForm([
            'hero_title' => '',
            'leaders' => [['name' => 'No role']],
            'faqs' => [['q' => 'No answer?']],
        ]))->assertSessionHasErrors(['hero_title', 'leaders.0.role', 'faqs.0.a']);
        $this->put(route('admin.pages.update', 'contact'), [])->assertNotFound();
    }

    public function test_home_testimony_is_editable(): void
    {
        $this->actingAs(User::factory()->staff('media')->create());

        $this->put(route('admin.pages.update', 'home'), ['quote' => 'God kept me through exams.', 'name' => 'Tola Martins', 'role' => 'Choir'])->assertRedirect();

        $this->assertSame('TM', PageContent::for('home')['testimony']['initials']);
        $this->get('/')->assertSee('God kept me through exams.')->assertSee('Tola Martins');
    }

    public function test_units_with_descriptions_replace_the_starter_list(): void
    {
        $this->get('/about')->assertSee('Lead the Tribe in worship');

        Unit::factory()->create(['name' => 'Sanctuary keepers', 'description' => 'Keep the house of God beautiful.']);

        $this->get('/about')->assertSee('Keep the house of God beautiful.')->assertDontSee('Lead the Tribe in worship');
    }

    public function test_leader_names_saved_in_old_settings_are_carried_over(): void
    {
        PageContent::query()->delete();
        Setting::putMany(['content.about.leaders.0.name' => 'Pastor Old Settings', 'content.home.testimony.name' => 'Old Member']);

        LegacyContent::import();

        $this->get('/about')->assertSee('Pastor Old Settings');
        $this->get('/')->assertSee('Old Member');
    }
}
