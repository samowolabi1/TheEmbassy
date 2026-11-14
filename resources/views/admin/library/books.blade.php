@extends('layouts.admin', ['title' => 'Library books', 'active' => 'library'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <a href="{{ route('admin.library.desk') }}" class="box__link">← Library</a>
            <h1>Books</h1>
            <span class="page-head__sub">The catalogue members see. Turn a book off to hide it without losing its history.</span>
        </div>
        <div class="actions"><a href="{{ route('admin.library.books.create') }}" class="btn btn--gold"><x-icon name="plus" :size="16" />Add a book</a></div>
    </header>

    <section class="box box--flush">
        <form method="GET" action="{{ route('admin.library.books') }}" class="box__head filters-bar" role="search">
            <label class="field filters-bar__search"><span class="sr-only">Search books</span>
                <input type="search" name="q" value="{{ $q }}" placeholder="Title or author">
            </label>
            <button type="submit" class="btn btn--navy btn--xs">Search</button>
        </form>
        @forelse ($books as $book)
            <div class="list-item">
                <span class="list-item__main">
                    <span class="list-item__title">{{ $book->title }}</span>
                    <span class="list-item__meta">{{ $book->author }} · {{ $book->categoryLabel() }} · {{ $book->copies }} {{ \Illuminate\Support\Str::plural('copy', $book->copies) }}, {{ $book->out }} out{{ $book->shelf ? ' · '.$book->shelf : '' }}</span>
                </span>
                @unless ($book->is_active)<span class="pill pill--draft">Hidden</span>@endunless
                <span class="list-item__actions"><a href="{{ route('admin.library.books.edit', $book) }}" class="btn btn--ghost btn--xs">Edit</a></span>
            </div>
        @empty
            <p class="empty-state">No books yet. Add the first one.</p>
        @endforelse
        @include('admin.partials.pager', ['paginator' => $books])
    </section>
@endsection
