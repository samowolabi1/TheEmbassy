{{-- A programme's header and tabs: applications, and settings, banner, choices and form. --}}
<header class="page-head">
    <div class="stack" style="gap: 4px">
        <a href="{{ route('admin.empowerment.index') }}" class="box__link">← Youth empowerment</a>
        <h1>{{ $programme->title }}</h1>
        <span class="page-head__sub">
            <span @class(['pill', 'pill--ok' => $programme->isOpen(), 'pill--draft' => ! $programme->is_published, 'pill--warn' => $programme->is_published && ! $programme->isOpen()])>{{ $programme->statusLabel() }}</span>
            @if ($programme->opens_on || $programme->closes_on)
                Applications {{ $programme->opens_on ? 'from '.$programme->opens_on->format('j M Y') : '' }}{{ $programme->closes_on ? ' until '.$programme->closes_on->format('j M Y') : '' }}.
            @endif
            @if ($programme->ageRule()) {{ $programme->ageRule() }}.@endif
        </span>
    </div>
    <div class="actions">
        <a href="{{ route('empowerment.show', $programme) }}" class="btn btn--outline" target="_blank" rel="noopener">{{ $programme->is_published ? 'View page' : 'Preview page' }}</a>
        <a href="{{ route('admin.empowerment.export', $programme) }}" class="btn btn--gold"><x-icon name="download" :size="16" />Download Excel</a>
    </div>
</header>
<nav class="tabs" aria-label="{{ $programme->title }}">
    <a href="{{ route('admin.empowerment.show', $programme) }}" class="tab" @if ($tab === 'applications') aria-current="page" @endif>Applications <span>{{ $programme->applications()->count() }}</span></a>
    <a href="{{ route('admin.empowerment.edit', $programme) }}" class="tab" @if ($tab === 'settings') aria-current="page" @endif>Page, choices &amp; form</a>
</nav>
