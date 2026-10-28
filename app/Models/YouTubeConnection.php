<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The church's YouTube channel, connected through Google sign-in. Tokens are stored encrypted.
 */
#[Fillable(['channel_id', 'channel_title', 'access_token', 'refresh_token', 'expires_at', 'stream_id', 'problem'])]
#[Hidden(['access_token', 'refresh_token'])]
class YouTubeConnection extends Model
{
    protected $table = 'youtube_connections';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function connector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by');
    }

    public static function current(): ?self
    {
        return static::query()->latest('id')->first();
    }

    public function isWorking(): bool
    {
        return $this->problem === null;
    }
}
