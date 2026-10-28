@extends('layouts.admin', ['title' => 'Content & announcements', 'active' => 'content'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Content &amp; announcements</h1>
            <span class="page-head__sub">What you publish here shows on the home page of the website.</span>
        </div>
    </header>

    <div class="row">
        <section class="box grow" id="new-announcement">
            <h2>New announcement</h2>
            <form method="POST" action="{{ route('admin.announcements.store') }}" class="form">
                @csrf
                @include('admin.partials.announcement-fields', ['announcement' => new \App\Models\Announcement])
                <button type="submit" class="btn btn--gold" style="align-self: flex-start">Save announcement</button>
            </form>
        </section>

        <aside class="side-col stack" style="gap: 12px; align-items: center">
            <span class="box__note" style="font-weight: 800; letter-spacing: 0.1em">PREVIEW</span>
            <div class="preview" aria-hidden="true">
                <div class="preview__screen">
                    <span class="preview__title">Announcements</span>
                    <div style="background: var(--white); border: 1px solid var(--line); border-radius: 18px; padding: 14px; display: flex; gap: 10px">
                        <span style="width: 36px; height: 36px; border-radius: 10px; background: var(--gold); display: flex; align-items: center; justify-content: center; flex-shrink: 0"><x-crown :width="20" :height="17" fill="var(--midnight)" /></span>
                        <span class="stack" style="gap: 4px; min-width: 0">
                            <span id="preview-title" style="font-weight: 800; font-size: 14px; color: var(--navy); overflow-wrap: anywhere">Your title</span>
                            <span id="preview-body" style="font-size: 13px; line-height: 1.45; color: var(--body); overflow-wrap: anywhere">Your message</span>
                            <span style="font-size: 11px; color: var(--slate)">{{ config('church.short_name') }} · now</span>
                        </span>
                    </div>
                </div>
            </div>
            <span class="box__note" style="text-align: center; max-width: 300px">The newest three live announcements show on the home page. Use "Send" on an announcement to text members, notify the app, or share it to WhatsApp.</span>
        </aside>
    </div>

    <div class="row">
        <section class="box box--navy declaration-form" id="declaration" style="flex: 1 1 420px">
            <div class="box__head">
                <h2>Weekly declaration</h2>
                <span class="pill {{ $declaration->week_of->isSameDay($thisWeek) ? 'pill--ok' : 'pill--scheduled' }}">
                    {{ $declaration->week_of->isSameDay($thisWeek) ? 'This week' : ($declaration->week_of->isFuture() ? 'Upcoming' : 'Past') }}
                </span>
            </div>
            <nav class="week-picker" aria-label="Choose a week">
                @foreach ([$thisWeek->copy()->subWeek(), $thisWeek, $thisWeek->copy()->addWeek(), $thisWeek->copy()->addWeeks(2)] as $week)
                    <a href="{{ route('admin.content', ['week' => $week->toDateString()]) }}#declaration" @if ($week->isSameDay($declaration->week_of)) aria-current="page" @endif>
                        {{ $week->isSameDay($thisWeek) ? 'This week' : 'Week of '.$week->format('j M') }}
                    </a>
                @endforeach
            </nav>
            <form method="POST" action="{{ route('admin.declarations.store') }}" class="form" style="gap: 12px">
                @csrf
                <input type="hidden" name="week_of" value="{{ $declaration->week_of->toDateString() }}">
                <span style="font-size: 13px; color: var(--mist)">
                    For the week of <strong style="color: var(--white)">{{ $declaration->week_of->format('l j F') }}</strong>.
                    {{ $declaration->exists ? '' : 'Not saved yet: these lines are copied from the latest one.' }}
                </span>
                @foreach (range(0, 3) as $i)
                    <label class="field">LINE {{ $i + 1 }}
                        <input type="text" name="lines[]" value="{{ old("lines.$i", $declaration->lines[$i] ?? '') }}" required maxlength="160">
                        @error("lines.$i") <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                @endforeach
                <button type="submit" class="btn btn--gold" style="align-self: flex-start">Save declaration</button>
            </form>
        </section>

        <section class="box box--flush" style="flex: 1 1 460px">
            <div class="box__head">
                <h2>All announcements</h2>
                <nav class="tabs" aria-label="Filter announcements">
                    @foreach (['all' => 'All', 'live' => 'Live', 'scheduled' => 'Scheduled', 'draft' => 'Drafts'] as $key => $label)
                        <a href="{{ route('admin.content', $key === 'all' ? [] : ['show' => $key]) }}" class="tab" style="height: 34px; font-size: 13px" @if ($filter === $key) aria-current="page" @endif>{{ $label }}</a>
                    @endforeach
                </nav>
            </div>
            @forelse ($announcements as $a)
                <div class="list-item">
                    <span class="list-item__main">
                        <span class="list-item__title">{{ $a->title }}</span>
                        <span class="list-item__meta">
                            {{ $a->tag }} ·
                            @switch($a->status())
                                @case('live') Live since {{ $a->published_at->format('j M, g:i A') }} @break
                                @case('scheduled') Goes live {{ $a->published_at->format('D j M, g:i A') }} @break
                                @default Edited {{ $a->updated_at->diffForHumans() }}
                            @endswitch
                        </span>
                    </span>
                    <span class="pill pill--{{ $a->status() }}">{{ $a->status() }}</span>
                    <span class="list-item__actions">
                        @if ($a->status() === 'live')
                            <a href="{{ route('admin.announcements.send', $a) }}" class="btn btn--ghost btn--xs">Send<span class="sr-only"> {{ $a->title }}</span></a>
                        @endif
                        <a href="{{ route('admin.announcements.edit', $a) }}" class="btn btn--ghost btn--xs">Edit<span class="sr-only"> {{ $a->title }}</span></a>
                        <form method="POST" action="{{ route('admin.announcements.destroy', $a) }}" data-confirm="Delete &quot;{{ $a->title }}&quot;?">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn--danger btn--xs">Delete<span class="sr-only"> {{ $a->title }}</span></button>
                        </form>
                    </span>
                </div>
            @empty
                <p class="empty-state">No announcements here yet.</p>
            @endforelse
        </section>
    </div>
@endsection
