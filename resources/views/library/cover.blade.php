{{-- A book's cover: the photo, or a drawn cover in the book's colour with its title and author. --}}
@if ($book->thumbUrl())
    <img src="{{ $book->thumbUrl() }}" alt="" class="book-cover" loading="lazy">
@else
    <span class="book-cover book-cover--drawn" style="--spine: {{ $book->spine() }}" aria-hidden="true">
        <span class="book-cover__title">{{ $book->title }}</span>
        <span class="book-cover__author">{{ $book->author }}</span>
    </span>
@endif
