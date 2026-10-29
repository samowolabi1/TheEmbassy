<?php

namespace App\Http\Controllers\Community;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Playing a Bible quiz: one go each, timed, scored on the server.
 * The right answers are shown once the quiz closes, so they can't be passed around while it's open.
 */
class QuizController extends Controller
{
    public function show(Request $request, Quiz $quiz): View
    {
        abort_unless($quiz->is_published && $quiz->opens_at->lte(now()), 404);
        $quiz->load('questions');
        $attempt = QuizAttempt::where('quiz_id', $quiz->id)->where('user_id', $request->user()->id)->first();

        return view('community.quiz', [
            'quiz' => $quiz,
            'attempt' => $attempt,
            'playing' => $attempt && ! $attempt->finished_at && $quiz->isOpen(),
            'leaderboard' => $quiz->leaderboard(),
            'players' => $quiz->attempts()->whereNotNull('finished_at')->count(),
            'reveal' => ! $quiz->isOpen(),
        ]);
    }

    public function start(Request $request, Quiz $quiz): RedirectResponse
    {
        abort_unless($quiz->isOpen(), 404);
        QuizAttempt::firstOrCreate(['quiz_id' => $quiz->id, 'user_id' => $request->user()->id], ['started_at' => now()]);

        return redirect()->route('community.quiz', $quiz);
    }

    public function submit(Request $request, Quiz $quiz): RedirectResponse
    {
        $attempt = QuizAttempt::where('quiz_id', $quiz->id)->where('user_id', $request->user()->id)->firstOrFail();
        if ($attempt->finished_at) {
            return redirect()->route('community.quiz', $quiz);
        }
        abort_unless($quiz->isOpen(), 404);
        $quiz->load('questions');
        $request->validate(['answers' => ['nullable', 'array'], 'answers.*' => ['nullable', 'integer', 'min:0', 'max:3']]);

        $answers = [];
        $score = 0;
        foreach ($quiz->questions as $question) {
            $given = $request->input("answers.{$question->id}");
            $answers[$question->id] = $given === null ? null : (int) $given;
            if ($given !== null && (int) $given === $question->answer) {
                $score++;
            }
        }

        $seconds = (int) $attempt->started_at->diffInSeconds(now());
        $late = $seconds > $quiz->timeLimit();
        $attempt->update([
            'answers' => $answers,
            'score' => $late ? 0 : $score,
            'seconds' => $seconds,
            'finished_at' => now(),
        ]);

        return redirect()->route('community.quiz', $quiz)->with('status', $late
            ? 'Time ran out before your answers arrived, so this one doesn\'t count. Try the next quiz!'
            : "You scored {$score} out of {$quiz->questions->count()}.");
    }
}
