<?php

namespace App\Services\Messaging;

use App\Mail\CampaignMail;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Member;
use App\Models\PushSubscription;
use App\Models\Shop;
use App\Models\User;
use App\Support\Notify;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * The Message centre: who a message is for, and sending it in batches from the scheduler
 * (messages:send, every minute), by email, WhatsApp and/or app notification. Members also find
 * it in Messages in the app. Words like {first_name} are filled in for each person.
 */
class Campaigns
{
    public const AUDIENCES = [
        'members' => 'Members',
        'sellers' => 'Sellers on the market',
        'staff' => 'Staff',
        'everyone' => 'Everyone (members, sellers and staff)',
    ];

    public const MEMBER_STATUSES = ['active' => 'Approved members', 'pending' => 'Waiting for approval', 'inactive' => 'Inactive', 'all' => 'All of them'];

    public const SELLER_STATUSES = ['all' => 'All approved shops', 'listed' => 'Shops listed now (subscription active)', 'lapsed' => 'Shops whose subscription has ended'];

    public const FILL_INS = ['first_name', 'name', 'church'];

    public const BATCH = 80;

    public function __construct(private WhatsApp $whatsapp, private WebPush $push) {}

    /**
     * Clean audience settings from a form.
     *
     * @return array<string, mixed>
     */
    public static function audience(array $input): array
    {
        $type = array_key_exists($input['type'] ?? '', self::AUDIENCES) ? $input['type'] : 'members';

        return [
            'type' => $type,
            'member_status' => array_key_exists($input['member_status'] ?? '', self::MEMBER_STATUSES) ? $input['member_status'] : 'active',
            'unit_id' => ($input['unit_id'] ?? null) ? (int) $input['unit_id'] : null,
            'fellowship_id' => ($input['fellowship_id'] ?? null) ? (int) $input['fellowship_id'] : null,
            'gender' => in_array($input['gender'] ?? '', ['female', 'male'], true) ? $input['gender'] : null,
            'birthday_month' => ($m = (int) ($input['birthday_month'] ?? 0)) >= 1 && $m <= 12 ? $m : null,
            'seller_status' => array_key_exists($input['seller_status'] ?? '', self::SELLER_STATUSES) ? $input['seller_status'] : 'all',
        ];
    }

    /**
     * Everyone the audience reaches, one entry per person (the same email or phone only once).
     *
     * @return Collection<int, array{kind: string, contact_id: int, user_id: ?int, name: string, email: ?string, phone: ?string, whatsapp_ok: bool}>
     */
    public function contacts(array $audience): Collection
    {
        $type = $audience['type'] ?? 'members';
        $people = collect();

        if (in_array($type, ['members', 'everyone'], true)) {
            $members = Member::with('user:id,email')
                ->when(($audience['member_status'] ?? 'active') !== 'all' || $type === 'everyone', fn ($q) => $q->where('status', $type === 'everyone' ? Member::ACTIVE : $audience['member_status']))
                ->when($type === 'members' && ($audience['unit_id'] ?? null), fn ($q) => $q->where('unit_id', $audience['unit_id']))
                ->when($type === 'members' && ($audience['fellowship_id'] ?? null), fn ($q) => $q->where('fellowship_id', $audience['fellowship_id']))
                ->when($type === 'members' && ($audience['gender'] ?? null), fn ($q) => $q->where('gender', $audience['gender']))
                ->orderBy('id')->get();
            if ($type === 'members' && ($month = $audience['birthday_month'] ?? null)) {
                $members = $members->filter(fn (Member $m) => $m->birthday && (int) $m->birthday->format('n') === (int) $month);
            }
            $people = $people->concat($members->map(fn (Member $m) => [
                'kind' => 'member', 'contact_id' => $m->id, 'user_id' => $m->user_id, 'name' => $m->name,
                'email' => $m->user?->email ?? $m->email, 'phone' => $m->phone, 'whatsapp_ok' => (bool) $m->notify_sms,
            ]));
        }

        if (in_array($type, ['sellers', 'everyone'], true)) {
            $status = $type === 'everyone' ? 'listed' : ($audience['seller_status'] ?? 'all');
            $shops = Shop::approved()
                ->when($status === 'listed', fn ($q) => $q->listed())
                ->when($status === 'lapsed', fn ($q) => $q->whereNotIn('id', Shop::query()->listed()->select('id')))
                ->orderBy('id')->get();
            $people = $people->concat($shops->map(fn (Shop $s) => [
                'kind' => 'seller', 'contact_id' => $s->id, 'user_id' => $s->user_id, 'name' => $s->owner_name ?: $s->name,
                'email' => $s->email, 'phone' => $s->whatsapp ?: $s->phone, 'whatsapp_ok' => true,
            ]));
        }

        if (in_array($type, ['staff', 'everyone'], true)) {
            $staff = User::whereIn('role', array_keys(config('roles.roles')))->orderBy('id')->get();
            $people = $people->concat($staff->map(fn (User $u) => [
                'kind' => 'staff', 'contact_id' => $u->id, 'user_id' => $u->id, 'name' => $u->name,
                'email' => $u->email, 'phone' => $u->member?->phone, 'whatsapp_ok' => (bool) ($u->member?->notify_sms ?? true),
            ]));
        }

        // One message per person even if they're, say, a member and a seller.
        $seen = [];

        return $people->filter(function ($p) use (&$seen) {
            $key = $p['user_id'] ? 'u'.$p['user_id'] : ($p['email'] ? 'e'.mb_strtolower($p['email']) : ($p['phone'] ? 'p'.Member::internationalPhone($p['phone']) : null));
            if ($key === null) {
                return false; // no way to reach them
            }
            if (isset($seen[$key])) {
                return false;
            }
            $seen[$key] = true;

            return true;
        })->values();
    }

