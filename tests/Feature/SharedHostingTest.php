<?php

namespace Tests\Feature;

use App\Models\Post;
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

    public function test_older_copies_have_their_pictures_moved_into_public_uploads(): void
    {
        $old = storage_path('app/public/move-test/2026/a.webp');
        $new = public_path('uploads/move-test/2026/a.webp');
        File::ensureDirectoryExists(dirname($old));
        file_put_contents($old, 'RIFF-old');
        touch($old, $when = now()->subDays(10)->getTimestamp());
        $post = Post::factory()->create(['body' => 'Look ![choir](/storage/blog/inline/2026/10/choir.webp)']);

        try {
            (require database_path('migrations/2026_12_12_090000_move_uploads_to_public.php'))->up();

            $this->assertFileExists($new);
            $this->assertFileDoesNotExist($old);
            $this->assertSame($when, filemtime($new));
            $this->assertSame('Look ![choir](/uploads/blog/inline/2026/10/choir.webp)', $post->fresh()->body);
        } finally {
            File::deleteDirectory(storage_path('app/public/move-test'));
            File::deleteDirectory(public_path('uploads/move-test'));
        }
    }

    public function test_private_files_are_never_served(): void
    {
        Storage::disk('local')->put('receipts/r.pdf', 'secret');
        $this->get('/storage/receipts/r.pdf')->assertNotFound();
        $this->get('/uploads/receipts/r.pdf')->assertNotFound();
        $this->assertStringStartsWith(storage_path(), Storage::disk('local')->path('receipts/r.pdf'));
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
