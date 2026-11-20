@extends('layouts.admin', ['title' => 'Contacts', 'active' => 'outreach'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <a href="{{ route('admin.outreach.index') }}" class="box__link">← Message centre</a>
            <h1>Contacts</h1>
            <span class="page-head__sub">Everyone the church can reach: members (all statuses), approved sellers and staff, with how to reach them and how many messages each has had.</span>
        </div>
        <div class="actions"><a href="{{ route('admin.outreach.create', ['to' => $type === 'everyone' ? 'members' : $type]) }}" class="btn btn--gold"><x-icon name="plus" :size="16" />New message</a></div>
    </header>

    <div class="tabs-row">
        <nav class="tabs" aria-label="Kind of contact">
            @foreach (['everyone' => 'Everyone', 'members' => 'Members', 'leaders' => 'Workers & ministers', 'sellers' => 'Sellers', 'staff' => 'Staff'] as $key => $label)
                <a href="{{ route('admin.outreach.contacts', ['type' => $key, 'q' => $q ?: null]) }}" class="tab" @if ($type === $key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
        <form method="GET" action="{{ route('admin.outreach.contacts') }}" class="search-inline" role="search">
            <input type="hidden" name="type" value="{{ $type }}">
            <label class="sr-only" for="contact-q">Search contacts</label>
            <input id="contact-q" type="search" name="q" value="{{ $q }}" placeholder="Name, email or phone" class="select-sm">
        </form>
    </div>

    <section class="box box--flush">
        <div class="table-wrap">
            <table class="table table--cards">
                <thead><tr><th>NAME</th><th>KIND</th><th>EMAIL</th><th>PHONE / WHATSAPP</th><th class="num">MESSAGES</th></tr></thead>
                <tbody>
                    @forelse ($people as $p)
                        @php($h = $history[$p['kind'].':'.$p['contact_id']] ?? null)
                        <tr>
                            <td style="font-weight: 700">
                                @if ($p['kind'] === 'member' && auth()->user()->canManage('members'))
                                    <a href="{{ route('admin.members.show', $p['contact_id']) }}">{{ $p['name'] }}</a>
                                @else
                                    {{ $p['name'] }}
                                @endif
                            </td>
                            <td>{{ ucfirst($p['kind']) }}@if (($p['rank'] ?? 'member') !== 'member') <span @class(['pill', 'pill--live' => $p['rank'] === 'minister', 'pill--ok' => $p['rank'] === 'worker'])>{{ \App\Models\Member::RANKS[$p['rank']] }}</span>@endif</td>
                            <td>@if ($p['email'])<a href="mailto:{{ $p['email'] }}">{{ $p['email'] }}</a>@else — @endif</td>
                            <td>
                                @if ($p['phone'])
                                    <a href="tel:{{ preg_replace('/\s+/', '', $p['phone']) }}">{{ $p['phone'] }}</a>
                                    @if (($n = \App\Models\Member::internationalPhone($p['phone'])) && $p['whatsapp_ok'])
                                        · <a href="https://wa.me/{{ $n }}" target="_blank" rel="noopener">WhatsApp</a>
                                    @elseif (! $p['whatsapp_ok']) · <span class="pill pill--draft">WhatsApp off</span>
                                    @endif
                                @else — @endif
                            </td>
                            <td class="num">{{ $h ? $h->messages.' · last '.\Illuminate\Support\Carbon::parse($h->last_at)->format('j M') : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-state">Nobody matches.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('admin.partials.pager', ['paginator' => $people])
    </section>
@endsection
