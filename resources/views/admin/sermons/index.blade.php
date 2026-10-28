@extends('layouts.admin', ['title' => 'Sermons & live', 'active' => 'live'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Sermons &amp; live</h1>
            <span class="page-head__sub">The message library and series on the Watch and home pages, and the live stream player</span>
        </div>
        <div class="actions">
            <a href="{{ route('watch') }}" class="btn btn--outline">View Watch page</a>
            <a href="{{ route('admin.sermons.create') }}" class="btn btn--gold"><x-icon name="plus" :size="16" />Add a message</a>
        </div>
    </header>

    <div class="row">
        <section class="box grow">
            <h2>Live stream</h2>
            <form method="POST" action="{{ route('admin.sermons.live') }}" class="form">
                @csrf
                <label class="field">YouTube video or live stream ID
                    <input type="text" name="video_id" value="{{ old('video_id', $live['video_id']) }}" maxlength="20" placeholder="e.g. dQw4w9WgXcQ">
                    <span class="field__hint">The part after <code>watch?v=</code> in the YouTube link. It plays on the Watch page.</span>
                    @error('video_id') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <label class="login__remember"><input type="checkbox" name="is_live" value="1" @checked($live['is_live'])>We're live now (shows "Live on YouTube" on the player)</label>
                <div><button type="submit" class="btn btn--navy">Save</button></div>
            </form>
        </section>

        <section class="box box--flush side-col">
            <div class="box__head"><h2>Series</h2></div>
            @foreach ($series as $s)
                <details class="list-item list-item--edit">
                    <summary>
                        <span class="list-item__main">
                            <span class="list-item__title">{{ $s->fullTitle() }}@if ($s->is_current) <span class="pill pill--ok">Now showing</span>@endif</span>
                            <span class="list-item__meta">{{ $s->sermons_count }} {{ \Illuminate\Support\Str::plural('message', $s->sermons_count) }}</span>
                        </span>
                        <span class="btn btn--ghost btn--xs">Edit</span>
                    </summary>
                    <form method="POST" action="{{ route('admin.series.update', $s) }}" class="form">
                        @csrf
                        @method('PUT')
                        @include('admin.sermons.series-fields', ['s' => $s])
                        <div class="list-item__actions">
                            <button type="submit" class="btn btn--navy btn--xs">Save</button>
                            <button type="submit" form="delete-series-{{ $s->id }}" class="btn btn--danger btn--xs">Remove</button>
                        </div>
                    </form>
                    <form id="delete-series-{{ $s->id }}" method="POST" action="{{ route('admin.series.destroy', $s) }}" data-confirm="Remove this series? Its messages stay in the library.">
                        @csrf
                        @method('DELETE')
                    </form>
                </details>
            @endforeach
            <details class="list-item list-item--edit" @if ($errors->series->any()) open @endif>
                <summary><span class="list-item__main"><span class="list-item__title">+ New series</span></span></summary>
                <form method="POST" action="{{ route('admin.series.store') }}" class="form">
                    @csrf
                    @include('admin.sermons.series-fields', ['s' => new \App\Models\SermonSeries(['style' => 'navy'])])
                    @error('title', 'series') <span class="field-error">{{ $message }}</span> @enderror
                    <div><button type="submit" class="btn btn--navy btn--xs">Add series</button></div>
                </form>
            </details>
        </section>
    </div>

    <section class="box box--flush">
        <div class="box__head"><h2>Messages</h2>@if ($sermons->isEmpty())<span class="box__note">Until you add messages, the Watch page shows the design's examples.</span>@endif</div>
        <div class="table-wrap">
            <table class="table" style="min-width: 680px">
                <thead><tr><th>MESSAGE</th><th>TYPE</th><th>SERIES</th><th>DATE</th><th class="num"><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                    @forelse ($sermons as $sermon)
                        <tr>
                            <td>
                                <span style="font-weight: 800">{{ $sermon->title }}</span>
                                @unless ($sermon->is_published) <span class="pill pill--draft">Hidden</span>@endunless
                                @unless ($sermon->url) <span class="pill pill--warn">No link</span>@endunless
                                @if ($sermon->preacher)<br><span style="font-size: 13px; color: var(--slate)">{{ $sermon->preacher }}</span>@endif
                            </td>
                            <td>{{ $sermon->kindLabel() }}</td>
                            <td>{{ $sermon->series?->fullTitle() ?? '—' }}</td>
                            <td style="white-space: nowrap; color: var(--slate)">{{ $sermon->preached_on->format('j M Y') }}</td>
                            <td class="num"><a href="{{ route('admin.sermons.edit', $sermon) }}" class="btn btn--ghost btn--xs">Edit<span class="sr-only"> {{ $sermon->title }}</span></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><p class="empty-state">No messages yet. Add your first one.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('admin.partials.pager', ['paginator' => $sermons])
    </section>
@endsection
