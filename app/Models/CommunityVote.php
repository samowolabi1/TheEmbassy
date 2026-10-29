<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['community_message_id', 'user_id', 'option'])]
class CommunityVote extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<CommunityMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(CommunityMessage::class, 'community_message_id');
    }
}
