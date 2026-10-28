@extends('layouts.app', ['title' => 'Join the family', 'active' => null, 'description' => 'Become a member of '.config('church.short_name').'.'])

@section('content')
    <section class="container notices-hero stack" style="gap: 12px">
        <span class="eyebrow">MEMBERSHIP</span>
        <h1 class="display notices-hero__title">Join the family.</h1>
        <p class="lede" style="max-width: 680px">Get on the church register so we can welcome you properly, connect you to a unit and a house fellowship, and keep you in the loop.</p>
    </section>

    <section class="container section--tight section--last">
        <form method="POST" action="{{ route('join.store') }}" class="notice-form" novalidate>
            @csrf
            <div class="card notice-form__main">
                <fieldset class="notice-form__group">
                    <legend>About you</legend>
                    <div class="grid-auto" style="--min: 220px; --gap: 14px">
                        <label class="field">Full name
                            <input type="text" name="name" value="{{ old('name', auth()->user()?->name) }}" required maxlength="100" autocomplete="name">
                            @error('name') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                        <label class="field">Phone
                            <input type="tel" name="phone" value="{{ old('phone') }}" required maxlength="40" autocomplete="tel">
                            @error('phone') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                        <label class="field">Gender (optional)
                            <select name="gender">
                                <option value="">Prefer not to say</option>
                                <option value="female" @selected(old('gender') === 'female')>Female</option>
                                <option value="male" @selected(old('gender') === 'male')>Male</option>
                            </select>
                        </label>
                        <label class="field">Birthday (optional)
                            <input type="date" name="birthday" value="{{ old('birthday') }}" max="{{ today()->subDay()->toDateString() }}">
                            @error('birthday') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                    </div>
                    <label class="field">Address or area (optional)
                        <input type="text" name="address" value="{{ old('address') }}" maxlength="255" autocomplete="street-address">
                    </label>
                </fieldset>
                <fieldset class="notice-form__group">
                    <legend>Get connected (optional)</legend>
                    <div class="grid-auto" style="--min: 220px; --gap: 14px">
                        <label class="field">Unit you'd like to serve in
                            <select name="unit_id">
                                <option value="">Not sure yet</option>
                                @foreach ($units as $unit)
                                    <option value="{{ $unit->id }}" @selected((int) old('unit_id') === $unit->id)>{{ $unit->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="field">House fellowship near you
                            <select name="fellowship_id">
                                <option value="">Not sure yet</option>
                                @foreach ($fellowships as $fellowship)
                                    <option value="{{ $fellowship->id }}" @selected((int) old('fellowship_id') === $fellowship->id)>{{ $fellowship->name }}{{ $fellowship->area ? ' · '.$fellowship->area : '' }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                </fieldset>
            </div>

            <aside class="card notice-form__side">
                @guest
                    <fieldset class="notice-form__group">
                        <legend>Your account</legend>
                        <label class="field">Email
                            <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
                            @error('email') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                        <label class="field">Password
                            <input type="password" name="password" required minlength="10" autocomplete="new-password">
                            <span class="field-hint">At least 10 characters.</span>
                            @error('password') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                        <label class="field">Type it again
                            <input type="password" name="password_confirmation" required autocomplete="new-password">
                        </label>
                    </fieldset>
                @else
                    <p class="lede" style="margin: 0">You're signed in as {{ auth()->user()->email }}.</p>
                @endguest
                <label class="check">
                    <input type="checkbox" name="agree" value="1" @checked(old('agree'))>
                    <span>The church may keep these details to care for me and contact me. See the <a href="{{ route('privacy') }}" style="font-weight: 800">privacy notice</a>.</span>
                </label>
                @error('agree') <span class="field-error">{{ $message }}</span> @enderror
                <button type="submit" class="btn btn--gold">Join</button>
                @guest <a href="{{ route('login') }}" class="field-hint" style="text-align: center">Already have an account? Log in</a> @endguest
            </aside>
        </form>
    </section>
@endsection
