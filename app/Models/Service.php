<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A church service and how many came, in person and online.
 */
#[Fillable(['held_on', 'kind', 'title', 'in_person', 'online', 'notes'])]
class Service extends Model
{
    use HasFactory;

    public const KINDS = [
        'sunday' => 'Sunday service',
        'midweek' => 'Midweek service',
        'special' => 'Special service',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'held_on' => 'date',
            'in_person' => 'integer',
            'online' => 'integer',
        ];
    }

    /**
     * Members marked present.
     *
     * @return BelongsToMany<Member, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class);
    }

    public function total(): int
    {
        return $this->in_person + $this->online;
    }

    public function label(): string
    {
        return $this->title ?: (self::KINDS[$this->kind] ?? 'Service');
    }
}
