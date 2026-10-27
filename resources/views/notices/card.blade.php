{{-- One notice on the board or the home page. --}}
<article @class(['notice', 'notice--featured' => $notice->featured]) data-filter-item data-tag="{{ $notice->category }}">
    @if ($notice->imageUrl())
        <img class="notice__image" src="{{ $notice->imageUrl() }}" alt="" loading="lazy">
    @endif
    <div class="notice__body">
        <div class="notice__tags">
            @if ($notice->featured)
                <span class="tag tag--gold">FEATURED</span>
            @endif
            <span class="tag tag--ice">{{ $notice->category }}</span>
        </div>
        <h3 class="notice__title">{{ $notice->title }}</h3>
        <p class="notice__text">{{ $notice->body }}</p>
        <div class="notice__foot">
            <span class="notice__from">{{ $notice->organisation }}@if ($notice->advertiser_type === 'church') <span class="notice__kind">· Church</span>@endif</span>
            @if ($notice->url)
                <a href="{{ $notice->url }}" class="btn btn--navy btn--sm" target="_blank" rel="noopener nofollow sponsored">Find out more<span class="sr-only"> about {{ $notice->title }}</span></a>
            @elseif ($notice->phone)
                <a href="tel:{{ preg_replace('/[^\d+]/', '', $notice->phone) }}" class="btn btn--outline btn--sm">Call {{ $notice->phone }}</a>
            @endif
        </div>
    </div>
</article>