    /**
     * How many people it reaches, and how many of them each way.
     *
     * @return array{people: int, email: int, whatsapp: int, app: int}
     */
    public function reach(array $audience): array
    {
        $people = $this->contacts($audience);
        $userIds = $people->pluck('user_id')->filter()->all();

        return [
            'people' => $people->count(),
            'email' => $people->filter(fn ($p) => filled($p['email']))->count(),
            'whatsapp' => $people->filter(fn ($p) => $p['whatsapp_ok'] && Member::internationalPhone($p['phone']))->count(),
            'app' => $userIds ? PushSubscription::whereIn('user_id', $userIds)->distinct('user_id')->count('user_id') : 0,
        ];
    }

    /**
     * Ready it to go: now, or at its scheduled time.
     */
    public function launch(Campaign $campaign): void
    {
        $campaign->forceFill(['status' => $campaign->scheduled_at?->isFuture() ? 'scheduled' : 'sending'])->save();
    }

    /**
     * Work on the next message due. Returns true if there was one.
     */
    public function sendBatch(): bool
    {
        $campaign = Campaign::where(fn ($q) => $q->where('status', 'sending')->orWhere(fn ($s) => $s->where('status', 'scheduled')->where('scheduled_at', '<=', now())))
            ->orderBy('id')->first();
        if (! $campaign) {
            return false;
        }
        if ($campaign->recipients_count === null) {
            $this->snapshot($campaign);
        }

        $batch = $campaign->recipients()->where('status', 'pending')->orderBy('id')->take(self::BATCH)->get();
        foreach ($batch as $recipient) {
            $this->deliver($campaign, $recipient);
        }

        if (! $campaign->recipients()->where('status', 'pending')->exists()) {
            $campaign->forceFill(['status' => 'sent', 'finished_at' => now()])->save();
        }

        return true;
    }

    /**
     * Fix the list of people when sending starts, so it doesn't change halfway through.
     */
    private function snapshot(Campaign $campaign): void
    {
        DB::transaction(function () use ($campaign) {
            $rows = $this->contacts($campaign->audience)->map(fn ($p) => [...$p, 'campaign_id' => $campaign->id, 'status' => 'pending'])->all();
            foreach (array_chunk($rows, 500) as $chunk) {
                CampaignRecipient::insert($chunk);
            }
            $campaign->forceFill(['status' => 'sending', 'started_at' => now(), 'recipients_count' => count($rows)])->save();
        });
    }

