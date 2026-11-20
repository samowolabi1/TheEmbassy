@extends('layouts.admin', ['title' => 'Register someone', 'active' => 'register'])

@section('content')
    @php
        // Refill only the form that was sent, as both have a name and phone.
        $o = fn (string $form, string $key, $default = null) => old('_form') === $form ? old($key, $default) : $default;
    @endphp
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Register someone</h1>
            <span class="page-head__sub">Fill in a welcome card for a first-timer, or put a new member on the register. Any staff member can do this.@if ($today) {{ $today }} first-{{ \Illuminate\Support\Str::plural('timer', $today) }} today.@endif</span>
        </div>
    </header>

    <div class="row" style="align-items: flex-start">
        <section class="box grow" id="visitor">
            <h2>A first-timer <x-tip text="Visitors can also fill this in themselves at /welcome (put the QR code at the door). The care team is asked to call them within a couple of days. If they've visited before (same phone or email), it becomes a second-visit follow-up instead, inviting them to join." /></h2>
            <form method="POST" action="{{ route('admin.register.first-timer') }}" class="form">
                @csrf
                <input type="hidden" name="_form" value="visitor">
                <div class="form-row">
                    <label class="field">Name <input type="text" name="name" value="{{ $o('visitor', 'name') }}" required maxlength="100" autocomplete="off"></label>
                    <label class="field">Phone <input type="tel" name="phone" value="{{ $o('visitor', 'phone') }}" required maxlength="40"></label>
                </div>
                @foreach (['name', 'phone', 'email', 'visited_on'] as $f)
                    @error($f, 'visitor') <span class="field-error">{{ $message }}</span> @enderror
                @endforeach
                <div class="form-row">
                    <label class="field">Email (optional) <input type="email" name="email" value="{{ $o('visitor', 'email') }}" maxlength="255"></label>
                    <label class="field">Visited on <input type="date" name="visited_on" value="{{ $o('visitor', 'visited_on', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required></label>
                </div>
                <label class="field">Invited by (optional) <input type="text" name="invited_by" value="{{ $o('visitor', 'invited_by') }}" maxlength="100"></label>
                <label class="field">Notes (optional) <textarea name="notes" rows="2" maxlength="1000" placeholder="Prayer request, where they live, how they heard about us…">{{ $o('visitor', 'notes') }}</textarea></label>
                <label class="login__remember"><input type="checkbox" name="wants_to_join" value="1" @checked($o('visitor', 'wants_to_join'))>They'd like to become a member</label>
                <div><button type="submit" class="btn btn--navy">Add welcome card</button></div>
            </form>
        </section>

        <section class="box grow" id="member">
            <h2>A new member <x-tip text="{{ $approves ? 'You look after the register, so they\'re approved straight away and get a welcome follow-up.' : 'They wait under Members › Waiting for approval until the church office approves them. A unit you choose is sent as a request.' }}" /></h2>
            <form method="POST" action="{{ route('admin.register.member') }}" class="form">
                @csrf
                <input type="hidden" name="_form" value="member">
                <div class="form-row">
                    <label class="field">Full name <input type="text" name="name" value="{{ $o('member', 'name') }}" required maxlength="100" autocomplete="off"></label>
                    <label class="field">Phone <input type="tel" name="phone" value="{{ $o('member', 'phone') }}" required maxlength="40"></label>
                </div>
                @foreach (['name', 'phone', 'email', 'birthday'] as $f)
                    @error($f, 'member') <span class="field-error">{{ $message }}</span> @enderror
                @endforeach
                <div class="form-row">
                    <label class="field">Email (optional) <input type="email" name="email" value="{{ $o('member', 'email') }}" maxlength="255"></label>
                    <label class="field">Gender
                        <select name="gender">
                            <option value="">Not given</option>
                            <option value="female" @selected($o('member', 'gender') === 'female')>Female</option>
                            <option value="male" @selected($o('member', 'gender') === 'male')>Male</option>
                        </select>
                    </label>
                    <x-birthday-input old-form="member" />
                </div>
                <label class="field">Address or area (optional) <input type="text" name="address" value="{{ $o('member', 'address') }}" maxlength="255"></label>
                <div class="form-row">
                    <label class="field">House fellowship
                        <select name="fellowship_id">
                            <option value="">None yet</option>
                            @foreach ($fellowships as $f)
                                <option value="{{ $f->id }}" @selected((int) $o('member', 'fellowship_id') === $f->id)>{{ $f->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="field">Unit
                        <select name="unit_id">
                            <option value="">None yet</option>
                            @foreach ($units as $u)
                                <option value="{{ $u->id }}" @selected((int) $o('member', 'unit_id') === $u->id)>{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                @if ($approves)
                    @include('admin.members._login-choice')
                @else
                    <span class="field__hint">The church office approves new members and gives them a login.</span>
                @endif
                <div><button type="submit" class="btn btn--navy">Add to the register</button></div>
            </form>
        </section>
    </div>
@endsection
