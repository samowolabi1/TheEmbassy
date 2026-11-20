@extends('layouts.app', ['title' => 'Youth empowerment'])

@section('content')
    <section class="container notices-hero section--last stack" style="gap: 14px">
        <span class="eyebrow">YOUTH EMPOWERMENT</span>
        <h1 class="display notices-hero__title">Learn a skill. Build your future.</h1>
        <p class="lede" style="max-width: 640px">There's no empowerment programme open right now. Check back soon, or follow us to hear when the next one opens.</p>
        <a href="{{ route('contact') }}" class="btn btn--outline" style="align-self: flex-start">Ask the church office</a>
    </section>
@endsection
