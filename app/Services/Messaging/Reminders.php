<?php

namespace App\Services\Messaging;

use App\Mail\ReminderMail;
use App\Models\Member;
use App\Models\PushSubscription;
use App\Models\ReminderLog;
use App\Models\Service;
use App\Models\ServiceFeedback;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Automatic messages around services and the market:
 *  - a reminder before each service, to every approved member;
 *  - a thank-you greeting after it, to the members who were there;
 *  - a feedback request a little later, to those who were there and haven't said how it was;
 *  - market renewal reminders (see SubscriptionReminders), which use deliver() here too.
 *
 * Staff switch each one on or off, set the timing, choose app notification, text message and/or
 * email, and write the wording on admin › Reminders & greetings. Each service is only handled once
 * per kind, and only recent services count, so turning something on never floods old services.
 */
class Reminders
{
    public const KINDS = [
        'service' => ['label' => 'Service reminder', 'timing' => 'hours_before', 'unit' => 'hours before the service starts', 'max' => 48],
        'greeting' => ['label' => 'Thank-you greeting', 'timing' => 'minutes_after', 'unit' => 'minutes after the service ends', 'max' => 720],
        'feedback' => ['label' => 'Feedback request', 'timing' => 'hours_after', 'unit' => 'hours after the service ends', 'max' => 48],
        'market' => ['label' => 'Market renewal reminder', 'timing' => 'days_before', 'unit' => 'days before a subscription ends', 'max' => 30],
    ];

    /** The placeholders each kind's wording can use. */
    public const PLACEHOLDERS = [
        'service' => ['first_name', 'service', 'day', 'time', 'church'],
        'greeting' => ['first_name', 'service', 'church'],
        'feedback' => ['first_name', 'service', 'church'],
        'market' => ['name', 'shop', 'market', 'end_date', 'days_left'],
    ];

    public function __construct(private WebPush $push, private Termii $sms) {}

    /**
     * @return array{enabled: bool, timing: int, push: bool, sms: bool, email: bool, text: string}
     */
    public static function settings(string $kind): array
    {
        $c = config("church.reminders.{$kind}", []);
        $timing = self::KINDS[$kind]['timing'];

        return [
            'enabled' => self::on($c['enabled'] ?? false),
            'timing' => max(0, (int) ($c[$timing] ?? 0)),
            'push' => self::on($c['push'] ?? false),
            'sms' => self::on($c['sms'] ?? false),
            'email' => self::on($c['email'] ?? false),
            'text' => (string) ($c['text'] ?? ''),
            'final' => self::on($c['final'] ?? false),
            'lapsed' => self::on($c['lapsed'] ?? false),
        ];
    }

    public static function render(string $text, array $vars): string
    {
        return trim(strtr($text, collect($vars)->mapWithKeys(fn ($v, $k) => ['{'.$k.'}' => (string) $v])->all()));
    }

    /**
     * Run every few minutes. Returns how many people each kind reached.
     *
     * @return array{service: int, greeting: int, feedback: int}
     */
    public function run(): array
    {
        return ['service' => $this->serviceReminders(), 'greeting' => $this->greetings(), 'feedback' => $this->feedbackRequests()];
    }

    private function serviceReminders(): int
    {
        $s = self::settings('service');
        if (! $s['enabled']) {
            return 0;
        }
        $reached = 0;
        Service::whereNull('reminder_sent_at')->whereNotNull('starts_at')
            ->whereBetween('starts_at', [now(), now()->addHours(max(1, $s['timing']))])
            ->orderBy('starts_at')->get()
            ->each(function (Service $service) use ($s, &$reached) {
                $service->forceFill(['reminder_sent_at' => now()])->save();
                $members = Member::active()->with('user')->get();
                $reached += $this->deliver($members, 'service', $s, fn (Member $m) => $this->serviceVars($service, $m), $service->label().' '.$this->day($service->starts_at), route('account'), $service);
            });

        return $reached;
    }

    private function greetings(): int
    {
        $s = self::settings('greeting');
        if (! $s['enabled']) {
            return 0;
        }
        $reached = 0;
        foreach ($this->endedServices('greeting_sent_at', $s['timing'], 24) as $service) {
            $service->forceFill(['greeting_sent_at' => now()])->save();
            $members = $service->members()->where('members.status', Member::ACTIVE)->with('user')->get();
            $reached += $this->deliver($members, 'greeting', $s, fn (Member $m) => $this->serviceVars($service, $m), 'Thank you for worshipping with us', route('account'), $service);
        }

        return $reached;
    }

    private function feedbackRequests(): int
    {
        $s = self::settings('feedback');
        if (! $s['enabled']) {
            return 0;
        }
        $reached = 0;
        foreach ($this->endedServices('feedback_asked_at', $s['timing'] * 60, 48) as $service) {
            $service->forceFill(['feedback_asked_at' => now()])->save();
            $answered = ServiceFeedback::where('service_id', $service->id)->pluck('member_id');
            $members = $service->members()->where('members.status', Member::ACTIVE)->whereNotIn('members.id', $answered)->with('user')->get();
            $reached += $this->deliver($members, 'feedback', $s, fn (Member $m) => $this->serviceVars($service, $m), 'How was '.$service->label().'?', route('account.feedback'), $service);
        }

        return $reached;
    }

