<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Money the church receives outside online giving: offerings counted after a service, donations,
 * hall hire, sales and so on. Any staff member records it; someone else confirms it.
 */
class Income extends Model
{
    public const PENDING = 'pending';

    public const CONFIRMED = 'confirmed';

    public const DECLINED = 'declined';

    public const METHODS = ['cash' => 'Cash', 'transfer' => 'Bank transfer', 'pos' => 'POS / card', 'other' => 'Other'];

    protected $fillable = ['title', 'category', 'amount', 'received_on', 'method', 'description'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'received_on' => 'date', 'decided_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function hasReceipt(): bool
    {
        return $this->receipt_path !== null && Storage::disk('local')->exists($this->receipt_path);
    }

    public function methodLabel(): string
    {
        return self::METHODS[$this->method] ?? ucfirst($this->method);
    }
}
