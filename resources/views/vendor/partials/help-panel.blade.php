{{-- The Help button on every seller page: what the page is for, common questions and a guided tour. --}}
@php
    $help = config('help.vendor')[$active] ?? config('help.vendor')['home'];
    // The help is written with the default currency name; use the one the church chose.
    $words = fn (string $t) => str_replace(['Crowns', 'Crown'], [\App\Support\TokenName::many(), \App\Support\TokenName::titleOne()], $t);
    $routeName = \Illuminate\Support\Facades\Route::currentRouteName();
    $tour = collect(config('help.tours')[$routeName] ?? [])->map(fn ($s) => ['target' => $s[0], 'title' => $s[1], 'body' => $s[2]])->values();
@endphp
<a href="#help" class="help-fab" data-help-open aria-haspopup="dialog">
    <x-icon name="help" :size="20" /><span>Help</span>
</a>

<dialog class="help-panel" aria-labelledby="help-panel-title" data-help-panel>
    <div class="help-panel__head">
        <span class="help-panel__kicker">HELP</span>
        <h2 id="help-panel-title">{{ $words($help['title']) }}</h2>
        <button type="button" class="help-panel__close" data-help-close aria-label="Close help"><x-icon name="close" :size="20" /></button>
    </div>
    <div class="help-panel__body">
        <p class="help-panel__about">{{ $words($help['about']) }}</p>
        @if ($tour->isNotEmpty())
            <button type="button" class="btn btn--gold btn--sm" data-tour-start>Take the tour · {{ $tour->count() }} {{ \Illuminate\Support\Str::plural('step', $tour->count()) }}</button>
        @endif
        @if (! empty($help['faqs']))
            <h3>Questions sellers ask</h3>
            <div class="help-faqs">
                @foreach ($help['faqs'] as [$q, $a])
                    <details class="help-faq">
                        <summary>{{ $words($q) }}</summary>
                        <p>{{ $words($a) }}</p>
                    </details>
                @endforeach
            </div>
        @endif
        @if ($contact = config('church.email') ?: config('church.phone'))
            <p class="box__note" style="margin: 0">Still stuck? Contact the church office: {{ $contact }}.</p>
        @endif
    </div>
</dialog>

@if ($tour->isNotEmpty())
    <script type="application/json" id="page-tour" data-key="{{ $routeName }}">@json($tour)</script>
@endif
