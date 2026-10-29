<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * A Bible quiz members play once while it's open, with a leaderboard.
 */
#[Fillable(['title', 'description', 'opens_at', 'closes_at', 'seconds_per_question', 'is_published'])]
class Quiz extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'is_published' => 'boolean',
        ];
    }

    /**
     * @return HasMany<QuizQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return HasMany<QuizAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    #[Scope]
    protected function open(Builder $query): void
    {
        $query->where('is_published', true)->where('opens_at', '<=', now())->where('closes_at', '>', now())->whereHas('questions');
    }

    public function isOpen(): bool
    {
        return $this->is_published && $this->opens_at->lte(now()) && $this->closes_at->gt(now());
    }

    /**
     * Time allowed for the whole quiz, plus a little slack for slow connections.
     */
    public function timeLimit(): int
    {
        return $this->questions->count() * $this->seconds_per_question + 30;
    }

    /**
     * Highest score first; the faster time wins a tie.
     *
     * @return Collection<int, QuizAttempt>
     */
    public function leaderboard(int $take = 10)
    {
        return $this->attempts()->with('user.member')->whereNotNull('finished_at')
            ->orderByDesc('score')->orderBy('seconds')->orderBy('finished_at')->take($take)->get();
    }
}
