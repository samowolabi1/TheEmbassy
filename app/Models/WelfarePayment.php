<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money (or items bought) given on a welfare case. Each one is also an approved church expense.
 */
#[Fillable(['amount', 'method', 'paid_on', 'reference'])]
class WelfarePayment extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'paid_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<WelfareCase, $this>
     */
    public function welfareCase(): BelongsTo
    {
        return $this->belongsTo(WelfareCase::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
