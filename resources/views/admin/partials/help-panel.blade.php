{{-- The Help button on every admin page: what the page is for, its questions, a guided tour, and a link to the help centre. --}}
@php
    $help = config("help.pages.{$active}") ?? config('help.pages.dashboard');
    $routeName = \Illuminate\Support\Facades\Route::currentRouteName();
    $tour = collect(config("help.tours")[$routeName] ?? [])->map(fn ($s) => ['target' => $s[0], 'title' => $s[1], 'body' => $s[2]])->values();
    $guides = collect(config('help.guides'))->filter(fn ($g) => ($g['area'] ?? null) === ($help['area'] ?? '__') || ($g['route'] === $routeName))->take(3);
@endphp
<a href="{{ route('admin.help') }}#{{ $active }}" class="help-fab" data-help-open aria-haspopup="dialog">
    <x-icon name="help" :size="20" /><span>Help</span>
</a>

<dialog class="help-panel" aria-labelledby="help-panel-title" data-help-panel>
    <div class="help-panel__head">
        <span class="help-panel__kicker">HELP</span>
        <h2 id="help-panel-title">{{ $help['title'] }}</h2>
        <button type="button" class="help-panel__close" data-help-close aria-label="Close help"><x-icon name="close" :size="20" /></button>
    </div>
    <div class="help-panel__body">
        <p class="help-panel__about">{{ $help['about'] }}</p>

        @if ($tour->isNotEmpty())
            <button type="button" class="btn btn--gold btn--sm" data-tour-start>Take the tour · {{ $tour->count() }} {{ \Illuminate\Support\Str::plural('step', $tour->count()) }}</button>
        @endif

        @if (! empty($help['faqs']))
            <h3>Questions people ask</h3>
            <div class="help-faqs">
                @foreach ($help['faqs'] as [$q, $a])
                    <details class="help-faq">
                        <summary>{{ $q }}</summary>
                        <p>{{ $a }}</p>
                    </details>
                @endforeach
            </div>
        @endif

        @if ($guides->isNotEmpty())
            <h3>How to…</h3>
            <ul class="help-guides">
                @foreach ($guides as $slug => $guide)
                    <li><a href="{{ route('admin.help') }}#guide-{{ $slug }}">{{ $guide['title'] }}</a></li>
                @endforeach
            </ul>
        @endif

        <a href="{{ route('admin.help') }}" class="box__link">All guides and questions in the help centre →</a>
    </div>
</dialog>

@if ($tour->isNotEmpty())
    <script type="application/json" id="page-tour" data-key="{{ $routeName }}">@json($tour)</script>
@endif
