@extends('layouts.admin', ['title' => $member->exists ? 'Edit '.$member->name : 'Add member', 'active' => 'members'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <a href="{{ $member->exists ? route('admin.members.show', $member) : route('admin.members.index') }}" class="box__link">← {{ $member->exists ? $member->name : 'Members' }}</a>
            <h1>{{ $member->exists ? 'Edit profile' : 'Add member' }}</h1>
        </div>
    </header>

    <form method="POST" action="{{ $member->exists ? route('admin.members.update', $member) : route('admin.members.store') }}" class="row">
        @csrf
        @if ($member->exists) @method('PUT') @endif

        <section class="box grow form">
            <h2>Details</h2>
            <div class="form-row">
                <label class="field">Full name
                    <input type="text" name="name" value="{{ old('name', $member->name) }}" required maxlength="100">
                    @error('name') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <label class="field">Phone
                    <input type="tel" name="phone" value="{{ old('phone', $member->phone) }}" maxlength="40">
                    @error('phone') <span class="field-error">{{ $message }}</span> @enderror
                </label>
            </div>
            <div class="form-row">
                <label class="field">Email
                    <input type="email" name="email" value="{{ old('email', $member->email) }}">
                    @error('email') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <label class="field">Gender
                    <select name="gender">
                        <option value="">—</option>
                        <option value="female" @selected(old('gender', $member->gender) === 'female')>Female</option>
                        <option value="male" @selected(old('gender', $member->gender) === 'male')>Male</option>
                    </select>
                </label>
            </div>
            <div class="form-row">
                <label class="field">Birthday
                    <input type="date" name="birthday" value="{{ old('birthday', $member->birthday?->toDateString()) }}">
                    @error('birthday') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <label class="field">Member since
                    <input type="date" name="joined_on" value="{{ old('joined_on', $member->joined_on?->toDateString()) }}">
                    @error('joined_on') <span class="field-error">{{ $message }}</span> @enderror
                </label>
            </div>
            <label class="field">Address
                <input type="text" name="address" value="{{ old('address', $member->address) }}" maxlength="255">
            </label>
            <label class="field">Notes (staff only)
                <textarea name="notes" maxlength="2000">{{ old('notes', $member->notes) }}</textarea>
            </label>
        </section>

        <section class="box side-col form">
            <h2>Church life</h2>
            @if ($member->exists)
                <label class="field">Status
                    <select name="status">
                        @foreach ($statuses as $key => $label)
                            <option value="{{ $key }}" @selected(old('status', $member->status) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            <label class="field">Unit
                <select name="unit_id">
                    <option value="">None</option>
                    @foreach ($units as $unit)
                        <option value="{{ $unit->id }}" @selected((int) old('unit_id', $member->unit_id) === $unit->id)>{{ $unit->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field">House fellowship
                <select name="fellowship_id">
                    <option value="">None</option>
                    @foreach ($fellowships as $fellowship)
                        <option value="{{ $fellowship->id }}" @selected((int) old('fellowship_id', $member->fellowship_id) === $fellowship->id)>{{ $fellowship->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field">Token plan
                <select name="token_plan_id">
                    <option value="">{{ $plans->firstWhere('is_default', true) ? 'Default plan' : 'None' }}</option>
                    @foreach ($plans as $plan)
                        <option value="{{ $plan->id }}" @selected((int) old('token_plan_id', $member->token_plan_id) === $plan->id)>{{ $plan->name }} · {{ $plan->amountLabel() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field">Tokens due from
                <input type="month" name="tokens_from" value="{{ old('tokens_from', $member->tokens_from?->format('Y-m')) }}">
                <span class="field__hint">Leave empty to start from the month they're approved.</span>
            </label>
            <fieldset class="field" style="border: 0; padding: 0; margin: 0">
                <legend style="margin-bottom: 6px">Milestones</legend>
                @foreach (\App\Models\Member::MILESTONES as $field => $label)
                    <label class="login__remember"><input type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $member->{$field}))>{{ $label }}</label>
                @endforeach
            </fieldset>
            <button type="submit" class="btn btn--navy">{{ $member->exists ? 'Save' : 'Add member' }}</button>
        </section>
    </form>
@endsection
