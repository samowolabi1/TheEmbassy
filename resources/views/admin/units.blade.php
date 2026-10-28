@extends('layouts.admin', ['title' => 'Units & fellowships', 'active' => 'units'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Units &amp; fellowships</h1>
            <span class="page-head__sub">Where members serve and where they meet during the week. Members pick from these when they join.</span>
        </div>
    </header>

    <div class="row">
        <section class="box box--flush grow">
            <div class="box__head"><h2>Units</h2></div>
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
                                        <option value="{{ $m->id }}" @selected($unit->hod_member_id === $m->id)>{{ $m->name }}</option>
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
                        @error('assistant_member_id', 'unit') <span class="field-error">{{ $message }}</span> @enderror
                        <span class="field__hint">The HOD and assistant sign in with their member account to run the unit's area. Welfare and media units get their own tools.</span>
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
            <form method="POST" action="{{ route('admin.units.store') }}" class="form" style="padding: 20px; border-top: 1px solid var(--admin-line)">
                @csrf
                <h3 style="margin: 0; font-size: 15px">Add a unit</h3>
                <div class="form-row">
                    <label class="field">Name <input type="text" name="name" value="{{ old('name') }}" required maxlength="60" placeholder="e.g. Choir"></label>
                    <label class="field">Leader (optional) <input type="text" name="leader_name" maxlength="100"></label>
                </div>
                @error('name', 'unit') <span class="field-error">{{ $message }}</span> @enderror
                <div><button type="submit" class="btn btn--navy btn--xs">Add unit</button></div>
            </form>
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
