{{-- One of the church's bank accounts, with copy buttons. --}}
<div class="card way bank-card" data-filter-item data-tag="{{ $account->currency }}" @if ($hidden) hidden @endif>
    <span class="bank-card__head">
        <span class="bank-card__currency">{{ $account->symbol() }} {{ $account->currency }}</span>
        <span class="bank-card__use">{{ ['all' => 'Tithes, offerings & donations', 'tithe' => 'Tithes & offerings', 'donations' => 'Donations'][$account->use_for] ?? '' }}</span>
    </span>
    <span class="way__title">{{ $account->bank_name }}</span>
    <dl class="bank-card__facts">
        <dt>Account name</dt><dd>{{ $account->account_name }}</dd>
        <dt>Account number</dt>
        <dd><span class="way__account">{{ $account->account_number }}</span>
            <button type="button" class="bank-card__copy" data-copy="{{ $account->account_number }}" aria-label="Copy account number"><x-icon name="link" :size="14" /> Copy</button></dd>
        @if ($account->sort_code)<dt>Sort code</dt><dd>{{ $account->sort_code }}</dd>@endif
        @if ($account->routing_number)<dt>Routing number</dt><dd>{{ $account->routing_number }}</dd>@endif
        @if ($account->swift)<dt>SWIFT / BIC</dt><dd>{{ $account->swift }} <button type="button" class="bank-card__copy" data-copy="{{ $account->swift }}" aria-label="Copy SWIFT code">Copy</button></dd>@endif
        @if ($account->iban)<dt>IBAN</dt><dd style="overflow-wrap: anywhere">{{ $account->iban }} <button type="button" class="bank-card__copy" data-copy="{{ $account->iban }}" aria-label="Copy IBAN">Copy</button></dd>@endif
    </dl>
    @if ($account->note)
        <span class="way__text">{{ $account->note }}</span>
    @endif
</div>
