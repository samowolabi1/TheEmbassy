<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommunityMessage;
use App\Models\CommunityReport;
use App\Models\CommunityRoom;
use App\Models\Quiz;
use App\Models\Unit;
use App\Models\User;
use App\Services\Content\Images;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Looking after the members' community: reports, muted members, rooms and Bible quizzes.
 */
class CommunityController extends Controller
{
    public function index(): View
    {
        return view('admin.community.index', [
            'reports' => CommunityReport::with('message.user.member', 'message.room', 'reporter')->whereNull('resolved_at')->latest()->get(),
            'rooms' => CommunityRoom::with('unit')->withCount(['messages' => fn ($q) => $q->whereNull('removed_at')])->orderBy('position')->orderBy('id')->get(),
            'muted' => User::where('community_muted_until', '>', now())->orderBy('community_muted_until')->get(),
            'quizzes' => Quiz::withCount(['questions', 'attempts'])->latest('opens_at')->take(12)->get(),
            'units' => Unit::orderBy('name')->get(['id', 'name']),
            'stats' => [
                'messages_week' => CommunityMessage::where('created_at', '>=', now()->subWeek())->whereNull('removed_at')->count(),
                'people_week' => CommunityMessage::where('created_at', '>=', now()->subWeek())->distinct()->count('user_id'),
            ],
        ]);
    }

    /**
     * Deal with a report: dismiss it, remove the message, or remove it and mute the writer.
     */
    public function resolve(Request $request, CommunityReport $report, Images $images): RedirectResponse
    {
        $data = $request->validate([
            'outcome' => ['required', Rule::in(['dismissed', 'removed', 'muted'])],
            'days' => ['nullable', 'required_if:outcome,muted', 'integer', Rule::in([1, 3, 7, 30])],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);
        $message = $report->message;

        DB::transaction(function () use ($data, $message, $request, $images) {
            if ($data['outcome'] !== 'dismissed' && ! $message->isRemoved()) {
                $images->delete($message->image_path, $message->image_thumb_path);
                $message->update(['body' => null, 'image_path' => null, 'image_thumb_path' => null, 'poll' => null, 'removed_at' => now(), 'removed_by' => $request->user()->id]);
                $message->reactions()->delete();
                $message->votes()->delete();
            }
            if ($data['outcome'] === 'muted' && $message->user) {
                $this->mute($message->user, (int) $data['days'], $data['reason'] ?? null);
            }
            // Every open report on the same message is settled together.
            CommunityReport::where('community_message_id', $message->id)->whereNull('resolved_at')
                ->update(['resolved_at' => now(), 'resolved_by' => $request->user()->id, 'outcome' => $data['outcome']]);
        });

        return back()->with('status', match ($data['outcome']) {
            'dismissed' => 'Report dismissed; the message stays.',
            'removed' => 'Message removed.',
            default => 'Message removed and '.$message->user?->communityName().' muted for '.$data['days'].' '.str('day')->plural((int) $data['days']).'.',
        });
    }

    public function muteUser(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'days' => ['required', 'integer', Rule::in([1, 3, 7, 30])],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);
        abort_if($user->moderatesCommunity(), 422, 'Moderators can\'t be muted.');
        $this->mute($user, (int) $data['days'], $data['reason'] ?? null);

        return back()->with('status', $user->communityName().' is muted.');
    }

    public function unmute(User $user): RedirectResponse
    {
        $user->forceFill(['community_muted_until' => null, 'community_mute_reason' => null])->save();

        return back()->with('status', $user->communityName().' can post again.');
    }

    public function storeRoom(Request $request): RedirectResponse
    {
        $data = $this->roomData($request);
        CommunityRoom::create([...$data, 'slug' => CommunityRoom::uniqueSlug($data['name']), 'position' => (int) CommunityRoom::max('position') + 1]);

        return back()->with('status', "Room \"{$data['name']}\" added.");
    }

    public function updateRoom(Request $request, CommunityRoom $room): RedirectResponse
    {
        $room->update($this->roomData($request, 'room'.$room->id));

        return back()->with('status', "Room \"{$room->name}\" saved.");
    }

    public function destroyRoom(CommunityRoom $room): RedirectResponse
    {
        $room->delete();

        return back()->with('status', "Room \"{$room->name}\" and its messages deleted.");
    }

    // ---- Quizzes ----

    public function createQuiz(): View
    {
        return view('admin.community.quiz', ['quiz' => new Quiz([
            'opens_at' => now()->startOfHour()->addHour(),
            'closes_at' => now()->startOfHour()->addDays(7),
            'seconds_per_question' => 20,
        ])]);
    }

