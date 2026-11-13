<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Someone joining the stream from elsewhere (a guest minister, a testimony from abroad). They open
 * their link on a phone or laptop; OBS adds them with its own link. Video goes through VDO.Ninja,
 * a free service that connects the two directly.
 */
#[Fillable(['name'])]
class StudioGuest extends Model
{
    public function guestUrl(): string
    {
        return 'https://vdo.ninja/?push='.$this->stream_id.'&label='.rawurlencode($this->name).'&webcam&quality=1';
    }

    public function obsUrl(): string
    {
        return 'https://vdo.ninja/?view='.$this->stream_id.'&cleanoutput&solo';
    }
}
