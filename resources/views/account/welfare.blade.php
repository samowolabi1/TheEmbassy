@extends('layouts.app', ['title' => 'Welfare', 'active' => null])

@section('content')
    <section class="container notices-hero stack" style="gap: 12px">
        <a href="{{ route('account') }}" class="back-link"><x-icon name="chevron-left" :size="16" />My account</a>
        <span class="eyebrow">WELFARE</span>
        <h1 class="display notices-hero__title">We're here for you.</h1>
        <p class="lede" style="max-width: 680px">Going through something? Let the welfare team know. Your request is confidential: only the welfare team and pastors see it.</p>
    </section>

    <section class="container section--tight section--last stack" style="gap: 20px">
        @if (session('status'))
            <p class="notice-alert" role="status" style="margin: 0">{{ session('status') }}</p>
        @endif

        @if ($assigned->isNotEmpty())
            <div class="card notice-form__main" style="flex: none">
                <h2 class="h2 h2--sm" style="margin: 0">Cases you're helping with</h2>
                <ul class="mini-list">
                    @foreach ($assigned as $c)
                        <li><a href="{{ route('account.welfare.case', $c) }}" style="font-weight: 700">{{ $c->personName() }} · {{ $c->summary }}</a><span>{{ $c->reference }}</span></li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($requests->isNotEmpty())
            <div class="card notice-form__main" style="flex: none">
                <h2 class="h2 h2--sm" style="margin: 0">Your requests</h2>
                <ul class="mini-list">
                    @foreach ($requests as $r)
                        <li><span>{{ $r->summary }} · {{ $r->created_at->format('j M Y') }}</span><strong>{{ $r->requesterStatus() }}</strong></li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($member->status === 'active')
            <form method="POST" action="{{ route('account.welfare.store') }}" class="card notice-form__main" style="flex: none">
                @csrf
                <fieldset class="notice-form__group">
                    <legend>Ask for help</legend>
                    @include('unit.welfare.fields')
                    <button type="submit" class="btn btn--gold" style="align-self: flex-start">Send to the welfare team</button>
                </fieldset>
            </form>
        @else
            <p class="lede">Once your membership is confirmed you can make a request here. If it's urgent, please speak to a pastor.</p>
        @endif
    </section>
@endsection
