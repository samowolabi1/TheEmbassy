{{-- Giving someone a login when they're added: a link, a temporary password, or not yet. --}}
<fieldset class="field" style="border: 0; padding: 0; margin: 0">
    <legend style="margin-bottom: 6px">Their login to the app <x-tip text="They sign in with their email or phone number. A link (by email, and WhatsApp if it's on) lets them choose a password and works for 7 days. A temporary password is shown to you once to pass on; they choose their own when they first sign in." /></legend>
    @foreach (['link' => 'Send them a link to choose a password (needs an email)', 'temporary' => 'Give me a temporary password to pass on', 'none' => 'Not now'] as $value => $label)
        <label class="login__remember"><input type="radio" name="login" value="{{ $value }}" @checked(old('login', 'link') === $value)>{{ $label }}</label>
    @endforeach
</fieldset>
