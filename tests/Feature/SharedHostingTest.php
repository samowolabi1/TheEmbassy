<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Content\Images;
use App\Support\ServerCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SharedHostingTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploaded_pictures_are_served_without_the_storage_link(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('gallery/2026/11/photo.webp', 'RIFF-fake-webp');
        Storage::disk('public')->put('notes.php', '<?php echo 1;');
        Storage::disk('public')->put('page.html', '<script>alert(1)</script>');

        $this->get('/storage/gallery/2026/11/photo.webp')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get('/storage/notes.php')->assertNotFound();
        $this->get('/storage/page.html')->assertNotFound();
        $this->get('/storage/../.env')->assertNotFound();
        $this->get('/storage/missing.jpg')->assertNotFound();
    }

    public function test_private_files_are_not_served_from_storage_urls(): void
    {
        Storage::disk('local')->put('receipts/r.pdf', 'secret');
        $this->get('/storage/receipts/r.pdf')->assertNotFound();
    }

    public function test_thumbnail_names_follow_the_picture_format(): void
    {
        $this->assertSame('pages/2026/a-thumb.webp', Images::thumbPath('pages/2026/a.webp'));
        $this->assertSame('pages/2026/a-thumb.jpg', Images::thumbPath('pages/2026/a.jpg'));
    }

    public function test_the_server_check_reports_what_hosting_needs(): void
    {
        $results = collect(app(ServerCheck::class)->run());
        $this->assertTrue($results->contains(fn ($c) => $c['label'] === 'Cron job' && $c['status'] === 'fail'));
        ServerCheck::beat();
        $results = collect(app(ServerCheck::class)->run());
        $this->assertSame('ok', $results->firstWhere('label', 'Cron job')['status']);
        $this->assertSame('ok', $results->firstWhere('label', 'Extension: gd')['status']);

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.server'))->assertOk()->assertSee('Server check')->assertSee('Cron job');
        $this->actingAs(User::factory()->create(['role' => 'media']))->get(route('admin.server'))->assertForbidden();
        $this->artisan('app:check')->assertExitCode(0);
    }

    public function test_the_project_root_refuses_direct_access_to_its_files(): void
    {
        $htaccess = file_get_contents(base_path('.htaccess'));
        $this->assertStringContainsString('RewriteRule ^(.*)$ public/$1 [L]', $htaccess);
        $this->assertStringContainsString('Require all denied', $htaccess);
    }
}
