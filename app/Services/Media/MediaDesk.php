<?php

namespace App\Services\Media;

use App\Mail\MediaRequestUpdate;
use App\Models\MediaRequest;
use App\Models\Member;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The life of a media request: asked for, taken on, delivered, and closed by whoever asked.
 * Each step emails the other side.
 */
class MediaDesk
{
    public function __construct(private MediaLibrary $library) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<UploadedFile>  $files  examples, logos, wording the team should use
     */
    public function submit(User $by, array $data, array $files = []): MediaRequest
    {
        $request = DB::transaction(function () use ($by, $data, $files) {
            $request = new MediaRequest($data);
            $request->requested_by = $by->id;
            $request->save();
            foreach ($files as $file) {
                $this->library->store($file, $by, 'reference', $request);
            }

            return $request;
        });

        $this->toTeam($request, 'New request: '.$request->title, ($by->member?->name ?? $by->name).' ('.$request->fromLabel().') asked for '.Str::lower($request->typeLabel()).', needed by '.$request->needed_by->format('D j M').'.');

        return $request;
    }

    /**
     * Give it to a media team member (a leader assigning, or a member taking it themselves).
     */
    public function assign(MediaRequest $request, Member $member, User $by): void
    {
        if (! in_array($request->status, ['new', 'in_progress'], true)) {
            throw ValidationException::withMessages(['assign' => 'This request is already '.Str::lower(MediaRequest::STATUSES[$request->status]).'.']);
        }
        $first = $request->status === 'new';
        $request->forceFill(['assigned_member_id' => $member->id, 'status' => 'in_progress', 'accepted_at' => $request->accepted_at ?? now()])->save();

        if ($first) {
            $this->toRequester($request, 'The media team is on it', Member::firstNameOf($member->name).' from the media team is working on "'.$request->title.'".');
        }
        if ($member->user_id !== $by->id) {
            $this->send([$member->user?->email ?? $member->email], $request, 'Assigned to you: '.$request->title, 'You\'ve been given this request. It\'s needed by '.$request->needed_by->format('D j M').'.', true);
        }
    }

    public function decline(MediaRequest $request, string $reason): void
    {
        if (! $request->isOpen()) {
            throw ValidationException::withMessages(['reason' => 'Only open requests can be declined.']);
        }
        $request->forceFill(['status' => 'declined', 'decline_reason' => $reason, 'closed_at' => now()])->save();
        $this->toRequester($request, 'About your request', 'The media team can\'t take on "'.$request->title.'": '.$reason);
    }

    /**
     * Hand over the finished work: files, links, and a note.
     *
     * @param  list<UploadedFile>  $files
     * @param  list<string>  $links
     */
    public function deliver(MediaRequest $request, User $by, array $files, array $links, ?string $note): void
    {
        if (! $request->isOpen()) {
            throw ValidationException::withMessages(['files' => 'This request isn\'t open.']);
        }
        if ($files === [] && $links === [] && blank($note)) {
            throw ValidationException::withMessages(['files' => 'Add the finished files, a link, or a note.']);
        }

        DB::transaction(function () use ($request, $by, $files, $links, $note) {
            foreach ($files as $file) {
                $this->library->store($file, $by, 'deliverable', $request);
            }
            foreach ($links as $link) {
                $this->library->link($link, Str::limit(parse_url($link, PHP_URL_HOST) ?: 'Link', 150, ''), $by, 'deliverable', $request);
            }
            if (filled($note)) {
                $request->comments()->make(['body' => $note])->forceFill(['user_id' => $by->id])->save();
            }
            $request->forceFill([
                'status' => 'delivered',
                'delivered_at' => now(),
                'assigned_member_id' => $request->assigned_member_id ?? $by->member?->id,
                'accepted_at' => $request->accepted_at ?? now(),
            ])->save();
        });

        $this->toRequester($request, 'Ready: '.$request->title, 'The media team has delivered your request. Have a look, download what you need, and mark it done or ask for changes.');
    }

    /**
     * The person who asked: happy with it, or wants changes (which reopens it).
     */
    public function respond(MediaRequest $request, User $by, bool $happy, ?string $changes = null): void
    {
        if ($request->status !== 'delivered' && ! ($happy && $request->status === 'in_progress')) {
            throw ValidationException::withMessages(['changes' => 'This request isn\'t waiting for you.']);
        }
        if ($happy) {
            $request->forceFill(['status' => 'completed', 'closed_at' => now()])->save();

            return;
        }
        if (blank($changes)) {
            throw ValidationException::withMessages(['changes' => 'Say what should change.']);
        }
        $request->comments()->make(['body' => $changes])->forceFill(['user_id' => $by->id])->save();
        $request->forceFill(['status' => 'in_progress'])->save();
        $this->toAssigneeOrTeam($request, 'Changes asked for: '.$request->title, $changes);
    }

    public function cancel(MediaRequest $request): void
    {
        if (! $request->isOpen()) {
            throw ValidationException::withMessages(['cancel' => 'This request can\'t be cancelled now.']);
        }
        $request->forceFill(['status' => 'cancelled', 'closed_at' => now()])->save();
        $this->toAssigneeOrTeam($request, 'Cancelled: '.$request->title, 'Whoever asked has cancelled this request.');
    }

    /**
     * A leader closes a delivered request the requester never came back to.
     */
    public function close(MediaRequest $request): void
    {
        if ($request->status !== 'delivered') {
            throw ValidationException::withMessages(['close' => 'Only delivered requests can be closed.']);
        }
        $request->forceFill(['status' => 'completed', 'closed_at' => now()])->save();
    }

    public function comment(MediaRequest $request, User $by, string $body, bool $internal = false, bool $fromTeam = false): void
    {
        $request->comments()->make(['body' => $body, 'is_internal' => $internal && $fromTeam])->forceFill(['user_id' => $by->id])->save();
        if ($internal && $fromTeam) {
            return;
        }
        $name = $by->member?->name ?? $by->name;
        $fromTeam
            ? $this->toRequester($request, 'New message about '.$request->title, $name.' from the media team: "'.Str::limit($body, 400).'"')
            : $this->toAssigneeOrTeam($request, 'New message about '.$request->title, $name.': "'.Str::limit($body, 400).'"');
    }

    private function toRequester(MediaRequest $request, string $subject, string $message): void
    {
        $this->send([$request->requester?->email], $request, $subject, $message, false);
    }

    private function toTeam(MediaRequest $request, string $subject, string $message): void
    {
        $unit = Unit::ofKind('media')?->load('hod.user', 'assistant.user');
        $this->send(collect([$unit?->hod, $unit?->assistant])->filter()->map(fn (Member $m) => $m->user?->email ?? $m->email)->all(), $request, $subject, $message, true);
    }

    private function toAssigneeOrTeam(MediaRequest $request, string $subject, string $message): void
    {
        $assignee = $request->assignee;
        $assignee
            ? $this->send([$assignee->user?->email ?? $assignee->email], $request, $subject, $message, true)
            : $this->toTeam($request, $subject, $message);
    }

    /**
     * @param  array<int, ?string>  $emails
     */
    private function send(array $emails, MediaRequest $request, string $subject, string $message, bool $forTeam): void
    {
        foreach (array_unique(array_filter($emails)) as $email) {
            try {
                Mail::to($email)->send(new MediaRequestUpdate($request, $subject, $message, $forTeam));
            } catch (Throwable $e) {
                Log::error('Could not send media request email', ['request' => $request->reference, 'error' => $e->getMessage()]);
            }
        }
    }
}
