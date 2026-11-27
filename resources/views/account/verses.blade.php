@extends('layouts.app', ['title' => 'Verses', 'active' => null, 'robots' => 'noindex'])

@section('content')
    <section class="container verses-page">
        <div class="verses-head">
            <a href="{{ route('account') }}" class="back-link"><x-icon name="chevron-left" :size="16" />My account</a>
            <a href="{{ route('account.verses.saved') }}" class="verses-head__saved">My saved verses <span data-saved-count>{{ $savedCount }}</span></a>
        </div>

        <div class="reel" data-reel data-more="{{ route('account.verses.more') }}" data-save="{{ route('account.verses.save', ['verse' => '__ID__']) }}" tabindex="0" aria-label="Bible verses. Swipe up, tap, or press the down arrow for another verse.">
            @foreach ($verses as $i => $v)
                @include('account.partials.verse-card', ['v' => $v, 'first' => $i === 0, 'isSaved' => in_array($v['id'], $saved, true)])
            @endforeach
        </div>
        <template data-card-template>
            @include('account.partials.verse-card', ['v' => ['id' => 0, 'reference' => '', 'text' => '', 'theme' => ''], 'first' => false, 'isSaved' => false])
        </template>
    </section>
@endsection
