<?php

namespace App\Services\Content;

use App\Models\Event;
use App\Models\Sermon;
use App\Models\SermonSeries;
use Illuminate\Support\Collection;

/**
 * Messages and series for the home and Watch pages. Until the church adds its own in admin,
 * the examples in config/content/sermons.php are shown.
 */
class SermonLibrary
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function messages(): Collection
    {
        $sermons = Sermon::published()->with('series')->get();
        if ($sermons->isEmpty()) {
            return collect(config('content.sermons.messages'));
        }

        return $sermons->map(fn (Sermon $s) => [
            'type' => $s->kind,
            'kind' => $s->kindLabel(),
            'title' => $s->title,
            'series' => $s->series?->fullTitle(),
            'meta' => $s->meta(),
            'length' => $s->length,
            'swatch' => $s->swatch,
            'url' => $s->url ?: route('watch'),
            'scripture' => $s->scripture,
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function series(): Collection
    {
        $series = SermonSeries::withCount(['sermons' => fn ($q) => $q->where('is_published', true)])->orderByDesc('is_current')->latest()->get();
        if ($series->isEmpty()) {
            return collect(config('content.sermons.series'));
        }

        return $series->map(fn (SermonSeries $s) => [
            'title' => $s->title,
            'accent' => $s->accent,
            'label' => ($s->is_current ? 'Now showing · ' : '').$s->sermons_count.' '.str('episode')->plural($s->sermons_count),
            'style' => $s->style,
            'url' => $s->url ?: route('watch'),
        ]);
    }

    /**
     * The latest message in the current series, for the home page feature and the Watch page title.
     *
     * @return array<string, mixed>
     */
    public function headline(): array
    {
        $current = SermonSeries::where('is_current', true)->latest()->first();
        $latest = $current?->sermons()->where('is_published', true)->orderByDesc('preached_on')->first();

        if (! $current) {
            return ['title' => 'A royal priesthood', 'length' => '42 min', 'url' => route('watch'), 'series_title' => 'Crowned', 'series_accent' => 'for More', 'week' => 3, 'scripture' => '1 Peter 2:9', 'kind' => 'Sunday Service'];
        }

        return [
            'title' => $latest?->title,
            'length' => $latest?->length,
            'url' => $latest?->url ?: ($current->url ?: route('watch')),
            'series_title' => $current->title,
            'series_accent' => $current->accent,
            'week' => $current->sermons()->where('is_published', true)->count(),
            'scripture' => $latest?->scripture,
            'kind' => $latest?->kindLabel() ?? 'Sunday service',
        ];
    }

    /**
     * Midweek service, then the next events.
     *
     * @return Collection<int, array{title: string, when: string}>
     */
    public function upcomingLive(): Collection
    {
        return collect([['title' => config('church.midweek_day').' service', 'when' => config('church.midweek_day').' · '.config('church.midweek_time')]])
            ->concat(Event::upcoming()->take(2)->get()->map(fn (Event $e) => ['title' => $e->title, 'when' => $e->starts_at->format('D j M · g:i A')]));
    }
}
