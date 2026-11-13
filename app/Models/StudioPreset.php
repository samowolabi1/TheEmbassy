<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A name caption or scripture saved before the service, to put on screen with one tap.
 */
#[Fillable(['type', 'line1', 'line2'])]
class StudioPreset extends Model {}
