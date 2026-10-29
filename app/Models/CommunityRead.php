<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * The last message someone has seen in a room, for unread counts.
 */
#[Fillable(['user_id', 'community_room_id', 'last_read_id'])]
class CommunityRead extends Model
{
    public static function mark(User $user, CommunityRoom $room, int $messageId): void
    {
        $read = static::firstOrNew(['user_id' => $user->id, 'community_room_id' => $room->id]);
        if ($messageId > $read->last_read_id) {
            $read->last_read_id = $messageId;
            $read->save();
        }
    }
}
