<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Content\Images;
use App\Support\ServerCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SharedHostingTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploads_live_in_the_public_folder_where_nothing_can_run(): void
    {
        $this->assertSame(public_path('uploads'), config('filesystems.disks.public.root'));
        $this->assertStringEndsWith('/uploads', config('filesystems.disks.public.url'));
        $this->assertSame([], config('filesystems.links'));

        $rules = file_get_contents(public_path('uploads/.htaccess'));
        $this->assertStringContainsString('php_flag engine off', $rules);
        $this->assertStringContainsString('Require all denied', $rules);
        $this->assertStringContainsString('-Indexes', $rules);
        foreach (['php', 'phtml', 'phar', 'html?', 'js'] as $ext) {
            $this->assertStringContainsString($ext, $rules);
        }
    }

    public function test_old_storage_addresses_still_work(): void
    {
        Storage::fake('public', ['url' => config('filesystems.disks.public.url')]);
        Storage::disk('public')->put('gallery/2026/11/photo.webp', 'RIFF-fake-webp');
        Storage::disk('public')->put('notes.php', '<?php echo 1;');
        Storage::disk('public')->put('page.html', '<script>alert(1)</script>');

        $this->get('/storage/gallery/2026/11/photo.webp')->assertStatus(301)->assertRedirect(url('/uploads/gallery/2026/11/photo.webp'));
        $this->get('/storage/notes.php')->assertNotFound();
        $this->get('/storage/page.html')->assertNotFound();
        $this->get('/storage/../.env')->assertNotFound();
        $this->get('/storage/missing.jpg')->assertNotFound();
    }

    public function test_old_uploads_move_across_keeping_their_paths_and_dates(): void
    {
        $old = storage_path('app/public/move-test/2026/a.webp');
        $new = public_path('uploads/move-test/2026/a.webp');
        File::ensureDirectoryExists(dirname($old));
        file_put_contents($old, 'RIFF-old');
        touch($old, $when = now()->subDays(10)->getTimestamp());

        try {
            // Not moved yet: the old address still serves it.
            $this->get('/storage/move-test/2026/a.webp')->assertOk();
            $this->artisan('uploads:move')->assertExitCode(0);

            $this->assertFileExists($new);
            $this->assertFileDoesNotExist($old);
            $this->assertSame('RIFF-old', file_get_contents($new));
            $this->assertSame($when, filemtime($new));
            $this->get('/storage/move-test/2026/a.webp')->assertRedirect(url('/uploads/move-test/2026/a.webp'));
            $this->artisan('uploads:move')->assertExitCode(0);
        } finally {
            File::deleteDirectory(storage_path('app/public/move-test'));
            File::deleteDirectory(public_path('uploads/move-test'));
        }
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
