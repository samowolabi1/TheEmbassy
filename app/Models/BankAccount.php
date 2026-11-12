<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One of the church's bank accounts, shown on the Give page for transfers.
 */
#[Fillable(['currency', 'bank_name', 'account_name', 'account_number', 'sort_code', 'routing_number', 'swift', 'iban', 'note', 'use_for', 'sort', 'is_active'])]
class BankAccount extends Model
{
    public const CURRENCIES = [
        'NGN' => ['Naira', '₦'],
        'USD' => ['US dollar', '$'],
        'GBP' => ['Pound sterling', '£'],
        'EUR' => ['Euro', '€'],
        'CAD' => ['Canadian dollar', 'CA$'],
        'GHS' => ['Ghana cedi', 'GH₵'],
        'ZAR' => ['South African rand', 'R'],
    ];

    public const USES = [
        'all' => 'Tithes, offerings and donations',
        'tithe' => 'Tithes and offerings only',
        'donations' => 'Donations only',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort' => 'integer'];
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true)->orderByRaw("case currency when 'NGN' then 0 else 1 end")->orderBy('currency')->orderBy('sort')->orderBy('id');
    }

    /**
     * Accounts for tithes and offerings, or for donations.
     */
    #[Scope]
    protected function for(Builder $query, string $purpose): void
    {
        $query->whereIn('use_for', ['all', $purpose]);
    }

    public function currencyName(): string
    {
        return self::CURRENCIES[$this->currency][0] ?? $this->currency;
    }

    public function symbol(): string
    {
        return self::CURRENCIES[$this->currency][1] ?? $this->currency;
    }
}
