@extends('layouts.admin', ['title' => 'Youth empowerment', 'active' => 'empowerment'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Youth empowerment</h1>
            <span class="page-head__sub">Programmes young people apply to on the website, at <a href="{{ route('empowerment.index') }}" target="_blank" rel="noopener" style="font-weight: 700">{{ str_replace(['https://', 'http://'], '', route('empowerment.index')) }}</a>. Each has its own banner, the choices on offer, and a form you design.</span>
        </div>
        <div class="actions"><a href="#new-programme" class="btn btn--gold"><x-icon name="plus" :size="16" />New programme</a></div>
    </header>

    <section class="box box--flush">
        @forelse ($programmes as $p)
            <div class="list-item" style="flex-wrap: wrap">
                <span class="list-item__main" style="flex: 1 1 260px">
                    <span class="list-item__title"><a href="{{ route('admin.empowerment.show', $p) }}">{{ $p->title }}</a>
                        <span @class(['pill', 'pill--ok' => $p->isOpen(), 'pill--draft' => ! $p->is_published, 'pill--warn' => $p->is_published && ! $p->isOpen()])>{{ $p->statusLabel() }}</span>
                    </span>
                    <span class="list-item__meta">{{ $p->applications_count }} {{ \Illuminate\Support\Str::plural('application', $p->applications_count) }}@if ($p->new_count) · <strong>{{ $p->new_count }} new</strong>@endif · {{ $p->tracks_count }} {{ \Illuminate\Support\Str::plural('choice', $p->tracks_count) }}@if ($p->closes_on) · closes {{ $p->closes_on->format('j M Y') }}@endif</span>
                </span>
                <span class="list-item__actions">
                    <a href="{{ route('admin.empowerment.show', $p) }}" class="btn btn--navy btn--xs">Applications</a>
                    <a href="{{ route('admin.empowerment.edit', $p) }}" class="btn btn--ghost btn--xs">Page, choices &amp; form</a>
                </span>
            </div>
        @empty
            <p class="empty-state">No programmes yet. Start one below: give it a name, then add a banner, the choices on offer and the form.</p>
        @endforelse
    </section>

    <section class="box" id="new-programme">
        <h2>New programme <x-tip text="It starts as a draft with a few sample questions, ages 16 to 35 and up to two choices each. Change anything before you publish it." /></h2>
        <form method="POST" action="{{ route('admin.empowerment.store') }}" class="form" style="flex-direction: row; flex-wrap: wrap; align-items: flex-end">
            @csrf
            <label class="field" style="flex: 1 1 280px">Name
                <input type="text" name="title" value="{{ old('title') }}" required maxlength="120" placeholder="e.g. Youth Empowerment 2027">
                @error('title') <span class="field-error">{{ $message }}</span> @enderror
            </label>
            <button type="submit" class="btn btn--navy">Create</button>
        </form>
    </section>
@endsection
