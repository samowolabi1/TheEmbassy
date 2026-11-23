{{-- One worker's permission request, for a unit head or a pastor. $p, $action (the answer URL), $showUnits. --}}
@php
    $onRota = ($rota ?? collect())->get($p->service_id.'-'.$p->member_id, collect());
@endphp
<article class="prayer-card" id="permission-{{ $p->id }}">
    <div class="prayer-card__head">
        <span class="prayer-card__who">{{ $p->member->name }}</span>
        <span class="pill {{ match ($p->status) { 'pending' => 'pill--gold', 'granted' => 'pill--ok', default => 'pill--draft' } }}">{{ $p->statusLabel() }}</span>
        <span class="prayer-card__when">Asked {{ $p->created_at->diffForHumans() }}</span>
    </div>
    <p class="prayer-card__text" style="margin: 0"><strong>{{ $p->summary() }}</strong>@if (($showUnits ?? false) && $p->member->units->isNotEmpty()) · {{ $p->member->units->pluck('name')->join(', ') }}@endif</p>
    <p class="report-quote" style="margin: 0; white-space: pre-line">{{ $p->reason }}</p>
    @if ($onRota->isNotEmpty())
        <p class="box__note" style="margin: 0; color: var(--warn); font-weight: 700">On the rota for this service: {{ $onRota->map(fn ($d) => $d->role.($d->unit ? ' ('.$d->unit->name.')' : ''))->join(', ') }}. Arrange cover.</p>
    @endif
    @if ($p->member->phone)
        <p class="box__note" style="margin: 0"><a href="tel:{{ preg_replace('/\s+/', '', $p->member->phone) }}">{{ $p->member->phone }}</a></p>
    @endif
    @if ($p->answeredBy)
        <p class="box__note" style="margin: 0">{{ $p->status === 'granted' ? 'Granted' : 'Not granted' }} by {{ $p->answeredBy->communityName() }} {{ $p->answered_at?->diffForHumans() }}@if ($p->answer_note): "{{ $p->answer_note }}"@endif</p>
    @endif
    @if (in_array($p->status, ['pending', 'granted', 'declined'], true) && $p->on_date->gte(today()))
        <form method="POST" action="{{ $action }}" class="form permission-answer">
            @csrf
            <label class="field"><span>Note to {{ $p->member->firstName() }} (optional)</span>
                <input type="text" name="note" maxlength="500" value="{{ $p->answer_note }}" placeholder="e.g. Granted. Bro. Femi will cover the camera.">
            </label>
            <div style="display: flex; gap: 8px; flex-wrap: wrap">
                @if ($p->status !== 'granted')
                    <button type="submit" name="status" value="granted" class="btn btn--gold btn--xs">Grant</button>
                @endif
                @if ($p->status !== 'declined')
                    <button type="submit" name="status" value="declined" class="btn btn--ghost btn--xs">{{ $p->status === 'granted' ? 'Withdraw permission' : 'Decline' }}</button>
                @endif
            </div>
        </form>
    @endif
</article>
