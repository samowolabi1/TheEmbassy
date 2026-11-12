{{-- The church's bank accounts in each currency, shown on the Give page. --}}
<section class="box box--flush" id="bank-accounts">
    <div class="box__head"><h2>Bank accounts</h2><span class="box__note">Shown on the Give page. Tithes and offerings are only received by transfer to these.</span></div>
    @forelse ($bankAccounts as $a)
        <details class="list-item list-item--edit" @if ($errors->getBag('account'.$a->id)->any()) open @endif>
            <summary>
                <span class="list-item__main">
                    <span class="list-item__title">{{ $a->currency }} · {{ $a->bank_name }} · {{ $a->account_number }}
                        @unless ($a->is_active) <span class="pill pill--draft">Hidden</span> @endunless
                    </span>
                    <span class="list-item__meta">{{ $a->account_name }} · {{ \App\Models\BankAccount::USES[$a->use_for] ?? '' }}</span>
                </span>
                <span class="btn btn--ghost btn--xs">Edit</span>
            </summary>
            <form method="POST" action="{{ route('admin.accounts.update', $a) }}" class="form">
                @csrf
                @method('PUT')
                @include('admin.partials.account-fields')
                @foreach ($errors->getBag('account'.$a->id)->all() as $error) <span class="field-error">{{ $error }}</span> @endforeach
                <div class="list-item__actions">
                    <button type="submit" class="btn btn--navy btn--xs">Save</button>
                    <button type="submit" form="delete-account-{{ $a->id }}" class="btn btn--danger btn--xs">Remove</button>
                </div>
            </form>
            <form id="delete-account-{{ $a->id }}" method="POST" action="{{ route('admin.accounts.destroy', $a) }}" data-confirm="Remove this account from the Give page?">
                @csrf
                @method('DELETE')
            </form>
        </details>
    @empty
        <p class="empty-state">No bank accounts yet. Add the church's accounts so people can give by transfer.</p>
    @endforelse
    <details class="list-item list-item--edit" @if ($errors->account->any()) open @endif>
        <summary><span class="list-item__main"><span class="list-item__title">+ Add an account</span><span class="list-item__meta">Naira, dollar, pound, euro and more</span></span></summary>
        <form method="POST" action="{{ route('admin.accounts.store') }}" class="form">
            @csrf
            @include('admin.partials.account-fields', ['a' => new \App\Models\BankAccount(['use_for' => 'all', 'is_active' => true])])
            @foreach ($errors->account->all() as $error) <span class="field-error">{{ $error }}</span> @endforeach
            <div><button type="submit" class="btn btn--navy btn--xs">Add account</button></div>
        </form>
    </details>
</section>
