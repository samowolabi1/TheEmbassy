@extends('layouts.admin', ['title' => 'Testimonies', 'active' => 'testimonies'])

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <h1>Testimonies</h1>
            <span class="page-head__sub">Shared by members from their account. Read each one, tidy spelling if needed, and publish. It goes on the <a href="{{ route('testimonies.index') }}" target="_blank" rel="noopener">testimonies page</a> only if the member agreed.</span>
        </div>
    </header>

    <nav class="tabs" aria-label="Testimonies">
        @foreach (\App\Http\Controllers\Admin\TestimonyController::TABS as $key => $label)
            <a href="{{ route('admin.testimonies.index', ['tab' => $key]) }}" class="tab" @if ($tab === $key) aria-current="page" @endif>{{ $label }} <span>{{ $counts[$key] }}</span></a>
        @endforeach
    </nav>

    <section class="box box--flush">
        @forelse ($testimonies as $t)
            <article class="prayer-card">
                <div class="prayer-card__head">
                    <span class="prayer-card__who">{{ $t->member->name }}</span>
                    <span class="pill pill--info">{{ $t->categoryLabel() }}</span>
                    @unless ($t->share_publicly)<span class="pill pill--warn">Not for the website</span>@endunless
                    @if ($t->share_in_church)<span class="pill pill--ok">May be read in church</span>@endif
                    <span class="prayer-card__when">{{ ($t->published_at ?? $t->created_at)->diffForHumans() }}{{ $t->praises ? ' · 🙌 '.$t->praises : '' }}</span>
                </div>
                <p class="box__note" style="margin: 0">Shown as: {{ $t->byline() }}</p>
                <form method="POST" action="{{ route('admin.testimonies.update', $t) }}" class="form">
                    @csrf
                    @method('PUT')
                    <div class="form-row">
                        <label class="field">Title <input type="text" name="title" value="{{ $t->title }}" required maxlength="120"></label>
                        <label class="field">Kind
                            <select name="category">
                                @foreach (\App\Models\Testimony::CATEGORIES as $key => $label)
                                    <option value="{{ $key }}" @selected($t->category === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                    <label class="field">Testimony <textarea name="body" required maxlength="5000" rows="7">{{ $t->body }}</textarea></label>
                    <div style="display: flex; gap: 8px; flex-wrap: wrap">
                        @if ($t->status !== 'published')
                            <button type="submit" name="action" value="publish" class="btn btn--gold btn--xs">Publish</button>
                        @endif
                        <button type="submit" name="action" value="save" class="btn btn--ghost btn--xs">Save edits</button>
                    </div>
                </form>
                @if ($t->status === 'pending')
                    <details>
                        <summary class="box__link" style="cursor: pointer">Don't publish</summary>
                        <form method="POST" action="{{ route('admin.testimonies.decline', $t) }}" class="form" style="margin-top: 8px">
                            @csrf
                            <label class="field">Why (they'll see this)
                                <input type="text" name="review_note" required maxlength="300" placeholder="e.g. Thank you! Please add a little more about what happened.">
                            </label>
                            <div><button type="submit" class="btn btn--danger btn--xs">Don't publish</button></div>
                        </form>
                    </details>
                @elseif ($t->status === 'published')
                    <form method="POST" action="{{ route('admin.testimonies.unpublish', $t) }}" data-confirm="Take this testimony down?">
                        @csrf
                        <button type="submit" class="btn btn--danger btn--xs">Take down</button>
                    </form>
                @elseif ($t->review_note)
                    <p class="box__note" style="margin: 0">Told the member: "{{ $t->review_note }}"</p>
                @endif
            </article>
        @empty
            <p class="empty-state">{{ $tab === 'pending' ? 'No testimonies waiting to be read.' : 'Nothing here.' }}</p>
        @endforelse
        @include('admin.partials.pager', ['paginator' => $testimonies])
    </section>
@endsection
