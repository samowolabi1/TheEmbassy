<?php

use App\Support\Uploads;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Uploaded pictures move from storage/app/public to public/uploads, so shared
 * hosting needs no storage:link shortcut. Blog articles keep their pictures'
 * addresses in their text, so those are updated too. Old /storage/ links
 * elsewhere (emails, shared posts) are redirected by the app.
 */
return new class extends Migration
{
    public function up(): void
    {
        Uploads::moveFromStorage();

        foreach (DB::table('posts')->where('body', 'like', '%/storage/blog/%')->get(['id', 'body']) as $post) {
            DB::table('posts')->where('id', $post->id)->update(['body' => str_replace('/storage/blog/', '/uploads/blog/', $post->body)]);
        }
    }

    public function down(): void
    {
        // The files stay in public/uploads; nothing to undo.
    }
};
