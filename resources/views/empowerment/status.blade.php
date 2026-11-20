@extends('layouts.app', ['title' => 'Your application', 'robots' => 'noindex'])

@section('content')
    <section class="container notices-hero section--last" style="max-width: 720px">
        <div class="card empower-done">
            <span class="eyebrow">{{ mb_strtoupper($application->programme->title) }}</span>
            <h1 class="h2" style="margin: 0">Hi {{ \App\Models\Member::firstNameOf($application->name) }}</h1>
            <p>Reference <strong>{{ $application->reference }}</strong>, sent {{ $application->created_at->format('j F Y') }}.</p>
            <p class="empower-status">Status: <strong>{{ $application->statusLabel() }}</strong>@if ($application->track && in_array($application->status, ['accepted', 'completed'], true)) · {{ $application->track->name }}@endif</p>
            @if ($application->choices->isNotEmpty())<p>You chose: {{ $application->choices->pluck('name')->join(', ', ' and ') }}.</p>@endif
            <p class="field-hint" style="margin: 0">Questions? <a href="{{ route('contact') }}" class="accent-bronze">Contact the church office</a> with your reference.</p>
        </div>
    </section>
@endsection
