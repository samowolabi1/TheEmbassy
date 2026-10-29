@extends('layouts.admin', ['title' => $quiz->exists ? 'Edit quiz' : 'New quiz', 'active' => 'community'])

@php
    $locked = $quiz->exists && $quiz->attempts()->exists();
    $questions = old('questions', $quiz->exists ? $quiz->questions->map(fn ($q) => ['question' => $q->question, 'options' => $q->options, 'answer' => $q->answer, 'reference' => $q->reference])->all() : []);
    // Room for more questions while it can still change.
    if (! $locked) {
        $questions = [...array_values($questions), ...array_fill(0, max(3, 10 - count($questions)), ['question' => '', 'options' => [], 'answer' => null, 'reference' => ''])];
    }
@endphp

@section('content')
    <header class="page-head">
        <div class="stack" style="gap: 4px">
            <a href="{{ route('admin.community.index') }}" class="box__link">← Community</a>
            <h1>{{ $quiz->exists ? $quiz->title : 'New Bible quiz' }}</h1>
            <span class="page-head__sub">Members get one go each while it's open. Answers are shown to them after it closes.</span>
        </div>
        @if ($quiz->exists && $quiz->is_published)
            <div class="actions"><a href="{{ route('community.quiz', $quiz) }}" class="btn btn--outline" target="_blank">View in the community</a></div>
        @endif
    </header>

    @if ($errors->any())
        <p class="flash flash--error" role="alert">{{ $errors->first() }}</p>
    @endif

    <form method="POST" action="{{ $quiz->exists ? route('admin.community.quizzes.update', $quiz) : route('admin.community.quizzes.store') }}" class="row">
        @csrf
        @if ($quiz->exists) @method('PUT') @endif

        <section class="box grow form">
            <h2>Questions</h2>
            @if ($locked)
                <p class="box__note">{{ $quiz->attempts()->count() }} people have played, so the questions are fixed.</p>
                <ol class="quiz-admin-list">
                    @foreach ($quiz->questions as $q)
                        <li><strong>{{ $q->question }}</strong> <span class="box__note">{{ $q->options[$q->answer] ?? '' }}@if ($q->reference) · {{ $q->reference }}@endif</span></li>
                    @endforeach
                </ol>
            @else
                <span class="box__note">Tick the right answer. Leave a question blank to skip it; at least two answers each.</span>
                @foreach ($questions as $i => $q)
                    <fieldset class="repeat-row repeat-row--stack">
                        <legend class="list-item__title" style="padding: 0">Question {{ $i + 1 }}</legend>
                        <label class="field"><span class="sr-only">Question {{ $i + 1 }}</span>
                            <input type="text" name="questions[{{ $i }}][question]" value="{{ $q['question'] ?? '' }}" maxlength="300" placeholder="e.g. Who was swallowed by a great fish?">
                        </label>
                        <div class="quiz-options">
                            @foreach (range(0, 3) as $o)
                                <label class="quiz-option">
                                    <input type="radio" name="questions[{{ $i }}][answer]" value="{{ $o }}" @checked(isset($q['answer']) && (string) $q['answer'] === (string) $o) aria-label="Answer {{ $o + 1 }} is right">
                                    <input type="text" name="questions[{{ $i }}][options][{{ $o }}]" value="{{ $q['options'][$o] ?? '' }}" maxlength="120" placeholder="Answer {{ $o + 1 }}" aria-label="Question {{ $i + 1 }}, answer {{ $o + 1 }}">
                                </label>
                            @endforeach
                        </div>
                        <label class="field">Bible reference (shown with the answer) <input type="text" name="questions[{{ $i }}][reference]" value="{{ $q['reference'] ?? '' }}" maxlength="60" placeholder="e.g. Jonah 1:17"></label>
                        @error("questions.{$i}.answer") <span class="field-error">{{ $message }}</span> @enderror
                    </fieldset>
                @endforeach
            @endif
        </section>

        <aside class="side-col stack" style="gap: 16px">
            <section class="box form">
                <label class="field">Title
                    <input type="text" name="title" value="{{ old('title', $quiz->title) }}" required maxlength="120" placeholder="e.g. Heroes of faith">
                    @error('title') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <label class="field">Description <input type="text" name="description" value="{{ old('description', $quiz->description) }}" maxlength="300"></label>
                <label class="field">Opens
                    <input type="datetime-local" name="opens_at" value="{{ old('opens_at', $quiz->opens_at?->format('Y-m-d\TH:i')) }}" required>
                </label>
                <label class="field">Closes
                    <input type="datetime-local" name="closes_at" value="{{ old('closes_at', $quiz->closes_at?->format('Y-m-d\TH:i')) }}" required>
                    @error('closes_at') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <label class="field">Seconds per question
                    <input type="number" name="seconds_per_question" value="{{ old('seconds_per_question', $quiz->seconds_per_question) }}" min="5" max="120" required>
                </label>
                <label class="login__remember"><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $quiz->is_published))>Publish (members see it when it opens)</label>
                <div class="actions">
                    <button type="submit" class="btn btn--navy">Save quiz</button>
                    @if ($quiz->exists)
                        <button type="submit" form="delete-quiz" class="btn btn--danger btn--xs">Delete</button>
                    @endif
                </div>
            </section>

            @if ($quiz->exists && ($leaderboard ?? collect())->isNotEmpty())
                <section class="box box--flush">
                    <div class="box__head"><h2>Leaderboard</h2></div>
                    @foreach ($leaderboard as $row)
                        <div class="list-item">
                            <span class="list-item__main">
                                <span class="list-item__title">{{ $loop->iteration }}. {{ $row->user?->communityName() }}</span>
                                <span class="list-item__meta">{{ $row->score }}/{{ $quiz->questions->count() }} in {{ gmdate('i:s', (int) $row->seconds) }}</span>
                            </span>
                        </div>
                    @endforeach
                </section>
            @endif
        </aside>
    </form>
    @if ($quiz->exists)
        <form id="delete-quiz" method="POST" action="{{ route('admin.community.quizzes.destroy', $quiz) }}" data-confirm="Delete this quiz and its scores?">
            @csrf
            @method('DELETE')
        </form>
    @endif
@endsection
