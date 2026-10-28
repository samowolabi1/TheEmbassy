@extends('layouts.unit', ['title' => 'Setup · '.$service->label(), 'active' => 'setup'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <a href="{{ route('unit.setup.index', $unit) }}" class="box__link">← Service setup &amp; streaming</a>
            <h1>{{ $service->label() }}</h1>
            <span class="page-head__sub">{{ $service->starts_at?->format('l j F, g:i A') ?? $service->held_on->format('l j F') }} · {{ $setup->tasks->whereNotNull('done_at')->count() }} of {{ $setup->tasks->count() }} ready</span>
        </div>
        @if ($canLead)
            <div class="actions">
                @if ($setup->isLive())
                    <form method="POST" action="{{ route('unit.setup.end', [$unit, $service]) }}" data-confirm="End the stream on the Watch page?">
                        @csrf
                        <button type="submit" class="btn btn--danger">● End stream</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('unit.setup.live', [$unit, $service]) }}">
                        @csrf
                        <button type="submit" class="btn btn--gold">Go live on the website</button>
                    </form>
                @endif
            </div>
        @endif
    </header>

    @error('youtube_video_id') <p class="flash flash--error" role="alert">{{ $message }}</p> @enderror
    @if ($setup->isLive())<p class="flash" role="status">● Live since {{ $setup->went_live_at->format('g:i A') }}. The Watch page is showing the stream.</p>@endif

    <div class="row">
        <section class="box box--flush grow">
            <div class="box__head"><h2>Checklist</h2><span class="box__note">Tick each item as it's ready</span></div>
            @foreach ($setup->tasks as $task)
                <div class="list-item">
                    <form method="POST" action="{{ route('unit.setup.tasks.toggle', [$unit, $task]) }}">
                        @csrf
                        <button type="submit" class="check-btn {{ $task->done_at ? 'is-done' : '' }}" aria-pressed="{{ $task->done_at ? 'true' : 'false' }}" aria-label="{{ $task->done_at ? 'Mark not done' : 'Mark done' }}: {{ $task->title }}">{{ $task->done_at ? '✓' : '' }}</button>
                    </form>
                    <span class="list-item__main">
                        <span class="list-item__title" @style(['text-decoration: line-through; color: var(--slate)' => $task->done_at])>{{ $task->title }}</span>
                        <span class="list-item__meta">{{ $task->member ? $task->member->name : 'Anyone' }}@if ($task->done_at) · done {{ $task->done_at->format('g:i A') }}{{ $task->doer ? ' by '.$task->doer->name : '' }}@endif</span>
                    </span>
                    @if ($canLead)
                        <form method="POST" action="{{ route('unit.setup.tasks.assign', [$unit, $task]) }}" style="display: flex; gap: 6px">
                            @csrf
                            @method('PUT')
                            <label class="sr-only" for="task-{{ $task->id }}">Who</label>
                            <select id="task-{{ $task->id }}" name="member_id" class="select-sm" onchange="this.form.submit()">
                                <option value="">Anyone</option>
                                @foreach ($members as $m)<option value="{{ $m->id }}" @selected($task->member_id === $m->id)>{{ $m->name }}</option>@endforeach
                            </select>
                        </form>
                    @endif
                </div>
            @endforeach
            @if ($canLead)
                <form method="POST" action="{{ route('unit.setup.tasks.store', [$unit, $service]) }}" style="display: flex; gap: 8px; flex-wrap: wrap; padding: 16px 20px">
                    @csrf
                    <label class="sr-only" for="new-task">New task</label>
                    <input id="new-task" type="text" name="title" required maxlength="255" placeholder="Add a task for this service" class="select-sm" style="flex: 1 1 220px">
                    <button type="submit" class="btn btn--navy btn--xs">Add</button>
                </form>
            @endif
        </section>

        <section class="box side-col">
            <h2>Stream</h2>
            @if ($canLead)
                <form method="POST" action="{{ route('unit.setup.update', [$unit, $service]) }}" class="form">
                    @csrf
                    @method('PUT')
                    <label class="field">Title <input type="text" name="stream_title" value="{{ $setup->stream_title }}" maxlength="100"></label>
                    <label class="field">Description <textarea name="stream_description" maxlength="5000" style="height: 110px">{{ $setup->stream_description }}</textarea></label>
                    <label class="field">YouTube video ID
                        <input type="text" name="youtube_video_id" value="{{ $setup->youtube_video_id }}" maxlength="20" placeholder="From the live stream's link">
                        <span class="field__hint">Shown in the Watch page player when you go live.</span>
                    </label>
                    <label class="field">Notes for the team <textarea name="notes" maxlength="5000" style="height: 80px">{{ $setup->notes }}</textarea></label>
                    <button type="submit" class="btn btn--navy">Save</button>
                </form>
                <button type="button" class="btn btn--ghost btn--xs" data-copy="{{ $setup->stream_title }}&#10;&#10;{{ $setup->stream_description }}">Copy title and description</button>
            @else
                <p class="box__note" style="margin: 0"><strong>{{ $setup->stream_title }}</strong><br>{{ $setup->stream_description }}</p>
                @if ($setup->notes)<p class="box__note" style="margin: 0; white-space: pre-line">{{ $setup->notes }}</p>@endif
            @endif

            @if ($duties->isNotEmpty())
                <h2 style="margin-top: 8px">On the rota</h2>
                @foreach ($duties as $duty)
                    <span style="font-size: 14px"><strong>{{ $duty->member->name }}</strong> · {{ $duty->role }}</span>
                @endforeach
            @endif

            @if ($canLead && $destinations->isNotEmpty())
                <h2 style="margin-top: 8px">Streaming to</h2>
                @foreach ($destinations as $d)
                    <span style="font-size: 14px">{{ $d->label }} · <a href="{{ route('unit.streams.key', [$unit, $d]) }}" target="_blank" rel="noopener">key</a></span>
                @endforeach
            @endif
            <a href="{{ route('watch') }}" class="box__link" target="_blank" rel="noopener">Open the Watch page</a>
        </section>
    </div>
@endsection
