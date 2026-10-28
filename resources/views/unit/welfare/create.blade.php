@extends('layouts.unit', ['title' => 'Open a case', 'active' => 'welfare'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <a href="{{ route('unit.welfare.index', $unit) }}" class="box__link">← Welfare cases</a>
            <h1>Open a case</h1>
            <span class="page-head__sub">For a member, or someone outside the church the team is reaching out to</span>
        </div>
    </header>

    <form method="POST" action="{{ route('unit.welfare.store', $unit) }}" class="box form">
        @csrf
        <div class="form-row">
            <label class="field">Member
                <select name="member_id">
                    <option value="">Not a member</option>
                    @foreach ($members as $m)
                        <option value="{{ $m->id }}" @selected((int) old('member_id') === $m->id)>{{ $m->name }}</option>
                    @endforeach
                </select>
                @error('member_id') <span class="field-error">{{ $message }}</span> @enderror
            </label>
            <label class="field">Or their name <input type="text" name="beneficiary_name" value="{{ old('beneficiary_name') }}" maxlength="100"></label>
            <label class="field">Their phone <input type="tel" name="beneficiary_phone" value="{{ old('beneficiary_phone') }}" maxlength="40"></label>
        </div>
        @include('unit.welfare.fields')
        <div><button type="submit" class="btn btn--navy">Open case</button></div>
    </form>
@endsection
