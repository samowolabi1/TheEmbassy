<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Someone saying "I prayed" for a request on the prayer wall (once each).
 */
#[Fillable(['prayer_request_id', 'user_id'])]
class PrayerRequestPrayer extends Model {}
