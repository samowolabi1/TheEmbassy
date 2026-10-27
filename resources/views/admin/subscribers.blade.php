@extends('layouts.admin', ['title' => 'Newsletter subscribers', 'active' => 'subscribers'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Newsletter subscribers</h1>
            <span class="page-head__sub">{{ number_format($total) }} {{ \Illuminate\Support\Str::plural('person', $total) }} signed up for the weekly declaration email</span>
        </div>
        <div class="actions">
            <a href="{{ route('admin.subscribers.export') }}" class="btn btn--outline">Download CSV</a>
        </div>
    </header>

    <section class="box box--flush">
        <form method="GET" action="{{ route('admin.subscribers.index') }}" class="box__head" role="search">
            <label class="field" style="flex: 1 1 260px; font-size: 13px">
                <span class="sr-only">Search by email</span>
                <input type="search" name="q" value="{{ $search }}" placeholder="Search by email"
                    style="height: 44px; border-radius: 12px; border: 1px solid var(--admin-line); background: var(--white); padding: 0 14px; font-size: 14px">
            </label>
            <button type="submit" class="btn btn--ghost btn--xs" style="height: 44px">Search</button>
        </form>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>EMAIL</th><th>SIGNED UP</th><th class="num"><span class="sr-only">Actions</span></th></tr>
                </thead>
                <tbody>
                    @forelse ($subscribers as $s)
                        <tr>
                            <td style="font-weight: 700; overflow-wrap: anywhere">{{ $s->email }}</td>
                            <td style="color: var(--slate); white-space: nowrap">{{ $s->created_at->format('j M Y') }}</td>
                            <td class="num">
                                <form method="POST" action="{{ route('admin.subscribers.destroy', $s) }}" data-confirm="Remove {{ $s->email }} from the list?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn--danger btn--xs">Remove<span class="sr-only"> {{ $s->email }}</span></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3"><p class="empty-state">{{ $search ? 'No subscribers match that search.' : 'No subscribers yet.' }}</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('admin.partials.pager', ['paginator' => $subscribers])
    </section>
    <p class="box__note" style="margin: 0">Sending the weekly email isn't built in yet. Download the CSV to import the list into an email service such as Mailchimp or Brevo.</p>
@endsection
