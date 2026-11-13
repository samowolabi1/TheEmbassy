@extends('layouts.app', ['title' => $r->title, 'active' => null])

@section('content')
    <section class="container notices-hero stack" style="gap: 12px">
        <a href="{{ route('media-requests.index') }}" class="back-link"><x-icon name="chevron-left" :size="16" />Media requests</a>
        <span class="eyebrow">{{ strtoupper($r->typeLabel()) }} · {{ $r->reference }}</span>
        <h1 class="display notices-hero__title">{{ $r->title }}</h1>
        <span class="status-chip {{ in_array($r->status, ['delivered', 'completed']) ? 'status-chip--ok' : '' }}">{{ $r->statusLabel() }}</span>
    </section>

    <section class="container section--tight section--last stack" style="gap: 20px">
        @if (session('status'))
            <p class="notice-alert" role="status" style="margin: 0">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <p class="notice-alert" role="alert" style="margin: 0">{{ $errors->first() }}</p>
        @endif

        @php($delivered = $r->files->where('purpose', 'deliverable'))
        @if ($r->status === 'delivered' || $delivered->isNotEmpty())
            <div class="card notice-form__main" style="flex: none">
                <h2 class="h2 h2--sm" style="margin: 0">From the media team</h2>
                @if ($delivered->isNotEmpty())
                    @include('media-requests.files', ['files' => $delivered])
                @else
                    <p class="lede" style="margin: 0">See their message below.</p>
                @endif
                @if ($r->status === 'delivered')
                    <form method="POST" action="{{ route('media-requests.respond', $r) }}" class="notice-form__group">
                        @csrf
                        <button type="submit" name="answer" value="done" class="btn btn--gold" style="align-self: flex-start">It's good, mark it done</button>
                        <label class="field">Or ask for changes
                            <textarea name="changes" maxlength="3000" rows="3" placeholder="What should change?">{{ old('changes') }}</textarea>
                        </label>
                        <button type="submit" name="answer" value="changes" class="btn btn--outline btn--sm" style="align-self: flex-start">Send back with changes</button>
                    </form>
                @endif
            </div>
        @endif

        @if ($r->status === 'declined')
            <div class="card notice-form__main" style="flex: none">
                <h2 class="h2 h2--sm" style="margin: 0">The media team couldn't take this on</h2>
                <p class="lede" style="margin: 0">{{ $r->decline_reason }}</p>
            </div>
        @endif

        <div class="card notice-form__main" style="flex: none">
            <h2 class="h2 h2--sm" style="margin: 0">What you asked for</h2>
            <ul class="mini-list">
                <li><span>For</span><strong>{{ $r->fromLabel() }}</strong></li>
                <li><span>Needed by</span><strong>{{ $r->needed_by->format('l j F Y') }}</strong></li>
                @if ($r->event_on)
                    <li><span>Event</span><strong>{{ $r->event_on->format('l j F Y') }}</strong></li>
                @endif
                @if ($r->assignee)
                    <li><span>Working on it</span><strong>{{ $r->assignee->name }}</strong></li>
                @endif
                <li><span>Sent</span><strong>{{ $r->created_at->format('j M Y, g:i A') }}</strong></li>
            </ul>
            <p style="margin: 0; white-space: pre-line">{{ $r->details }}</p>
            @php($attached = $r->files->where('purpose', 'reference'))
            @if ($attached->isNotEmpty())
                <h3 style="margin: 4px 0 0; font-size: 15px">You attached</h3>
                @include('media-requests.files', ['files' => $attached])
            @endif
            @if ($r->isOpen())
                <form method="POST" action="{{ route('media-requests.respond', $r) }}" onsubmit="return confirm('Cancel this request?')">
                    @csrf
                    <button type="submit" name="answer" value="cancel" class="btn btn--outline btn--sm">Cancel request</button>
                </form>
            @endif
        </div>

        <div class="card notice-form__main" style="flex: none">
            <h2 class="h2 h2--sm" style="margin: 0">Messages</h2>
            @forelse ($comments as $c)
                <div class="package-row">
                    <span class="stack" style="gap: 2px">
                        <strong>{{ $c->author?->member?->name ?? $c->author?->name ?? 'Someone' }}{{ $c->user_id === $r->requested_by ? '' : ' · media team' }}</strong>
                        <span style="white-space: pre-line">{{ $c->body }}</span>
                    </span>
                    <span class="field-hint">{{ $c->created_at->format('j M, g:i A') }}</span>
                </div>
            @empty
                <p class="field-hint" style="margin: 0">No messages yet.</p>
            @endforelse
            @unless (in_array($r->status, ['cancelled']))
                <form method="POST" action="{{ route('media-requests.comment', $r) }}" class="notice-form__group">
                    @csrf
                    <label class="field">Send a message
                        <textarea name="body" required maxlength="3000" rows="3">{{ old('body') }}</textarea>
                    </label>
                    <button type="submit" class="btn btn--navy btn--sm" style="align-self: flex-start">Send</button>
                </form>
            @endunless
        </div>
    </section>
@endsection
