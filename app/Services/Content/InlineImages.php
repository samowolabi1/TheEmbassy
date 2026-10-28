<?php

namespace App\Services\Content;

use App\Models\Post;
use Illuminate\Support\Facades\Storage;

/**
 * Pictures uploaded into articles that no article uses any more (removed from the text,
 * or the article was deleted). Recent uploads are left alone: they may be in an unsaved draft.
 */
class InlineImages
{
    public const FOLDER = 'blog/inline';

    /**
     * Delete unused pictures older than the given number of days. Returns how many were removed.
     */
    public function prune(int $olderThanDays = 2): int
    {
        $disk = Storage::disk('public');
        $cutoff = now()->subDays($olderThanDays)->getTimestamp();

        $removed = 0;
        foreach ($disk->allFiles(self::FOLDER) as $path) {
            if (str_ends_with($path, '-thumb.webp') || $disk->lastModified($path) > $cutoff) {
                continue;
            }
            $name = basename($path);
            if (Post::where('body', 'like', '%'.$name.'%')->exists()) {
                continue;
            }
            $disk->delete([$path, preg_replace('/\.webp$/', '-thumb.webp', $path)]);
            $removed++;
        }

        return $removed;
    }
}
