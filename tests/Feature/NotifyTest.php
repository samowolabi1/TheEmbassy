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
            ->assertSee('laravel-notify/dist/notify.js', false);

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
}
