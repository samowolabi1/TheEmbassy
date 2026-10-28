@extends('layouts.unit', ['title' => 'Service setup & streaming', 'active' => 'setup'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Service setup &amp; streaming</h1>
            <span class="page-head__sub">The checklist for each service, the stream details, and the keys for the streaming software (OBS, vMix or an encoder)</span>
        </div>
    </header>

    @if ($live)
        <p class="flash" role="status">● Live now: {{ $live->service->label() }} since {{ $live->went_live_at->format('g:i A') }}. <a href="{{ route('unit.setup.show', [$unit, $live->service]) }}">Open</a></p>
    @endif

    <section class="box box--flush">
        <div class="box__head"><h2>Coming services</h2></div>
        @forelse ($services as $s)
            <a href="{{ route('unit.setup.show', [$unit, $s]) }}" class="list-item" style="color: var(--ink)">
                <span class="list-item__main">
                    <span class="list-item__title">{{ $s->label() }}</span>
                    <span class="list-item__meta">{{ $s->starts_at?->format('l j M, g:i A') ?? $s->held_on->format('l j M') }}</span>
                </span>
                @if ($s->setup)
                    <span class="pill {{ $s->setup->done_count === $s->setup->tasks_count && $s->setup->tasks_count ? 'pill--ok' : 'pill--info' }}">{{ $s->setup->done_count }}/{{ $s->setup->tasks_count }} ready</span>
                    @if ($s->setup->isLive())<span class="pill pill--warn">Live</span>@endif
                @else
                    <span class="pill pill--draft">Not started</span>
                @endif
            </a>
        @empty
            <p class="empty-state">No services scheduled. The church office adds them under Attendance.</p>
        @endforelse
    </section>

    @if ($canLead)
        <section class="box">
            <h2>Setup checklist</h2>
            <form method="POST" action="{{ route('unit.setup.checklist', $unit) }}" class="form">
                @csrf
                @method('PUT')
                <label class="field">One item per line. Each new service setup starts with these.
                    <textarea name="checklist" maxlength="5000" style="height: 160px">{{ implode("\n", $unit->checklistItems()) }}</textarea>
                </label>
                <div><button type="submit" class="btn btn--ghost btn--xs">Save checklist</button></div>
            </form>
        </section>

        <section class="box box--flush">
            <div class="box__head"><h2>Stream destinations</h2><span class="box__note">Server and key from each platform's live settings (YouTube Studio › Go live › Stream; Facebook Live Producer). Keys are stored encrypted.</span></div>
            @foreach ($destinations as $d)
                <details class="list-item list-item--edit">
                    <summary>
                        <span class="list-item__main">
                            <span class="list-item__title">{{ $d->label }}</span>
                            <span class="list-item__meta">{{ \App\Models\StreamDestination::PLATFORMS[$d->platform] }} · {{ $d->server_url }} · key {{ $d->maskedKey() }}</span>
                        </span>
                        @unless ($d->is_active)<span class="pill pill--draft">Off</span>@endunless
                        <a href="{{ route('unit.streams.key', [$unit, $d]) }}" class="btn btn--ghost btn--xs" target="_blank" rel="noopener">Show key</a>
                    </summary>
                    <form method="POST" action="{{ route('unit.streams.update', [$unit, $d]) }}" class="form">
                        @csrf
                        @method('PUT')
                        @include('unit.media.stream-fields', ['d' => $d])
                        <label class="login__remember"><input type="checkbox" name="is_active" value="1" @checked($d->is_active)>In use</label>
                        <div class="list-item__actions">
                            <button type="submit" class="btn btn--navy btn--xs">Save</button>
                            <button type="submit" form="delete-stream-{{ $d->id }}" class="btn btn--danger btn--xs">Remove</button>
                        </div>
                    </form>
                    <form id="delete-stream-{{ $d->id }}" method="POST" action="{{ route('unit.streams.destroy', [$unit, $d]) }}" data-confirm="Remove this stream destination?">
                        @csrf
                        @method('DELETE')
                    </form>
                </details>
            @endforeach
            <details class="list-item list-item--edit" @if ($errors->has('server_url') || $errors->has('stream_key')) open @endif>
                <summary><span class="list-item__main"><span class="list-item__title">+ Add a destination</span></span></summary>
                <form method="POST" action="{{ route('unit.streams.store', $unit) }}" class="form">
                    @csrf
                    @include('unit.media.stream-fields', ['d' => new \App\Models\StreamDestination(['platform' => 'youtube'])])
                    <div><button type="submit" class="btn btn--navy btn--xs">Save</button></div>
                </form>
            </details>
        </section>
    @endif
@endsection
