<?php

namespace App\Models;

use App\Contracts\Payable;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['reference', 'email', 'amount', 'platform_fee', 'currency'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    public const PENDING = 'pending';

    public const SUCCESS = 'success';

    public const FAILED = 'failed';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'platform_fee' => 'integer',
            'paid_at' => 'datetime',
            'gateway_response' => 'array',
        ];
    }

    /**
     * @return MorphTo<Model&Payable, $this>
     */
    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isPaid(): bool
    {
        return $this->status === self::SUCCESS;
    }
}
