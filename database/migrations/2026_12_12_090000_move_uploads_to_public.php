<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * One-off for copies made before uploads moved to public/uploads: brings the
 * pictures across from storage/app/public (keeping their folders and dates),
 * fixes picture links inside blog articles, and removes the old public/storage
 * shortcut. On a fresh install there's nothing to move and it does nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        $from = storage_path('app/public');
        $to = public_path('uploads');

        if (is_dir($from)) {
            foreach (File::allFiles($from) as $file) {
                $target = $to.DIRECTORY_SEPARATOR.$file->getRelativePathname();
                if ($file->getFilename() === '.gitignore' || is_file($target)) {
                    continue;
                }
                File::ensureDirectoryExists(dirname($target));
                if (! @rename($file->getPathname(), $target)) {
                    $time = $file->getMTime();
                    File::copy($file->getPathname(), $target);
                    @touch($target, $time);
                }
            }
        }

        if (is_link(public_path('storage'))) {
            @unlink(public_path('storage'));
        }

        foreach (DB::table('posts')->where('body', 'like', '%/storage/blog/%')->get(['id', 'body']) as $post) {
            DB::table('posts')->where('id', $post->id)->update(['body' => str_replace('/storage/blog/', '/uploads/blog/', $post->body)]);
        }
    }

    public function down(): void
    {
        // The pictures stay in public/uploads.
    }
};
