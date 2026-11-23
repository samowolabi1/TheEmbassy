<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class NotifyTest extends TestCase
{
    use RefreshDatabase;

    public function test_saved_messages_pop_up_as_a_toast_once(): void
    {
        $admin = User::factory()->create(['role' => 'super']);

        $page = $this->actingAs($admin)->followingRedirects()->post('/admin/announcements', [
            'title' => 'Fasting week', 'body' => 'Starts Monday.', 'tag' => 'Prayer', 'tone' => 'wash', 'when' => 'draft',
        ]);
        $page->assertOk()->assertSee('class="notify"', false)->assertSee('border-green-500', false)
            ->assertSee('notify/notify.js', false);

        // Shown on that page only, not again on the next one.
        $this->get(route('admin.dashboard'))->assertDontSee('notify.js');
    }

    public function test_form_errors_pop_up_with_the_first_problem(): void
    {
        $this->from(route('login'))->post(route('login.store'), ['email' => '', 'password' => ''])
            ->assertRedirect(route('login'));

        $this->get(route('login'))->assertSee('Please check the form')->assertSee('border-red-500', false);
    }

    public function test_pages_without_a_message_load_no_toast_files(): void
    {
        $this->get('/')->assertOk()->assertDontSee('class="notify"', false)->assertDontSee('notify.js');
    }

    public function test_notify_can_be_called_directly(): void
    {
        notify()->info()->title('Heads up')->message('Service starts at 9.')->send();

        $this->get('/')->assertSee('Heads up')->assertSee('Service starts at 9.');
    }

    public function test_no_page_uses_the_browser_s_own_confirm_or_prompt_boxes(): void
    {
        $files = collect([...File::allFiles(resource_path('views')), ...File::allFiles(resource_path('js'))])
            ->reject(fn ($f) => $f->getFilename() === 'dialog.js');
        $offenders = $files->filter(fn ($f) => preg_match('/(^|[^.\w])(window\.)?(confirm|prompt|alert)\(/m', preg_replace('#//.*$#m', '', $f->getContents())));

        $this->assertSame([], $offenders->map(fn ($f) => $f->getFilename())->values()->all(), 'Use data-confirm or ask() from resources/js/dialog.js instead.');
    }

    public function test_errors_in_a_named_form_bag_pop_up_too(): void
    {
        $office = User::factory()->create(['role' => 'secretary']);
        $this->actingAs($office)->from(route('admin.register.index'))->followingRedirects()
            ->post(route('admin.register.member'), ['_form' => 'member', 'phone' => '0803'])
            ->assertSee('Please check the form')->assertSee('border-red-500', false);
    }

    public function test_no_folder_in_public_hides_a_page_with_the_same_address(): void
    {
        // The web server serves a real folder before Laravel sees the request, so public/vendor
        // would hide the seller area at /vendor.
        $firstSegments = collect(app('router')->getRoutes()->getRoutes())
            ->map(fn ($r) => explode('/', trim($r->uri(), '/'))[0])->filter()->unique();
        // storage/ is the one meant to overlap: the route only serves files when the folder link can't be made.
        $folders = collect(File::directories(public_path()))->map(fn ($d) => basename($d))->reject(fn ($d) => $d === 'storage');

        $this->assertSame([], $folders->intersect($firstSegments)->values()->all());
    }
}
