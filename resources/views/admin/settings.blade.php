@extends('layouts.admin', ['title' => 'Settings & roles', 'active' => 'settings'])

@php($name = fn (string $key) => str_replace('.', '__', $key))

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Settings &amp; roles</h1>
            <span class="page-head__sub">Church details shown on the website, and who can use this dashboard</span>
        </div>
    </header>

    <section class="box box--flush">
        <div class="box__head">
            <h2>Staff</h2>
            <span class="box__note">Each role only sees its own areas. Administrators see everything, including this page.</span>
        </div>
        @error('staff') <p class="flash flash--error" role="alert" style="margin: 0 20px">{{ $message }}</p> @enderror
        @foreach ($staff as $person)
            <div class="list-item">
                <span class="avatar">{{ collect(explode(' ', $person->name))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('') }}</span>
                <span class="list-item__main">
                    <span class="list-item__title">{{ $person->name }}@if ($person->is(auth()->user())) <span class="pill pill--info">You</span>@endif</span>
                    <span class="list-item__meta" style="overflow-wrap: anywhere">{{ $person->email }}</span>
                </span>
                <span class="list-item__actions" style="flex-wrap: wrap">
                    <form method="POST" action="{{ route('admin.staff.update', $person) }}" style="display: flex; gap: 8px">
                        @csrf
                        @method('PATCH')
                        <label class="sr-only" for="role-{{ $person->id }}">Role for {{ $person->name }}</label>
                        <select id="role-{{ $person->id }}" name="role" class="select-sm">
                            @foreach ($roles as $key => $role)
                                <option value="{{ $key }}" @selected($person->role === $key)>{{ $role['name'] }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn--ghost btn--xs">Save</button>
                    </form>
                    @unless ($person->is(auth()->user()))
                        <form method="POST" action="{{ route('admin.staff.destroy', $person) }}" data-confirm="Remove {{ $person->name }}'s staff access?">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn--danger btn--xs">Remove<span class="sr-only"> {{ $person->name }}</span></button>
                        </form>
                    @endunless
                </span>
            </div>
        @endforeach
        <form method="POST" action="{{ route('admin.staff.store') }}" class="form" style="padding: 20px; border-top: 1px solid var(--admin-line)">
            @csrf
            <h3 style="margin: 0; font-size: 16px">Add staff</h3>
            <div class="form-row">
                <label class="field">Name
                    <input type="text" name="name" value="{{ old('name') }}" required maxlength="100">
                    @error('name') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <label class="field">Email
                    <input type="email" name="email" value="{{ old('email') }}" required>
                    @error('email') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <label class="field">Role
                    <select name="role" required>
                        @foreach ($roles as $key => $role)
                            <option value="{{ $key }}" @selected(old('role', 'secretary') === $key)>{{ $role['name'] }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
            <div><button type="submit" class="btn btn--navy">Add and email them</button></div>
        </form>
    </section>

    <section class="box box--flush">
        <div class="box__head"><h2>What each role can open</h2></div>
        <div class="table-wrap">
            <table class="table" style="min-width: 640px">
                <thead><tr><th>AREA</th>@foreach ($roles as $role)<th>{{ strtoupper($role['name']) }}</th>@endforeach</tr></thead>
                <tbody>
                    @foreach ($areas as $area => $label)
                        <tr>
                            <td style="font-weight: 700">{{ $label }}</td>
                            @foreach ($roles as $role)
                                <td>@if (in_array('*', $role['areas'], true) || in_array($area, $role['areas'], true))<span aria-label="Yes">✓</span>@else<span class="sr-only">No</span>@endif</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <form method="POST" action="{{ route('admin.settings.update') }}" class="stack" style="gap: 20px">
        @csrf
        @method('PUT')
        @foreach ($groups as $group)
            <section class="box form">
                <h2>{{ $group['title'] }}</h2>
                <div class="grid-auto" style="--min: 260px; --gap: 14px 18px">
                    @foreach ($group['fields'] as $key => $field)
                        @php($input = $name($key))
                        @if ($field['type'] === 'checkbox')
                            <label class="login__remember" style="align-self: end">
                                <input type="hidden" name="{{ $input }}" value="0">
                                <input type="checkbox" name="{{ $input }}" value="1" @checked(old($input, $values[$key]))> {{ $field['label'] }}
                            </label>
                        @elseif ($field['type'] === 'textarea')
                            <label class="field" style="grid-column: 1 / -1">{{ $field['label'] }}
                                <textarea name="{{ $input }}" maxlength="1000" style="height: 90px">{{ old($input, $values[$key]) }}</textarea>
                                @error($input) <span class="field-error">{{ $message }}</span> @enderror
                            </label>
                        @else
                            <label class="field">{{ $field['label'] }}
                                <input type="{{ $field['type'] }}" name="{{ $input }}" value="{{ old($input, $values[$key]) }}" maxlength="500">
                                @isset($field['hint']) <span class="field__hint">{{ $field['hint'] }}</span> @endisset
                                @error($input) <span class="field-error">{{ $message }}</span> @enderror
                            </label>
                        @endif
                    @endforeach
                </div>
            </section>
        @endforeach
        <div><button type="submit" class="btn btn--gold">Save settings</button></div>
    </form>
@endsection
