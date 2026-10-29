<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member flagging a message for moderators to look at.
 */
#[Fillable(['community_message_id', 'user_id', 'reason', 'resolved_at', 'resolved_by', 'outcome'])]
class CommunityReport extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<CommunityMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(CommunityMessage::class, 'community_message_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
