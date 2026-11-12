<div class="form-row">
    <label class="field">Currency
        <select name="currency">
            @foreach (\App\Models\BankAccount::CURRENCIES as $code => [$name, $symbol])
                <option value="{{ $code }}" @selected(($a->currency ?? 'NGN') === $code)>{{ $code }} · {{ $name }}</option>
            @endforeach
        </select>
    </label>
    <label class="field">Bank <input type="text" name="bank_name" value="{{ $a->bank_name }}" required maxlength="100"></label>
</div>
<div class="form-row">
    <label class="field">Account name <input type="text" name="account_name" value="{{ $a->account_name ?? config('church.name') }}" required maxlength="150"></label>
    <label class="field">Account number <input type="text" name="account_number" value="{{ $a->account_number }}" required maxlength="40"></label>
</div>
<div class="form-row">
    <label class="field">Sort code (UK) <input type="text" name="sort_code" value="{{ $a->sort_code }}" maxlength="20"></label>
    <label class="field">Routing number (US) <input type="text" name="routing_number" value="{{ $a->routing_number }}" maxlength="20"></label>
</div>
<div class="form-row">
    <label class="field">SWIFT / BIC <input type="text" name="swift" value="{{ $a->swift }}" maxlength="20"></label>
    <label class="field">IBAN <input type="text" name="iban" value="{{ $a->iban }}" maxlength="40"></label>
</div>
<label class="field">Use for
    <select name="use_for">
        @foreach (\App\Models\BankAccount::USES as $key => $label)
            <option value="{{ $key }}" @selected(($a->use_for ?? 'all') === $key)>{{ $label }}</option>
        @endforeach
    </select>
</label>
<label class="field">Note for givers (optional) <input type="text" name="note" value="{{ $a->note }}" maxlength="200" placeholder="e.g. Domiciliary account. Use 'Tithe' and your name as the reference."></label>
<label class="login__remember"><input type="checkbox" name="is_active" value="1" @checked($a->is_active ?? true)>Show on the Give page</label>
