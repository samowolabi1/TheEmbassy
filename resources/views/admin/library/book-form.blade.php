@extends('layouts.admin', ['title' => $book->exists ? 'Edit book' : 'Add a book', 'active' => 'library'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <a href="{{ route('admin.library.books') }}" class="box__link">← Books</a>
            <h1>{{ $book->exists ? $book->title : 'Add a book' }}</h1>
        </div>
    </header>

    <form method="POST" action="{{ $book->exists ? route('admin.library.books.update', $book) : route('admin.library.books.store') }}" enctype="multipart/form-data" class="box form">
        @csrf
        @if ($book->exists) @method('PUT') @endif
        <div class="form-row">
            <label class="field">Title
                <input type="text" name="title" value="{{ old('title', $book->title) }}" required maxlength="160">
                @error('title') <span class="field-error">{{ $message }}</span> @enderror
            </label>
            <label class="field">Author
                <input type="text" name="author" value="{{ old('author', $book->author) }}" required maxlength="120">
                @error('author') <span class="field-error">{{ $message }}</span> @enderror
            </label>
        </div>
        <div class="form-row">
            <label class="field">Category
                <select name="category" required>
                    @foreach (\App\Models\Book::CATEGORIES as $key => $label)
                        <option value="{{ $key }}" @selected(old('category', $book->category) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field">Copies
                <input type="number" name="copies" value="{{ old('copies', $book->copies) }}" min="1" max="500" required>
                @error('copies') <span class="field-error">{{ $message }}</span> @enderror
            </label>
            <label class="field">Where it's kept (optional) <input type="text" name="shelf" value="{{ old('shelf', $book->shelf) }}" maxlength="40" placeholder="e.g. Shelf B2"></label>
            <label class="field">ISBN (optional) <input type="text" name="isbn" value="{{ old('isbn', $book->isbn) }}" maxlength="20"></label>
        </div>
        <label class="field">About the book
            <textarea name="description" maxlength="3000" rows="5">{{ old('description', $book->description) }}</textarea>
        </label>
        <div class="field">
            <span>Cover photo (optional)</span>
            @if ($book->thumbUrl())
                <img src="{{ $book->thumbUrl() }}" alt="" style="width: 90px; border-radius: 6px">
                <label class="login__remember"><input type="checkbox" name="remove_cover" value="1">Remove the cover</label>
            @endif
            <input type="file" name="cover" accept="image/jpeg,image/png,image/webp" class="file-input">
            <span class="field__hint">Without one, a cover is drawn in colour with the title and author.</span>
            @error('cover') <span class="field-error">{{ $message }}</span> @enderror
        </div>
        <label class="login__remember"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $book->is_active ?? true))>Show in the library</label>
        <div><button type="submit" class="btn btn--gold">{{ $book->exists ? 'Save' : 'Add the book' }}</button></div>
    </form>
@endsection
