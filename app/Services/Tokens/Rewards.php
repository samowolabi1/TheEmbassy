<?php

namespace App\Services\Tokens;

use App\Models\Book;
use App\Models\BookLoan;
use App\Models\Duty;
use App\Models\Member;
use App\Models\Quiz;
use App\Models\Service;
use App\Models\UnitMeeting;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * The ways to earn tokens. Rewards are paid out of the church treasury (never created from nothing),
 * and every reward has a key, so it's given once per occasion however many times its trigger happens.
 * If the treasury runs short, rewards pause until the pastor in charge mints more.
 */
class Rewards
{
    /** What each rule is called on the wallet page and in Settings. */
    public const RULES = [
        'attend_in_person' => ['Come to church', 'Mark your attendance at church (location and screen code)'],
        'attend_online' => ['Join a service online', 'Join from your account, or check in with the code on the stream'],
        'served' => ['Serve on the rota', 'Be present at a service your unit has you serving at'],
        'sunday_streak' => ['Four Sundays in a row', 'A bonus for every four Sundays running'],
        'unit_meeting' => ['Unit meetings', 'Check in at your unit\'s meetings'],
        'quiz_play' => ['Play the Bible quiz', 'For taking part in each quiz'],
        'quiz_correct' => ['Bible quiz answers', 'For each right answer'],
        'community_day' => ['Join in the community', 'Your first message in the community each day'],
        'declaration' => ['Declare it', 'Say this week\'s declaration from your account'],
        'profile' => ['Complete your profile', 'Add your birthday, address, unit and fellowship (once)'],
        'welcome' => ['Welcome gift', 'When your membership is confirmed (once)'],
        'birthday' => ['Birthday gift', 'On your birthday each year'],
        'testimony' => ['Share a testimony', 'When your testimony is published (once a month)'],
        'book_returned' => ['Return library books on time', 'For each book you bring back by its due date'],
        'book_review' => ['Review a book', 'Your first review of each library book'],
    ];

    public function __construct(private Ledger $ledger) {}

    public function enabled(): bool
    {
        return (bool) config('tokens.enabled', true);
    }

    public function amount(string $rule): int
    {
        return max(0, (int) config("tokens.rules.{$rule}", 0));
    }

    /**
     * Give the tokens for a rule, once per key. Returns how many were given (0 if none).
     */
    public function give(User $user, string $rule, string $key, string $description, ?int $amount = null): int
    {
        $amount ??= $this->amount($rule);
        if (! $this->enabled() || $amount <= 0) {
            return 0;
        }

        return $this->ledger->payOut($user, $amount, 'reward', $description, "{$rule}:{$key}:{$user->id}", null, quiet: true) ? $amount : 0;
    }

    /**
     * After a member is marked present at a service, in person or online. Also covers serving on
     * the rota and Sunday streaks. Returns the total given.
     */
    public function attended(Member $member, Service $service, string $mode): int
    {
        $user = $member->user;
        if (! $user || $member->status !== Member::ACTIVE) {
            return 0;
        }
        $label = $service->label().' · '.$service->held_on->format('j M');

        $given = $mode === 'online'
            ? $this->give($user, 'attend_online', "service:{$service->id}", 'Joined online: '.$label)
            : $this->give($user, 'attend_in_person', "service:{$service->id}", 'At church: '.$label);

        if (Duty::where('service_id', $service->id)->where('member_id', $member->id)->exists()) {
            $given += $this->give($user, 'served', "service:{$service->id}", 'Served: '.$label);
        }

        if ($service->kind === 'sunday' && ($streak = $this->sundayStreak($member, $service)) > 0 && $streak % 4 === 0) {
            $given += $this->give($user, 'sunday_streak', "service:{$service->id}", "{$streak} Sundays in a row");
        }

        return $given;
    }

    public function meeting(Member $member, UnitMeeting $meeting): int
    {
        if (! $member->user || $member->status !== Member::ACTIVE) {
            return 0;
        }

        return $this->give($member->user, 'unit_meeting', "meeting:{$meeting->id}", 'Unit meeting: '.$meeting->title);
    }

    public function quiz(User $user, Quiz $quiz, int $score): int
    {
        return $this->give($user, 'quiz_play', "quiz:{$quiz->id}", 'Played the quiz: '.$quiz->title)
            + $this->give($user, 'quiz_correct', "quiz:{$quiz->id}", "{$score} right in the quiz: {$quiz->title}", $score * $this->amount('quiz_correct'));
    }

    public function communityPost(User $user): int
    {
        return $this->give($user, 'community_day', 'day:'.today()->toDateString(), 'Joined in the community');
    }

    public function declared(User $user, Carbon $weekOf): int
    {
        return $this->give($user, 'declaration', 'week:'.$weekOf->toDateString(), 'Declared the word for the week of '.$weekOf->format('j M'));
    }

    /**
     * Once the profile has everything in it.
     */
    public function profile(Member $member): int
    {
        if (! $member->user || $member->status !== Member::ACTIVE
            || ! $member->birthday || blank($member->address) || ! $member->unit_id || ! $member->fellowship_id) {
            return 0;
        }

        return $this->give($member->user, 'profile', 'once', 'Completed your profile');
    }

    public function welcome(User $user): int
    {
        return $this->give($user, 'welcome', 'once', 'Welcome to '.config('church.short_name'));
    }

    /**
     * Once a month at most, so testimonies are shared for their own sake.
     */
    public function testimony(User $user): int
    {
        return $this->give($user, 'testimony', now()->format('Y-m'), 'Thank you for sharing your testimony');
    }

    /**
     * A library book brought back by its due date.
     */
    public function bookReturned(User $user, BookLoan $loan): int
    {
        return $this->give($user, 'book_returned', 'loan:'.$loan->id, 'Returned "'.$loan->book->title.'" on time');
    }

    /**
     * The first review of each book (editing it later earns nothing more).
     */
    public function bookReview(User $user, Book $book): int
    {
        return $this->give($user, 'book_review', 'book:'.$book->id, 'Reviewed "'.$book->title.'"');
    }

    public function birthday(User $user): int
    {
        return $this->give($user, 'birthday', 'year:'.today()->year, 'Happy birthday!');
    }

    /**
     * How many Sunday services in a row, up to and including this one, the member was at.
     */
    private function sundayStreak(Member $member, Service $service): int
    {
        $sundays = Service::where('kind', 'sunday')->whereDate('held_on', '<=', $service->held_on)
            ->orderByDesc('held_on')->take(52)->pluck('id');
        $attended = $member->services()->whereIn('services.id', $sundays)->pluck('services.id')->flip();

        $streak = 0;
        foreach ($sundays as $id) {
            if (! isset($attended[$id])) {
                break;
            }
            $streak++;
        }

        return $streak;
    }
}