    private function deliver(Campaign $campaign, CampaignRecipient $r): void
    {
        $vars = ['first_name' => Member::firstNameOf($r->name), 'name' => $r->name, 'church' => config('church.short_name')];
        $subject = Reminders::render($campaign->subject, $vars);
        $body = Reminders::render($campaign->body, $vars);
        $url = $r->kind === 'seller' ? route('vendor.products.index') : route('account.inbox');
        $done = [];
        $errors = [];

        if ($campaign->uses('email') && filled($r->email)) {
            try {
                Mail::to($r->email)->send(new CampaignMail($subject, $body, $url, $r->kind));
                $done[] = 'email';
            } catch (Throwable $e) {
                $errors[] = 'email: '.mb_substr($e->getMessage(), 0, 80);
                Log::warning('Message centre email not sent', ['campaign' => $campaign->id, 'error' => $e->getMessage()]);
            }
        }
        if ($campaign->uses('whatsapp') && $r->whatsapp_ok && ($number = Member::internationalPhone($r->phone))) {
            if ($this->whatsapp->ready() && $this->whatsapp->send([$number], $subject, $body) > 0) {
                $done[] = 'whatsapp';
            } else {
                $errors[] = 'WhatsApp not accepted';
            }
        }
        if ($campaign->uses('push') && $r->user_id && Notify::on('push') && $this->push->configured()) {
            $subs = PushSubscription::where('user_id', $r->user_id)->get();
            if ($subs->isNotEmpty() && $this->push->send($subs, ['title' => $subject, 'body' => mb_substr($body, 0, 180), 'url' => $url, 'tag' => 'campaign-'.$campaign->id])['sent'] > 0) {
                $done[] = 'app';
            }
        }
        // Members can always read it in Messages in the app.
        $inbox = $r->kind !== 'seller' && $r->user_id;

        $r->forceFill([
            'status' => $done || $inbox ? 'sent' : 'failed',
            'channels' => implode(',', $done),
            'error' => $done || $inbox ? null : (implode('; ', $errors) ?: 'No email, WhatsApp number or app to reach them'),
            'sent_at' => now(),
        ])->save();

        $campaign->forceFill([
            'email_sent' => $campaign->email_sent + (in_array('email', $done, true) ? 1 : 0),
            'whatsapp_sent' => $campaign->whatsapp_sent + (in_array('whatsapp', $done, true) ? 1 : 0),
            'push_sent' => $campaign->push_sent + (in_array('app', $done, true) ? 1 : 0),
            'failed' => $campaign->failed + ($done || $inbox ? 0 : 1),
        ])->save();
    }

    /**
     * A copy to the staff member writing it, by the ways chosen.
     *
     * @return list<string>
     */
    public function test(Campaign $campaign, User $user): array
    {
        $vars = ['first_name' => Member::firstNameOf($user->name), 'name' => $user->name, 'church' => config('church.short_name')];
        $subject = '[Test] '.Reminders::render($campaign->subject, $vars);
        $body = Reminders::render($campaign->body, $vars);
        $went = [];
        if ($campaign->uses('email') && $user->email) {
            try {
                Mail::to($user->email)->send(new CampaignMail($subject, $body, route('account.inbox'), 'staff'));
                $went[] = 'email';
            } catch (Throwable) {
                // reported below as nothing sent
            }
        }
        if ($campaign->uses('whatsapp') && $this->whatsapp->ready() && ($n = Member::internationalPhone($user->member?->phone))) {
            $this->whatsapp->send([$n], $subject, $body) && $went[] = 'WhatsApp';
        }
        if ($campaign->uses('push') && Notify::on('push') && $this->push->configured() && ($subs = PushSubscription::where('user_id', $user->id)->get())->isNotEmpty()) {
            $this->push->send($subs, ['title' => $subject, 'body' => mb_substr($body, 0, 180), 'url' => route('account.inbox'), 'tag' => 'campaign-test']);
            $went[] = 'app notification';
        }

        return $went;
    }
}
