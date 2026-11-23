<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Uploaded pictures used to live in storage/app/public, shown through a
 * public/storage shortcut. They now live in public/uploads. This moves the
 * old ones across: run by a migration on update, and by `uploads:move`.
 */
class Uploads
{
    public static function oldFolder(): string
    {
        return storage_path('app/public');
    }

    public static function folder(): string
    {
        return public_path('uploads');
    }

    /**
     * Files still in the old folder, not counting placeholders.
     */
    public static function leftBehind(): int
    {
        if (! is_dir(self::oldFolder())) {
            return 0;
        }

        return collect(File::allFiles(self::oldFolder()))->reject(fn ($f) => $f->getFilename() === '.gitignore')->count();
    }

    /**
     * Move every file across, keeping its path and date. A file already in the
     * new folder is left as it is. Returns [moved, already there, failed].
     *
     * @return array{0: int, 1: int, 2: int}
     */
    public static function moveFromStorage(): array
    {
        $from = self::oldFolder();
        $to = self::folder();
        $moved = $kept = $failed = 0;
        if (! is_dir($from)) {
            return [0, 0, 0];
        }
        File::ensureDirectoryExists($to);

        foreach (File::allFiles($from) as $file) {
            if ($file->getFilename() === '.gitignore') {
                continue;
            }
            $target = $to.DIRECTORY_SEPARATOR.$file->getRelativePathname();
            if (is_file($target)) {
                $kept++;

                continue;
            }
            try {
                File::ensureDirectoryExists(dirname($target));
                if (! @rename($file->getPathname(), $target)) {
                    // Across disks rename can fail: copy, keep the date, then remove the old one.
                    $time = $file->getMTime();
                    File::copy($file->getPathname(), $target);
                    @touch($target, $time);
                    @unlink($file->getPathname());
                }
                $moved++;
            } catch (Throwable $e) {
                $failed++;
                Log::warning('Could not move an upload to public/uploads', ['file' => $file->getRelativePathname(), 'error' => $e->getMessage()]);
            }
        }

        // The old shortcut now points at an empty folder; remove it so it can't confuse anyone.
        $link = public_path('storage');
        if (is_link($link)) {
            @unlink($link);
        }

        return [$moved, $kept, $failed];
    }
}
