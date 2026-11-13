<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A member's birthday, celebrated: the year's card and the community post that carries it.
 */
class BirthdayCelebration extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * @return BelongsTo<CommunityMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(CommunityMessage::class, 'community_message_id');
    }

    public function cardUrl(): string
    {
        return Storage::disk('public')->url($this->card_path);
    }
}
