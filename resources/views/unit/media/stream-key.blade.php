@extends('layouts.unit', ['title' => 'Stream key', 'active' => 'setup'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <a href="{{ route('unit.setup.index', $unit) }}" class="box__link">← Service setup &amp; streaming</a>
            <h1>{{ $destination->label }}</h1>
            <span class="page-head__sub">Copy these into the streaming software. Anyone with the key can stream to the channel, so don't share it in chats.</span>
        </div>
    </header>
    <section class="box">
        <dl class="detail-list">
            <dt>Server</dt><dd style="font-family: ui-monospace, monospace">{{ $destination->server_url }}</dd>
            <dt>Stream key</dt><dd style="font-family: ui-monospace, monospace">{{ $destination->stream_key }}</dd>
        </dl>
        <div style="display: flex; gap: 8px; flex-wrap: wrap">
            <button type="button" class="btn btn--ghost btn--xs" data-copy="{{ $destination->server_url }}">Copy server</button>
            <button type="button" class="btn btn--navy btn--xs" data-copy="{{ $destination->stream_key }}">Copy key</button>
        </div>
    </section>
@endsection
