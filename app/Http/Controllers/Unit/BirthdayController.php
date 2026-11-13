<?php

namespace App\Http\Controllers\Unit;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Setting;
use App\Models\SocialPost;
use App\Models\Unit;
use App\Services\Celebrations\BirthdayCard;
use App\Services\Content\Images;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/**
 * The media unit's birthday cards: the template every card is made from, and the cards for the
 * birthdays coming up, to download, put a photo on, or turn into a social post.
 */
class BirthdayController extends Controller
{
    public const DAYS = 30;

    public function index(Request $request, Unit $unit, BirthdayCard $cards): View
    {
        $start = today();
        $upcoming = Member::active()->whereNotNull('birthday')->with('unit', 'user')->get()
            ->map(function (Member $m) use ($start) {
                $next = $this->nextBirthday($m, $start);

                return $next->diffInDays($start, true) < self::DAYS ? ['member' => $m, 'date' => $next] : null;
            })
            ->filter()->sortBy(fn ($b) => $b['date']->timestamp)->values();

        return view('unit.media.birthdays', [
            'unit' => $unit,
            'template' => $cards->template(),
            'community' => (bool) config('church.birthday.community'),
            'upcoming' => $upcoming,
            'canLead' => Gate::allows('lead-media', $unit),
        ]);
    }

    public function updateTemplate(Request $request, Unit $unit, Images $images): RedirectResponse
    {
        $data = $request->validate([
            'design' => ['required', Rule::in(array_keys(BirthdayCard::DESIGNS))],
            'headline' => ['required', 'string', 'max:30'],
            'message' => ['required', 'string', 'max:200'],
            'verse' => ['nullable', 'string', 'max:160'],
            'background' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:15360'],
            'remove_background' => ['nullable', 'boolean'],
        ], ['message.max' => 'Keep the message under 200 characters so it fits the card.', 'verse.max' => 'Keep the verse under 160 characters.']);

        $values = [
            'church.birthday.design' => $data['design'],
            'church.birthday.headline' => $data['headline'],
            'church.birthday.message' => $data['message'],
            'church.birthday.verse' => $data['verse'] ?? '',
            'church.birthday.community' => $request->boolean('community') ? '1' : '0',
        ];
        $current = config('church.birthday.background');
        if ($request->boolean('remove_background') || $request->hasFile('background')) {
            if ($current) {
                Storage::disk('public')->delete($current);
            }
            $values['church.birthday.background'] = '';
        }
        if ($file = $request->file('background')) {
            try {
                $saved = $images->store($file, 'celebrations/backgrounds', 2200, 160);
            } catch (RuntimeException $e) {
                return back()->withErrors(['background' => $e->getMessage()]);
            }
            $images->delete($saved['thumb_path']);
            $values['church.birthday.background'] = $saved['path'];
        }
        Setting::putMany($values);

        return back()->with('status', 'Birthday card template saved.');
    }

    /**
     * The template as it looks now, with the viewer's own name as the example.
     */
    public function preview(Request $request, Unit $unit, BirthdayCard $cards): Response
    {
        $member = $request->user()->member;

        return $this->png($cards->render($member?->name ?? $request->user()->name, today(), $member?->photo_path, [], 7), 'birthday-card-preview.png');
    }

    public function card(Request $request, Unit $unit, Member $member, BirthdayCard $cards): Response
    {
        abort_unless($member->birthday && $member->status === Member::ACTIVE, 404);

        return $this->png($cards->forMember($member), Str::slug($member->name).'-birthday.png', $request->boolean('download'));
    }

    /**
     * Put a photo on a member's card (often sent to the team on WhatsApp).
     */
    public function photo(Request $request, Unit $unit, Member $member, Images $images): RedirectResponse
    {
        $request->validate(['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:15360']]);
        try {
            $saved = $images->store($request->file('photo'), 'members/photos', 1200, 160);
        } catch (RuntimeException $e) {
            return back()->withErrors(['photo' => $e->getMessage()]);
        }
        $images->delete($member->photo_path, $saved['thumb_path']);
        $member->forceFill(['photo_path' => $saved['path']])->save();

        return back()->with('status', "Photo added to {$member->name}'s card.");
    }

    /**
     * Start a social post with the card, for the HOD to approve.
     */
    public function social(Request $request, Unit $unit, Member $member, BirthdayCard $cards): RedirectResponse
    {
        abort_unless($member->birthday && $member->celebrate_birthday, 422, 'This member would rather their birthday wasn\'t shared.');
        $path = 'social/birthday-'.Str::random(24).'.png';
        Storage::disk('public')->put($path, $cards->forMember($member));

        $post = new SocialPost([
            'caption' => "Happy birthday, {$member->name}! 🎉🎂\n\nThe whole ".config('church.short_name').' family celebrates you today. May this new year be full of God\'s favour, joy and fruitfulness.'.(config('church.handle') ? "\n\n".config('church.handle') : '').' #HappyBirthday',
            'platforms' => ['facebook', 'instagram'],
            'publish_at' => null,
        ]);
        $post->media_path = $path;
        $post->media_type = 'image';
        $post->created_by = $request->user()->id;
        $post->status = 'draft';
        $post->save();

        return redirect()->route('unit.social.show', [$unit, $post])->with('status', 'Draft post made with the card. Check the caption, then send it for approval or publish.');
    }

    private function nextBirthday(Member $member, Carbon $from): Carbon
    {
        $day = $member->birthday->format('m-d') === '02-29' && ! $from->isLeapYear()
            ? Carbon::create($from->year, 2, 28)
            : $member->birthday->copy()->year($from->year);

        return $day->lt($from) ? $this->nextBirthday($member, $from->copy()->addYear()->startOfYear()) : $day->startOfDay();
    }

    private function png(string $bytes, string $name, bool $download = false): Response
    {
        return response($bytes, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, no-store',
            'Content-Disposition' => ($download ? 'attachment' : 'inline').'; filename="'.$name.'"',
        ]);
    }
}
