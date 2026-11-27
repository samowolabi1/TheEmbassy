<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A Bible verse (KJV) for the members' verse scroll and the daily verse. The church adds,
 * edits and hides them in admin › Bible verses.
 */
#[Fillable(['reference', 'text', 'theme', 'is_active'])]
class Verse extends Model
{
    public const THEMES = ['Comfort', 'Faith', 'Faithfulness', 'Forgiveness', 'Guidance', 'Healing', 'Hope', 'Joy', 'Love', 'Peace', 'Praise', 'Prayer', 'Protection', 'Provision', 'Purpose', 'Salvation', 'Strength', 'Victory', 'Wisdom'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @return BelongsToMany<Member, $this>
     */
    public function savedBy(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'member_verse')->withPivot('created_at');
    }

    /**
     * What goes in a share or the clipboard: the words, then the reference.
     */
    public function shareText(): string
    {
        return '"'.$this->text.'" — '.$this->reference.' (KJV)';
    }

    /**
     * @return array{id: int, reference: string, text: string, theme: ?string}
     */
    public function toCard(): array
    {
        return ['id' => $this->id, 'reference' => $this->reference, 'text' => $this->text, 'theme' => $this->theme];
    }
}
