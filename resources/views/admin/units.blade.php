@extends('layouts.admin', ['title' => 'Units & fellowships', 'active' => 'units'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Units &amp; fellowships</h1>
            <span class="page-head__sub">Where members serve and where they meet during the week. Members ask to join a unit; you approve it here.</span>
        </div>
        <div class="actions">
            <a href="#new-unit" class="btn btn--gold" onclick="document.getElementById('new-unit').open = true">+ New unit</a>
        </div>
    </header>

    @if ($requests->isNotEmpty())
        <section class="box box--flush" id="requests">
            <div class="box__head"><h2>Asking to join a unit <span class="pill pill--info">{{ $requests->count() }}</span> <x-tip text="Members ask to join a unit from their account (or when they sign up). Approving puts them in the unit, which makes them a worker, with their answers about their classes. Declining tells them, with your reason." /></h2></div>
            @foreach ($requests as $req)
                <div class="list-item" style="flex-wrap: wrap; gap: 12px">
                    <span class="list-item__main" style="flex: 1 1 280px">
                        <span class="list-item__title"><a href="{{ route('admin.members.show', $req->member) }}">{{ $req->member->name }}</a> → {{ $req->unit->name }}</span>
                        <span class="list-item__meta">
                            Asked {{ $req->created_at->diffForHumans() }}
                            @if ($req->member->status !== 'active') · <strong>membership not approved yet</strong>@endif
                            @if ($req->member->units->isNotEmpty()) · already in {{ $req->member->unitNames() }}@endif
                            @if ($req->training)
                                @php
                                    $notYet = collect($req->training)->filter(fn ($v) => ! $v)->keys()->map(fn ($f) => \App\Models\Member::MILESTONES[$f] ?? $f);
                                @endphp
                                · {{ $notYet->isEmpty() ? 'all workers\' classes done' : 'not done: '.$notYet->join(', ') }}
                            @else
                                · classes not answered yet
                            @endif
                        </span>
                        @if ($req->message)<span class="box__note" style="margin: 4px 0 0">“{{ $req->message }}”</span>@endif
                    </span>
                    <span style="display: flex; gap: 8px; flex-wrap: wrap; align-items: flex-start">
                        <form method="POST" action="{{ route('admin.unit-requests.approve', $req) }}">
                            @csrf
                            <button type="submit" class="btn btn--navy btn--xs">Approve<span class="sr-only">: {{ $req->member->name }} to {{ $req->unit->name }}</span></button>
                        </form>
                        <details class="training-edit" style="margin: 0">
                            <summary class="btn btn--ghost btn--xs">Decline</summary>
                            <form method="POST" action="{{ route('admin.unit-requests.decline', $req) }}" class="form" style="gap: 8px; margin-top: 8px; min-width: 240px">
                                @csrf
                                <label class="field">Reason they'll see (optional) <input type="text" name="reason" maxlength="300" placeholder="e.g. The choir isn't taking new members until January."></label>
                                <button type="submit" class="btn btn--danger btn--xs" style="align-self: flex-start">Decline</button>
                            </form>
                        </details>
                    </span>
                </div>
            @endforeach
        </section>
    @endif

    <div class="row">
        <section class="box box--flush grow">
            <div class="box__head"><h2>Units</h2></div>
            <details class="list-item list-item--edit" id="new-unit" @if ($errors->unit->any() && old('_new')) open @endif>
                <summary>
                    <span class="list-item__main"><span class="list-item__title">+ New unit</span><span class="list-item__meta">Add a unit members can serve in</span></span>
                    <span class="btn btn--gold btn--xs">Add</span>
                </summary>
                <form method="POST" action="{{ route('admin.units.store') }}" class="form">
                    @csrf
                    <input type="hidden" name="_new" value="1">
                    <div class="form-row">
                        <label class="field">Name <input type="text" name="name" value="{{ old('name') }}" required maxlength="60" placeholder="e.g. Children's church"></label>
                        <label class="field">Type
                            <select name="kind">
                                @foreach ($kinds as $key => $label)
                                    <option value="{{ $key }}" @selected(old('kind', 'general') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                    <div class="form-row">
                        <label class="field">HOD (optional)
                            <select name="hod_member_id">
                                <option value="">Not set</option>
                                @foreach ($members as $m)
                                    <option value="{{ $m->id }}" @selected((int) old('hod_member_id') === $m->id) @disabled(isset($heads[$m->id]))>{{ $m->name }}@if (isset($heads[$m->id])) (heads {{ $units->firstWhere('id', $heads[$m->id])?->name }})@endif</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="field">Assistant HOD (optional)
                            <select name="assistant_member_id">
                                <option value="">Not set</option>
                                @foreach ($members as $m)
                                    <option value="{{ $m->id }}" @selected((int) old('assistant_member_id') === $m->id)>{{ $m->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                    <label class="field">What they do (optional) <input type="text" name="description" value="{{ old('description') }}" maxlength="500" placeholder="Members see this when they ask to join"></label>
                    @foreach (['name', 'hod_member_id', 'assistant_member_id'] as $f)
                        @error($f, 'unit') <span class="field-error">{{ $message }}</span> @enderror
                    @endforeach
                    <div class="list-item__actions"><button type="submit" class="btn btn--navy btn--xs">Add unit</button></div>
                </form>
            </details>
            @foreach ($units as $unit)
                <details class="list-item list-item--edit">
                    <summary>
                        <span class="list-item__main">
                            <span class="list-item__title">{{ $unit->name }}</span>
                            <span class="list-item__meta">{{ $unit->members_count }} {{ \Illuminate\Support\Str::plural('member', $unit->members_count) }}{{ $unit->hod ? ' · HOD '.$unit->hod->name : ($unit->leader_name ? ' · Led by '.$unit->leader_name : ' · No HOD yet') }}{{ $unit->kind !== 'general' ? ' · '.$kinds[$unit->kind].' tools' : '' }}</span>
                        </span>
                        <a href="{{ route('unit.show', $unit) }}" class="btn btn--ghost btn--xs">Open unit area</a>
                        <span class="btn btn--ghost btn--xs">Edit</span>
                    </summary>
                    <form method="POST" action="{{ route('admin.units.update', $unit) }}" class="form">
                        @csrf
                        @method('PUT')
                        <div class="form-row">
                            <label class="field">Name <input type="text" name="name" value="{{ $unit->name }}" required maxlength="60"></label>
                            <label class="field">Leader <input type="text" name="leader_name" value="{{ $unit->leader_name }}" maxlength="100"></label>
                        </div>
                        <div class="form-row">
                            <label class="field">HOD
                                <select name="hod_member_id">
                                    <option value="">Not set</option>
                                    @foreach ($members as $m)
                                        <option value="{{ $m->id }}" @selected($unit->hod_member_id === $m->id) @disabled(isset($heads[$m->id]) && $heads[$m->id] !== $unit->id)>{{ $m->name }}@if (isset($heads[$m->id]) && $heads[$m->id] !== $unit->id) (heads {{ $units->firstWhere('id', $heads[$m->id])?->name }})@endif</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="field">Assistant HOD
                                <select name="assistant_member_id">
                                    <option value="">Not set</option>
                                    @foreach ($members as $m)
                                        <option value="{{ $m->id }}" @selected($unit->assistant_member_id === $m->id)>{{ $m->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="field">Type
                                <select name="kind">
                                    @foreach ($kinds as $key => $label)
                                        <option value="{{ $key }}" @selected($unit->kind === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                        @error('hod_member_id', 'unit') <span class="field-error">{{ $message }}</span> @enderror
                        @error('assistant_member_id', 'unit') <span class="field-error">{{ $message }}</span> @enderror
                        <span class="field__hint">Someone can head one unit only; a minister can head a unit too. The HOD and assistant are added to the unit (so they're workers) and sign in with their member account to run the unit's area. Welfare and media units get their own tools.</span>
                        <label class="field">What they do <input type="text" name="description" value="{{ $unit->description }}" maxlength="500"></label>
                        <div class="list-item__actions">
                            <button type="submit" class="btn btn--navy btn--xs">Save</button>
                            <button type="submit" form="delete-unit-{{ $unit->id }}" class="btn btn--danger btn--xs">Remove</button>
                        </div>
                    </form>
                    <form id="delete-unit-{{ $unit->id }}" method="POST" action="{{ route('admin.units.destroy', $unit) }}" data-confirm="Remove {{ $unit->name }}? Its members stay on the register without a unit.">
                        @csrf
                        @method('DELETE')
                    </form>
                </details>
            @endforeach
        </section>

        <section class="box box--flush grow">
            <div class="box__head"><h2>House fellowships</h2></div>
            @foreach ($fellowships as $fellowship)
                <details class="list-item list-item--edit">
                    <summary>
                        <span class="list-item__main">
                            <span class="list-item__title">{{ $fellowship->name }}</span>
                            <span class="list-item__meta">{{ $fellowship->members_count }} {{ \Illuminate\Support\Str::plural('member', $fellowship->members_count) }}{{ $fellowship->area ? ' · '.$fellowship->area : '' }}{{ $fellowship->meets ? ' · '.$fellowship->meets : '' }}</span>
                        </span>
                        <span class="btn btn--ghost btn--xs">Edit</span>
                    </summary>
                    <form method="POST" action="{{ route('admin.fellowships.update', $fellowship) }}" class="form">
                        @csrf
                        @method('PUT')
                        <div class="form-row">
                            <label class="field">Name <input type="text" name="name" value="{{ $fellowship->name }}" required maxlength="60"></label>
                            <label class="field">Host <input type="text" name="host_name" value="{{ $fellowship->host_name }}" maxlength="100"></label>
                        </div>
                        <div class="form-row">
                            <label class="field">Area <input type="text" name="area" value="{{ $fellowship->area }}" maxlength="100"></label>
                            <label class="field">Meets <input type="text" name="meets" value="{{ $fellowship->meets }}" maxlength="100" placeholder="e.g. Thursdays, 6 PM"></label>
                        </div>
                        <div class="list-item__actions">
                            <button type="submit" class="btn btn--navy btn--xs">Save</button>
                            <button type="submit" form="delete-fellowship-{{ $fellowship->id }}" class="btn btn--danger btn--xs">Remove</button>
                        </div>
                    </form>
                    <form id="delete-fellowship-{{ $fellowship->id }}" method="POST" action="{{ route('admin.fellowships.destroy', $fellowship) }}" data-confirm="Remove {{ $fellowship->name }}? Its members stay on the register.">
                        @csrf
                        @method('DELETE')
                    </form>
                </details>
            @endforeach
            <form method="POST" action="{{ route('admin.fellowships.store') }}" class="form" style="padding: 20px; border-top: 1px solid var(--admin-line)">
                @csrf
                <h3 style="margin: 0; font-size: 15px">Add a house fellowship</h3>
                <div class="form-row">
                    <label class="field">Name <input type="text" name="name" required maxlength="60" placeholder="e.g. House 1 · Yaba"></label>
                    <label class="field">Area (optional) <input type="text" name="area" maxlength="100"></label>
                </div>
                @error('name', 'fellowship') <span class="field-error">{{ $message }}</span> @enderror
                <div><button type="submit" class="btn btn--navy btn--xs">Add fellowship</button></div>
            </form>
        </section>
    </div>
@endsection
