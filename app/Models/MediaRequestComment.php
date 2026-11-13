<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A message on a media request between the person who asked and the media team. Internal notes
 * are seen by the media team only.
 */
#[Fillable(['body', 'is_internal'])]
class MediaRequestComment extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_internal' => 'boolean'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<MediaRequest, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(MediaRequest::class, 'media_request_id');
    }
}
