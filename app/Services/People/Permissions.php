<?php

namespace App\Services\People;

use App\Mail\ReminderMail;
use App\Models\Member;
use App\Models\PermissionRequest;
use App\Models\PushSubscription;
use App\Models\Service;
use App\Models\Unit;
use App\Models\User;
use App\Services\Messaging\Reminders;
use App\Services\Messaging\WebPush;
use App\Services\Messaging\WhatsApp;
use Illuminate\Support\Collection;

/**
 * Workers' permission to be away from a service or to come late. The
 * worker asks with a reason; the heads of their units and the pastors are
 * told and can grant or decline it; the worker hears back.
 */
class Permissions
{
    public function __construct(private WebPush $push, private Reminders $reminders, private WhatsApp $whatsapp) {}

    /**
     * Workers and ministers, and anyone serving in a unit, can ask.
     */
    public static function canAsk(?Member $member): bool
    {
        return $member !== null && $member->status === Member::ACTIVE
            && (in_array($member->rank, ['worker', 'minister'], true) || $member->units()->exists());
    }

    /**
     * Services coming up in the next few weeks, to choose from.
     *
     * @return Collection<int, Service>
     */
    public function upcomingServices(): Collection
    {
        return Service::where('held_on', '>=', today()->toDateString())->where('held_on', '<=', today()->addWeeks(6)->toDateString())
            ->orderBy('held_on')->orderBy('starts_at')->get();
    }

    public function ask(Member $member, array $data): PermissionRequest
    {
        $request = $member->permissionRequests()->create($data);
        $request->load('member.units', 'service');

        $title = $member->name.': '.mb_strtolower(PermissionRequest::KINDS[$request->kind]);
        $body = $request->summary().'. Reason: '.$request->reason;
        foreach ($this->leaders($member) as $user) {
            $url = $user->canManage('permissions') ? route('admin.permissions.index') : route('unit.permissions', $this->unitLedBy($member, $user));
            $this->tell($user, $title, $body, $url, 'Answer it');
        }

        return $request;
    }

    public function answer(PermissionRequest $request, User $by, string $status, ?string $note): void
    {
        $request->forceFill([
            'status' => $status,
            'answered_by' => $by->id,
            'answer_note' => $note ?: null,
            'answered_at' => now(),
        ])->save();

        $member = $request->member;
        $headline = $status === 'granted' ? 'Permission granted 👍' : 'Your permission request';
        $body = $request->summary().': '.mb_strtolower($request->statusLabel()).' by '.$by->communityName().'.'.($note ? ' "'.$note.'"' : '');
        if ($member->user) {
            $this->tell($member->user, $headline, $body, route('account.permission'), 'See it');
        } else {
            $this->whatsapp->toMember($member, $headline, $body, route('account.permission'));
        }
    }

    /**
     * Everyone who is told about a worker's request: the heads of their units and the pastors.
     *
     * @return Collection<int, User>
     */
    public function leaders(Member $member): Collection
    {
        $heads = $member->units->flatMap(fn (Unit $u) => [$u->hod, $u->assistant])->filter()
            ->reject(fn (Member $m) => $m->id === $member->id)->map->user->filter();
        $pastors = User::whereIn('role', ['pastor', 'super'])->get();

        return $heads->concat($pastors)->unique('id')->values();
    }

    private function unitLedBy(Member $member, User $user): ?Unit
    {
        return $member->units->first(fn (Unit $u) => $u->isLedBy($user->member));
    }

    private function tell(User $user, string $title, string $body, string $url, string $button): void
    {
        if ($this->push->configured()) {
            $this->push->send(PushSubscription::where('user_id', $user->id)->get(), ['title' => $title, 'body' => mb_substr($body, 0, 180), 'url' => $url, 'tag' => 'permission']);
        }
        if ($user->member) {
            $this->whatsapp->toMember($user->member, $title, $body, $url);
        }
        if ($email = $user->email ?? $user->member?->email) {
            $this->reminders->mail($email, new ReminderMail($title, $body, $url, $button));
        }
    }
}
