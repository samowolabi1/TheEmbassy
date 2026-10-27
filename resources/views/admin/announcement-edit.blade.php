@extends('layouts.admin', ['title' => 'Edit announcement', 'active' => 'content'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <a href="{{ route('admin.content') }}" class="box__link">← Content &amp; announcements</a>
            <h1>Edit announcement</h1>
        </div>
        <span class="pill pill--{{ $announcement->status() }}">{{ $announcement->status() }}</span>
    </header>

    <section class="box" style="max-width: 760px">
        <form method="POST" action="{{ route('admin.announcements.update', $announcement) }}" class="form">
            @csrf
            @method('PUT')
            @include('admin.partials.announcement-fields', ['announcement' => $announcement])
            <div class="actions">
                <button type="submit" class="btn btn--gold">Save changes</button>
                <a href="{{ route('admin.content') }}" class="btn btn--ghost">Cancel</a>
            </div>
        </form>
    </section>
@endsection
