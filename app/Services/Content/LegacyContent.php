<?php

namespace App\Services\Content;

use App\Models\PageContent;
use App\Models\Post;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Moves the starter content that used to live only in config/content into the database,
 * so it can be edited in admin. Runs once from the migration; safe to run again.
 */
class LegacyContent
{
    public static function import(): void
    {
        static::importPosts();
        static::importPageNames();
    }

    private static function importPosts(): void
    {
        if (Post::query()->exists()) {
            return;
        }

        $posts = config('content.posts.posts', []);
        foreach ($posts as $i => $p) {
            Post::create([
                'title' => $p['title'],
                'slug' => $p['slug'],
                'topic' => $p['topic'],
                'dek' => $p['dek'] ?? null,
                'body' => static::markdown($p['body'] ?? []) ?: '[Article body: write this article in admin, under Blog.]',
                'swatch' => $p['swatch'] ?? 'navy',
                'author_name' => $p['author'] ?? null,
                'author_bio' => $p['author_bio'] ?? null,
                'is_featured' => (bool) ($p['featured'] ?? false),
                // Keep the design's order: the first article is the newest.
                'published_at' => now()->subMinutes($i),
            ]);
        }
    }

    /**
     * Leader names and the testimony used to be saved in Settings; carry them over.
     */
    private static function importPageNames(): void
    {
        if (! Schema::hasTable('settings') || PageContent::query()->exists()) {
            return;
        }
        $saved = DB::table('settings')->where('key', 'like', 'content.%')->whereNotNull('value')->where('value', '!=', '')->pluck('value', 'key');
        if ($saved->isEmpty()) {
            return;
        }

        $about = config('content.about');
        foreach ($about['leaders'] as $n => $leader) {
            $about['leaders'][$n]['name'] = $saved["content.about.leaders.{$n}.name"] ?? $leader['name'];
        }
        PageContent::create(['key' => 'about', 'content' => ['leaders' => $about['leaders']]]);

        $testimony = config('content.home.testimony');
        foreach (['name', 'quote', 'initials', 'role'] as $field) {
            $testimony[$field] = $saved["content.home.testimony.{$field}"] ?? $testimony[$field];
        }
        PageContent::create(['key' => 'home', 'content' => ['testimony' => $testimony]]);
    }

    /**
     * The old body blocks as Markdown.
     *
     * @param  list<array<int, mixed>>  $blocks
     */
    private static function markdown(array $blocks): string
    {
        return collect($blocks)->map(fn ($b) => match ($b[0]) {
            'p' => $b[1],
            'h2' => '## '.$b[1],
            'quote' => '> "'.$b[1]."\"\n> — ".$b[2],
            'reflect' => ":::reflect {$b[1]}\n".collect($b[2])->map(fn ($item, $n) => ($n + 1).'. '.$item)->join("\n")."\n:::",
            default => '',
        })->filter()->join("\n\n");
    }
}
