<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Someone saying "Praise God" on a testimony (once each).
 */
#[Fillable(['testimony_id', 'user_id'])]
class TestimonyPraise extends Model {}
