<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\BookLoan;
use App\Services\Content\Images;
use App\Services\Library\Lending;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

/**
 * The library desk: hand books over, take them back, chase overdue ones, and keep the catalogue.
 */
class LibraryController extends Controller
{
    public const TABS = ['collect' => 'To hand over', 'out' => 'On loan', 'overdue' => 'Overdue', 'waiting' => 'Waiting list', 'history' => 'History'];

    public function desk(Request $request): View
    {
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'collect';
        $q = trim((string) $request->query('q'));

        return view('admin.library.desk', [
            'tab' => $tab,
            'q' => $q,
            'loans' => BookLoan::with('book', 'member')
                ->when($q !== '', fn ($l) => $l->where(fn ($w) => $w->whereHas('member', fn ($m) => $m->where('name', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%"))
                    ->orWhereHas('book', fn ($b) => $b->where('title', 'like', "%{$q}%"))))
                ->when($tab === 'collect', fn ($l) => $l->where('status', BookLoan::REQUESTED)->orderBy('updated_at'))
                ->when($tab === 'out', fn ($l) => $l->where('status', BookLoan::ON_LOAN)->orderBy('due_on'))
                ->when($tab === 'overdue', fn ($l) => $l->where('status', BookLoan::ON_LOAN)->where('due_on', '<', today())->orderBy('due_on'))
                ->when($tab === 'waiting', fn ($l) => $l->where('status', BookLoan::WAITING)->orderBy('id'))
                ->when($tab === 'history', fn ($l) => $l->whereIn('status', [BookLoan::RETURNED, BookLoan::CANCELLED])->latest('updated_at'))
                ->paginate(25)->withQueryString(),
            'counts' => [
                'collect' => BookLoan::where('status', BookLoan::REQUESTED)->count(),
                'out' => BookLoan::where('status', BookLoan::ON_LOAN)->count(),
                'overdue' => BookLoan::where('status', BookLoan::ON_LOAN)->where('due_on', '<', today())->count(),
                'waiting' => BookLoan::where('status', BookLoan::WAITING)->count(),
            ],
            'stats' => ['books' => Book::active()->count(), 'copies' => (int) Book::active()->sum('copies')],
        ]);
    }

    public function lend(Request $request, BookLoan $loan, Lending $lending): RedirectResponse
    {
        $lending->lend($loan, $request->user());

        return back()->with('status', "Handed over to {$loan->member->name}. Due back ".$loan->fresh()->due_on->format('D j M').'.');
    }

    public function receive(Request $request, BookLoan $loan, Lending $lending): RedirectResponse
    {
        $lending->receive($loan, $request->user());

        return back()->with('status', "\"{$loan->book->title}\" is back.");
    }

    public function extend(Request $request, BookLoan $loan, Lending $lending): RedirectResponse
    {
        $days = (int) $request->validate(['days' => ['required', 'integer', Rule::in([7, 14, 28])]])['days'];
        $lending->extend($loan, $days);

        return back()->with('status', 'Now due back '.$loan->fresh()->due_on->format('D j M').'.');
    }

    public function cancel(BookLoan $loan, Lending $lending): RedirectResponse
    {
        $lending->cancel($loan);

        return back()->with('status', 'Reservation cancelled.');
    }

    public function books(Request $request): View
    {
        $q = trim((string) $request->query('q'));

        return view('admin.library.books', [
            'books' => Book::withCount(['loans as out' => fn ($l) => $l->whereIn('status', [BookLoan::REQUESTED, BookLoan::ON_LOAN])])
                ->when($q !== '', fn ($b) => $b->where(fn ($w) => $w->where('title', 'like', "%{$q}%")->orWhere('author', 'like', "%{$q}%")))
                ->orderBy('title')->paginate(30)->withQueryString(),
            'q' => $q,
        ]);
    }

    public function create(): View
    {
        return view('admin.library.book-form', ['book' => new Book(['copies' => 1, 'is_active' => true])]);
    }

    public function store(Request $request, Images $images): RedirectResponse
    {
        $book = new Book($this->validated($request));
        $this->cover($request, $book, $images);
        $book->save();

        return redirect()->route('admin.library.books')->with('status', "\"{$book->title}\" added.");
    }

    public function edit(Book $book): View
    {
        return view('admin.library.book-form', ['book' => $book]);
    }

    public function update(Request $request, Book $book, Images $images): RedirectResponse
    {
        $data = $this->validated($request);
        $out = $book->copiesTaken();
        if ($data['copies'] < $out) {
            throw ValidationException::withMessages(['copies' => "{$out} copies are out or set aside, so there must be at least {$out}."]);
        }
        $book->fill($data);
        $this->cover($request, $book, $images);
        $book->save();

        return redirect()->route('admin.library.books')->with('status', "\"{$book->title}\" saved.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'author' => ['required', 'string', 'max:120'],
            'category' => ['required', Rule::in(array_keys(Book::CATEGORIES))],
            'description' => ['nullable', 'string', 'max:3000'],
            'isbn' => ['nullable', 'string', 'max:20'],
            'copies' => ['required', 'integer', 'min:1', 'max:500'],
            'shelf' => ['nullable', 'string', 'max:40'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'remove_cover' => ['nullable', 'boolean'],
        ]);

        return [...collect($data)->except(['cover', 'remove_cover'])->all(), 'is_active' => $request->boolean('is_active')];
    }

    private function cover(Request $request, Book $book, Images $images): void
    {
        if ($request->boolean('remove_cover') || $request->hasFile('cover')) {
            $images->delete($book->cover_path, $book->cover_thumb_path);
            $book->cover_path = $book->cover_thumb_path = null;
        }
        if ($file = $request->file('cover')) {
            try {
                $saved = $images->store($file, 'books', 900, 360);
            } catch (RuntimeException $e) {
                throw ValidationException::withMessages(['cover' => $e->getMessage()]);
            }
            $book->cover_path = $saved['path'];
            $book->cover_thumb_path = $saved['thumb_path'];
        }
    }
}