    public function storeQuiz(Request $request): RedirectResponse
    {
        $quiz = DB::transaction(function () use ($request) {
            $quiz = Quiz::create($this->quizData($request));
            $this->saveQuestions($quiz, $request);

            return $quiz;
        });

        return redirect()->route('admin.community.quizzes.edit', $quiz)->with('status', 'Quiz saved.');
    }

    public function editQuiz(Quiz $quiz): View
    {
        return view('admin.community.quiz', ['quiz' => $quiz->load('questions'), 'leaderboard' => $quiz->leaderboard(20)]);
    }

    public function updateQuiz(Request $request, Quiz $quiz): RedirectResponse
    {
        // Changing questions after people have played would make the scores meaningless.
        $locked = $quiz->attempts()->exists();
        DB::transaction(function () use ($request, $quiz, $locked) {
            $quiz->update($this->quizData($request));
            if (! $locked) {
                $this->saveQuestions($quiz, $request);
            }
        });

        return back()->with('status', $locked ? 'Quiz details saved. Questions can\'t change once people have played.' : 'Quiz saved.');
    }

    public function destroyQuiz(Quiz $quiz): RedirectResponse
    {
        $quiz->delete();

        return redirect()->route('admin.community.index')->with('status', "Quiz \"{$quiz->title}\" deleted.");
    }

    private function mute(User $user, int $days, ?string $reason): void
    {
        if ($user->moderatesCommunity()) {
            return;
        }
        $user->forceFill(['community_muted_until' => now()->addDays($days), 'community_mute_reason' => $reason])->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function roomData(Request $request, string $bag = 'room'): array
    {
        $data = $request->validateWithBag($bag, [
            'name' => ['required', 'string', 'max:60'],
            'emoji' => ['nullable', 'string', 'max:16'],
            'description' => ['nullable', 'string', 'max:200'],
            'unit_id' => ['nullable', Rule::exists('units', 'id')],
            'position' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        return [
            ...collect($data)->except('position')->all(),
            'is_locked' => $request->boolean('is_locked'),
            ...(isset($data['position']) ? ['position' => $data['position']] : []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function quizData(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:300'],
            'opens_at' => ['required', 'date'],
            'closes_at' => ['required', 'date', 'after:opens_at'],
            'seconds_per_question' => ['required', 'integer', 'between:5,120'],
        ]);

        return [
            ...$data,
            'opens_at' => Carbon::parse($data['opens_at']),
            'closes_at' => Carbon::parse($data['closes_at']),
            'is_published' => $request->boolean('is_published'),
        ];
    }

    private function saveQuestions(Quiz $quiz, Request $request): void
    {
        $data = $request->validate([
            'questions' => ['nullable', 'array', 'max:30'],
            'questions.*.question' => ['nullable', 'string', 'max:300'],
            'questions.*.options' => ['nullable', 'array', 'max:4'],
            'questions.*.options.*' => ['nullable', 'string', 'max:120'],
            'questions.*.answer' => ['nullable', 'integer', 'min:0', 'max:3'],
            'questions.*.reference' => ['nullable', 'string', 'max:60'],
        ]);

        $rows = [];
        foreach ($data['questions'] ?? [] as $i => $q) {
            if (blank($q['question'] ?? null)) {
                continue;
            }
            // Keep the options in place so the chosen answer still points at the right one.
            $options = array_map(fn ($o) => trim((string) $o), array_slice(array_pad($q['options'] ?? [], 4, ''), 0, 4));
            $filled = array_keys(array_filter($options, fn ($o) => $o !== ''));
            $answer = isset($q['answer']) ? (int) $q['answer'] : null;
            if (count($filled) < 2 || $answer === null || ! in_array($answer, $filled, true)) {
                throw ValidationException::withMessages(["questions.{$i}.answer" => 'Question '.(count($rows) + 1).' needs at least two answers and the right one ticked.']);
            }
            // Drop empty options, moving the answer index with them.
            $rows[] = [
                'question' => trim($q['question']),
                'options' => array_values(array_map(fn ($k) => $options[$k], $filled)),
                'answer' => array_search($answer, $filled, true),
                'reference' => $q['reference'] ?? null,
                'position' => count($rows),
            ];
        }
        if ($rows === [] && $request->boolean('is_published')) {
            throw ValidationException::withMessages(['questions' => 'Add at least one question before publishing.']);
        }

        $quiz->questions()->delete();
        foreach ($rows as $row) {
            $quiz->questions()->create($row);
        }
    }
}
