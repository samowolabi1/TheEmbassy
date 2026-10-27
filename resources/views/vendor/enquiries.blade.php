@extends('layouts.vendor', ['title' => 'Enquiries', 'active' => 'enquiries'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Enquiries</h1>
            <span class="page-head__sub">Messages from buyers. Reply to them directly; payment is between you and them.</span>
        </div>
    </header>

    <section class="box">
        @forelse ($enquiries as $enquiry)
            <article style="border: 1px solid var(--admin-line); border-radius: 20px; padding: 18px; display: flex; flex-direction: column; gap: 10px; {{ $enquiry->read_at ? 'opacity: .75' : '' }}">
                <div class="prayer-card__head">
                    <span class="prayer-card__who">{{ $enquiry->name }}</span>
                    @if ($enquiry->product)
                        <span class="pill pill--info">{{ $enquiry->product->name }}</span>
                    @endif
                    @if ($enquiry->preferred_date)
                        <span class="pill pill--ok">For {{ $enquiry->preferred_date->format('D j M') }}</span>
                    @endif
                    @unless ($enquiry->read_at)
                        <span class="pill pill--warn">New</span>
                    @endunless
                    <span class="prayer-card__when">{{ $enquiry->created_at->diffForHumans() }}</span>
                </div>
                <p class="prayer-card__text" style="white-space: pre-line">{{ $enquiry->message }}</p>
                <div class="list-item__actions" style="flex-wrap: wrap">
                    <a href="tel:{{ preg_replace('/[^\d+]/', '', $enquiry->phone) }}" class="btn btn--navy btn--xs">Call {{ $enquiry->phone }}</a>
                    @if ($enquiry->email)
                        <a href="mailto:{{ $enquiry->email }}?subject={{ rawurlencode('Re: '.($enquiry->product?->name ?? $shop->name)) }}" class="btn btn--ghost btn--xs">Email</a>
                    @endif
                    <form method="POST" action="{{ route('vendor.enquiries.read', $enquiry) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn btn--ghost btn--xs">{{ $enquiry->read_at ? 'Mark as new' : 'Mark as handled' }}</button>
                    </form>
                </div>
            </article>
        @empty
            <p class="empty-state">No enquiries yet. Buyers can message you from any of your listings.</p>
        @endforelse
        {{ $enquiries->links() }}
    </section>
@endsection
