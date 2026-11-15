@extends('layouts.admin', ['title' => 'Help centre', 'active' => 'help'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Help centre</h1>
            <span class="page-head__sub">Step-by-step guides for common jobs, and answers to the questions people ask. Every page also has a Help button with a short tour of that page.</span>
        </div>
    </header>

    <div class="help-search">
        <label class="sr-only" for="help-q">Search help</label>
        <x-icon name="search" :size="18" />
        <input id="help-q" type="search" placeholder="Search, e.g. offering, approve, tickets" data-help-search autocomplete="off">
    </div>
    <p class="empty-state" data-help-empty hidden>Nothing matches. Try another word, or ask the church office.</p>

    <section class="stack" style="gap: 12px" aria-labelledby="guides-title">
        <h2 id="guides-title" class="help-section-title">Walkthroughs</h2>
        <div class="help-guide-grid">
            @foreach ($guides as $slug => $guide)
                <article class="box help-guide" id="guide-{{ $slug }}" data-help-item>
                    <h3>{{ $guide['title'] }}</h3>
                    <ol>
                        @foreach ($guide['steps'] as $step)
                            <li>{{ $step }}</li>
                        @endforeach
                    </ol>
                    @if (\Illuminate\Support\Facades\Route::has($guide['route']))
                        <div class="help-guide__actions">
                            <a href="{{ route($guide['route']) }}" class="btn btn--ghost btn--xs">Open the page</a>
                            @if ($guide['tour'])
                                <a href="{{ route($guide['route'], ['tour' => 1]) }}" class="btn btn--navy btn--xs">Show me around</a>
                            @endif
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    </section>

    <section class="stack" style="gap: 12px" aria-labelledby="faqs-title">
        <h2 id="faqs-title" class="help-section-title">Questions people ask</h2>
        @foreach ($pages as $key => $page)
            <div class="box help-topic" id="{{ $key }}" data-help-group>
                <h3>{{ $page['title'] }}</h3>
                <p class="box__note">{{ $page['about'] }}</p>
                <div class="help-faqs">
                    @foreach ($page['faqs'] as [$q, $a])
                        <details class="help-faq" data-help-item>
                            <summary>{{ $q }}</summary>
                            <p>{{ $a }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        @endforeach
    </section>
@endsection
