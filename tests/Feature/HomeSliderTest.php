<?php

namespace Tests\Feature;

use App\Models\PageContent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HomeSliderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->admin = User::factory()->create(['role' => 'secretary']);
    }

    /**
     * The form as it is now, with changes.
     *
     * @param  array<int, array<string, mixed>>  $changes
     * @return array<string, mixed>
     */
    private function form(array $changes = [], array $extra = []): array
    {
        $slides = collect(PageContent::for('home')['slides'])->map(fn ($s, $i) => [...collect($s)->except('photo')->all(), 'from' => $i, 'order' => $i + 1, 'hidden' => $s['hidden'] ? '1' : null])->all();
        foreach ($changes as $i => $c) {
            $slides[$i] = [...($slides[$i] ?? ['from' => null, 'order' => $i + 1]), ...$c];
        }
        $t = PageContent::for('home')['testimony'];

        return ['slides' => $slides, 'quote' => $t['quote'], 'name' => $t['name'], ...$extra];
    }

    public function test_the_default_slides_show_until_changed(): void
    {
        $this->get(route('home'))->assertOk()
            ->assertSee('Young. Royal.')->assertSee('Royal Youth')->assertSee('I am His.')
            ->assertSee('/ 04', false)->assertSee('1 PETER 2:9');
    }

    public function test_staff_change_words_add_a_slide_with_a_picture_and_reorder(): void
    {
        $this->actingAs($this->admin)->get(route('admin.pages.edit', 'home'))->assertOk()->assertSee('Slider')->assertSee('+ Add a slide');

        $this->put(route('admin.pages.update', 'home'), $this->form([
            0 => ['title' => "Come home.\nYou belong.", 'accent' => 'Sundays at 9', 'photo' => UploadedFile::fake()->image('worship.jpg', 1600, 1000), 'photo_alt' => 'Worship'],
            4 => ['title' => 'Back to school', 'accent' => 'Campus night', 'kicker' => 'FRIDAY', 'primary_label' => 'Register', 'primary_link' => 'https://example.com/register', 'order' => 0, 'feature' => 'date', 'feature_text' => '21 NOV'],
        ]))->assertSessionHasNoErrors();

        $slides = PageContent::for('home')['slides'];
        $this->assertCount(5, $slides);
        $this->assertSame(['Back to school', "Come home.\nYou belong."], [$slides[0]['title'], $slides[1]['title']]);
        Storage::disk('public')->assertExists($slides[1]['photo']);

        $page = $this->get(route('home'))->assertOk();
        $page->assertSee('Come home.<br />', false)->assertSee('Sundays at 9')->assertSee('/ 05', false)
            ->assertSee('https://example.com/register', false)->assertSee(PageContent::photoUrl($slides[1]['photo']), false)
            ->assertDontSee('Young. Royal.');
        // The first slide is the page's main heading.
        $this->assertStringContainsString('<h1 class="display slide__title">Back to school', $page->getContent());
    }

    public function test_hidden_and_past_slides_stay_off_and_bad_links_are_refused(): void
    {
        $this->actingAs($this->admin)->put(route('admin.pages.update', 'home'), $this->form([
            1 => ['hidden' => '1'],
            3 => ['show_until' => now()->subDay()->toDateString()],
        ]))->assertSessionHasNoErrors();
        $this->get(route('home'))->assertDontSee('<span class="accent">Convention</span>', false)->assertDontSee('I am His.')->assertSee('/ 02', false);

        $this->put(route('admin.pages.update', 'home'), $this->form([0 => ['primary_link' => 'javascript:alert(1)']]))->assertSessionHasErrors('slides.0.primary_link');
        $this->put(route('admin.pages.update', 'home'), $this->form([0 => ['primary_label' => 'Go', 'primary_link' => '']]))->assertSessionHasErrors('slides.0.primary_link');

        // Clearing a title removes the slide, and its picture goes too.
        $this->put(route('admin.pages.update', 'home'), $this->form([0 => ['photo' => UploadedFile::fake()->image('a.jpg', 1200, 800)]]));
        $photo = PageContent::for('home')['slides'][0]['photo'];
        $this->put(route('admin.pages.update', 'home'), $this->form([0 => ['title' => '', 'accent' => '']]));
        Storage::disk('public')->assertMissing($photo);
        $this->assertSame('Royal Youth', PageContent::for('home')['slides'][0]['title']);
    }

    public function test_one_slide_left_has_no_controls_and_staff_without_content_cannot_edit(): void
    {
        $this->actingAs($this->admin)->put(route('admin.pages.update', 'home'), $this->form([1 => ['title' => '', 'accent' => ''], 2 => ['feature' => 'none', 'title' => ''], 3 => ['title' => '', 'accent' => '']]))
            ->assertSessionHasNoErrors();
        $this->get(route('home'))->assertSee('Young. Royal.')->assertDontSee('data-dot', false);

        $this->actingAs(User::factory()->create(['role' => 'finance']))->get(route('admin.pages.edit', 'home'))->assertForbidden();
    }
}