    /**
     * Services that ended at least $minutes ago, but not more than $withinHours ago, not yet handled.
     *
     * @return Collection<int, Service>
     */
    private function endedServices(string $column, int $minutes, int $withinHours): Collection
    {
        return Service::whereNull($column)->whereNotNull('starts_at')
            ->where('starts_at', '>', now()->subHours($withinHours + 12))->where('starts_at', '<', now())
            ->get()
            ->filter(function (Service $service) use ($minutes, $withinHours) {
                $ended = $service->ends_at ?? $service->starts_at->copy()->addHours(2);

                return $ended->copy()->addMinutes($minutes)->lte(now()) && $ended->gt(now()->subHours($withinHours));
            })->values();
    }

    /**
     * @return array<string, string>
     */
    public function serviceVars(Service $service, ?Member $member): array
    {
        return [
            'first_name' => $member ? Member::firstNameOf($member->name) : 'friend',
            'service' => $service->label(),
            'day' => $this->day($service->starts_at),
            'time' => $service->starts_at?->format('g:i A') ?? '',
            'church' => config('church.short_name'),
        ];
    }

    private function day(?Carbon $at): string
    {
        return match (true) {
            $at === null => '',
            $at->isToday() => 'today',
            $at->isTomorrow() => 'tomorrow',
            default => 'on '.$at->format('l'),
        };
    }

    /**
     * Send one kind of message to many members by the chosen ways, and note it for each.
     *
     * @param  Collection<int, Member>  $members
     * @param  callable(Member): array<string, string>  $vars
     */
    public function deliver(Collection $members, string $kind, array $s, callable $vars, string $title, ?string $url, ?Service $service = null): int
    {
        $reached = 0;
        $rows = [];
        $channels = collect(['push', 'sms', 'email'])->filter(fn ($c) => $s[$c])->join(',');
        $texts = [];

        foreach ($members as $member) {
            $body = self::render($s['text'], $vars($member));
            $got = false;
            if ($s['push'] && $member->user && $this->push->configured()) {
                $subs = PushSubscription::where('user_id', $member->user_id)->get();
                if ($subs->isNotEmpty()) {
                    $this->push->send($subs, ['title' => $title, 'body' => $body, 'url' => $url, 'tag' => "reminder-{$kind}"]);
                    $got = true;
                }
            }
            if ($s['sms'] && $member->notify_sms && $member->phone) {
                $texts[$body][] = $member->phone;
            }
            $email = $member->user?->email ?? $member->email;
            if ($s['email'] && $email) {
                $got = $this->mail($email, new ReminderMail($title, $body, $url)) || $got;
            }
            if ($member->user_id) {
                $rows[] = ['kind' => $kind, 'user_id' => $member->user_id, 'service_id' => $service?->id, 'title' => mb_substr($title, 0, 160), 'body' => $body, 'url' => $url, 'channels' => $channels, 'created_at' => now()];
                $got = true; // it's in their inbox too
            }
            $reached += $got || ($s['sms'] && $member->notify_sms && $member->phone) ? 1 : 0;
        }

        if ($texts && $this->sms->configured()) {
            foreach ($texts as $body => $numbers) {
                $this->sms->send($numbers, mb_substr($body, 0, 459));
            }
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            ReminderLog::insert($chunk);
        }

        return $reached;
    }

    /**
     * A sample of one kind sent to the staff member looking at it, by the ways it's set to use.
     *
     * @return list<string> the ways it went
     */
    public function test(User $user, string $kind, array $s): array
    {
        $service = Service::whereNotNull('starts_at')->where('starts_at', '>', now()->subDay())->orderBy('starts_at')->first()
            ?? new Service(['kind' => 'sunday', 'starts_at' => now()->next('Sunday')->setTime(9, 0)]);
        $vars = $kind === 'market'
            ? ['name' => Member::firstNameOf($user->name), 'shop' => 'Your shop', 'market' => config('church.brand.market_name'), 'end_date' => now()->addDays(max(1, $s['timing']))->format('l j F'), 'days_left' => self::daysLeft(max(1, $s['timing']))]
            : [...$this->serviceVars($service, null), 'first_name' => Member::firstNameOf($user->name)];
        $body = self::render($s['text'], $vars);
        $title = '[Test] '.self::KINDS[$kind]['label'];
        $went = [];
        if ($s['push'] && $this->push->configured() && ($subs = PushSubscription::where('user_id', $user->id)->get())->isNotEmpty()) {
            $this->push->send($subs, ['title' => $title, 'body' => $body, 'url' => route('home'), 'tag' => 'reminder-test']);
            $went[] = 'app notification';
        }
        if ($s['sms'] && $this->sms->configured() && $user->member?->phone) {
            $this->sms->send([$user->member->phone], $body) && $went[] = 'text message';
        }
        if ($s['email'] && $user->email && $this->mail($user->email, new ReminderMail($title, $body, route('home')))) {
            $went[] = 'email';
        }

        return $went;
    }

    public static function daysLeft(int $days): string
    {
        return match (true) {
            $days <= 0 => 'today',
            $days === 1 => '1 day left',
            default => "{$days} days left",
        };
    }

    public function mail(string $to, object $mailable): bool
    {
        try {
            Mail::to($to)->send($mailable);

            return true;
        } catch (Throwable $e) {
            Log::error('Could not send a reminder email', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public static function on(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
