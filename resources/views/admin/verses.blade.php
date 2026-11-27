@extends('layouts.admin', ['title' => 'Bible verses', 'active' => 'verses'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Bible verses</h1>
            <span class="page-head__sub">The verses members see in Verses on their account, and once a day when they open the app. King James Version.</span>
        </div>
    </header>

    <div class="row">
        <section class="box grow">
            <nav class="tabs" aria-label="Which verses">
                <a href="{{ route('admin.verses.index') }}" class="tab" @unless ($hidden) aria-current="page" @endunless>Showing <span>{{ $activeCount }}</span></a>
                <a href="{{ route('admin.verses.index', ['show' => 'hidden']) }}" class="tab" @if ($hidden) aria-current="page" @endif>Hidden <span>{{ $hiddenCount }}</span></a>
            </nav>
            <form method="GET" class="form form-row" style="align-items: end">
                @if ($hidden)<input type="hidden" name="show" value="hidden">@endif
                <label class="field">Search <input type="search" name="q" value="{{ $q }}" placeholder="A reference or words, e.g. Psalm 23 or shepherd"></label>
                <label class="field">Theme
                    <select name="theme">
                        <option value="">All themes</option>
                        @foreach (\App\Models\Verse::THEMES as $t)
                            <option value="{{ $t }}" @selected($theme === $t)>{{ $t }}</option>
                        @endforeach
                    </select>
                </label>
                <div><button type="submit" class="btn btn--navy">Find</button></div>
            </form>

            @forelse ($verses as $v)
                <div class="list-item verse-row">
                    <div class="stack" style="gap: 4px; min-width: 0">
                        <strong>{{ $v->reference }} @if ($v->theme)<span class="pill pill--draft">{{ $v->theme }}</span>@endif</strong>
                        <span class="box__note" style="margin: 0">{{ $v->text }}</span>
                    </div>
                    <div class="list-item__actions">
                        <details class="list-item--edit">
                            <summary class="btn btn--ghost btn--xs">Edit</summary>
                            <form method="POST" action="{{ route('admin.verses.update', $v) }}" class="form" style="margin-top: 10px">
                                @csrf
                                @method('PUT')
                                @include('admin.partials.verse-fields', ['verse' => $v])
                                <div><button type="submit" class="btn btn--gold btn--xs">Save</button></div>
                            </form>
                        </details>
                        <form method="POST" action="{{ route('admin.verses.toggle', $v) }}" @if ($v->is_active) data-confirm="Hide {{ $v->reference }} from members?" data-confirm-ok="Hide it" @endif>
                            @csrf
                            <button type="submit" class="btn btn--ghost btn--xs">{{ $v->is_active ? 'Hide' : 'Show again' }}</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="box__note">{{ $q || $theme ? 'No verses match that.' : ($hidden ? 'No hidden verses.' : 'No verses yet. Add the first one.') }}</p>
            @endforelse
            {{ $verses->links() }}
        </section>

        <section class="box side-col" id="new-verse">
            <h2>Add a verse</h2>
            <form method="POST" action="{{ route('admin.verses.store') }}" class="form">
                @csrf
                @include('admin.partials.verse-fields', ['verse' => new \App\Models\Verse])
                @if ($errors->any())<span class="field-error">{{ $errors->first() }}</span>@endif
                <div><button type="submit" class="btn btn--gold">Add verse</button></div>
            </form>
            <p class="box__note">Use the King James Version: it's free to use. Most modern versions (NIV, NKJV, NLT, AMP) need the publisher's permission.</p>
        </section>
    </div>
@endsection
