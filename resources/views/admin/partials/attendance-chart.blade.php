{{-- Stacked bars of the last Sundays: in person (navy) and online (gold). Expects $sundays. --}}
@php($max = max(1, $sundays->max(fn ($s) => $s->total())))
@if ($sundays->isEmpty())
    <p class="empty-state">No Sunday services recorded yet.</p>
@else
    <div class="chart" role="img" aria-label="Sunday attendance: {{ $sundays->map(fn ($s) => $s->held_on->format('j M').' '.$s->total())->join(', ') }}">
        @foreach ($sundays as $s)
            <div class="chart__col">
                <span class="chart__num">{{ number_format($s->total()) }}</span>
                <div class="chart__bar" style="height: {{ round($s->total() * 100 / $max) }}%">
                    <span class="chart__online" style="flex: {{ $s->online }}"></span>
                    <span class="chart__inperson" style="flex: {{ $s->in_person }}"></span>
                </div>
                <span class="chart__label">{{ $s->held_on->format('j M') }}</span>
            </div>
        @endforeach
    </div>
    <div class="chart__legend"><span><i class="chart__key chart__key--inperson"></i>In person</span><span><i class="chart__key chart__key--online"></i>Online</span></div>
@endif
