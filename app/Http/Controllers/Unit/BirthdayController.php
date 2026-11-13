<?php

namespace App\Http\Controllers\Unit;

use App\Http\Controllers\Controller;
use App\Models\BirthdayDesign;
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
            'designs' => $cards->all(),
            'enabled' => $cards->enabled(),
            'uploads' => BirthdayDesign::orderBy('id')->get(),
            'community' => (bool) config('church.birthday.community'),
            'upcoming' => $upcoming,
            'cards' => $cards,
            'canLead' => Gate::allows('lead-media', $unit),
        ]);
    }

    /**
     * Which designs are in use, and the words on the built-in ones.
     */
    public function updateTemplate(Request $request, Unit $unit, BirthdayCard $cards): RedirectResponse
    {
        $data = $request->validate([
            'designs' => ['required', 'array', 'min:1'],
            'designs.*' => [Rule::in(array_keys($cards->all()))],
            'headline' => ['required', 'string', 'max:30'],
            'message' => ['required', 'string', 'max:200'],
            'verse' => ['nullable', 'string', 'max:160'],
        ], [
            'designs.required' => 'Choose at least one design.',
            'message.max' => 'Keep the message under 200 characters so it fits the card.',
            'verse.max' => 'Keep the verse under 160 characters.',
        ]);

        Setting::putMany([
            'church.birthday.design' => implode(',', $data['designs']),
            'church.birthday.headline' => $data['headline'],
            'church.birthday.message' => $data['message'],
            'church.birthday.verse' => $data['verse'] ?? '',
            'church.birthday.community' => $request->boolean('community') ? '1' : '0',
        ]);

        return back()->with('status', count($data['designs']) > 1 ? 'Saved. Each member gets one of your '.count($data['designs']).' designs.' : 'Saved.');
    }

    /**
     * A card with the viewer's own name as the example: any design, or an uploaded design
     * with placement that hasn't been saved yet (for the editor's live preview).
     */
    public function preview(Request $request, Unit $unit, BirthdayCard $cards): Response
    {
        $design = array_key_exists((string) $request->query('design'), $cards->all()) ? $request->query('design') : null;
        $layout = null;
        if ($design && str_starts_with($design, 'upload-') && $request->has('layout')) {
            $layout = $this->layoutFrom((array) $request->query('layout'), BirthdayDesign::findOrFail((int) substr($design, 7))->layout());
        }
        $member = $request->user()->member;
        $bytes = $cards->render($member?->name ?? $request->user()->name, today(), $member?->photo_path, $design, 7, $layout);

        return $this->png($bytes, 'birthday-card-preview.png');
    }

    /**
     * A finished design made elsewhere (Canva, Photoshop…), uploaded as a picture.
     */
    public function storeDesign(Request $request, Unit $unit, Images $images, BirthdayCard $cards): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:20480'],
        ], ['image.max' => 'Choose a picture under 20 MB.']);
        try {
            $saved = $images->store($request->file('image'), 'celebrations/designs', 2160, 160);
        } catch (RuntimeException $e) {
            return back()->withErrors(['image' => $e->getMessage()]);
        }
        $images->delete($saved['thumb_path']);

        $design = new BirthdayDesign(['name' => $data['name']]);
        $design->background_path = $saved['path'];
        $design->created_by = $request->user()->id;
        $design->save();
        // Put it to use alongside the others.
        Setting::putMany(['church.birthday.design' => implode(',', [...$cards->enabled(), $design->key()])]);

        return redirect()->route('unit.birthdays.designs.edit', [$unit, $design])
            ->with('status', 'Design uploaded. Now place the photo and name where they belong on it.');
    }

    public function editDesign(Unit $unit, BirthdayDesign $design): View
    {
        return view('unit.media.birthday-design', ['unit' => $unit, 'design' => $design, 'layout' => $design->layout()]);
    }

    public function updateDesign(Request $request, Unit $unit, BirthdayDesign $design): RedirectResponse
    {
        $request->validate(['name' => ['required', 'string', 'max:80']]);
        $design->update(['name' => $request->input('name'), 'layout' => $this->layoutFrom((array) $request->input('layout', []), BirthdayDesign::DEFAULTS, true)]);

        return redirect()->route('unit.birthdays.index', $unit)->with('status', "\"{$design->name}\" saved.");
    }

    public function destroyDesign(Request $request, Unit $unit, BirthdayDesign $design, Images $images, BirthdayCard $cards): RedirectResponse
    {
        $key = $design->key();
        $images->delete($design->background_path);
        $design->delete();
        $left = array_values(array_filter($cards->enabled(), fn ($d) => $d !== $key));
        Setting::putMany(['church.birthday.design' => implode(',', $left ?: ['royal'])]);

        return back()->with('status', "\"{$design->name}\" removed.");
    }

    public function card(Request $request, Unit $unit, Member $member, BirthdayCard $cards): Response
    {
        abort_unless($member->birthday && $member->status === Member::ACTIVE, 404);

        $design = array_key_exists((string) $request->query('design'), $cards->all()) ? $request->query('design') : null;

        return $this->png($cards->forMember($member, $design), Str::slug($member->name).'-birthday.png', $request->boolean('download'));
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

    /**
     * Placement from the form or the preview, kept within sensible limits.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $fallback
     * @return array<string, mixed>
     */
    private function layoutFrom(array $input, array $fallback, bool $checkboxes = false): array
    {
        $num = fn (string $key, float $min, float $max) => isset($input[$key]) && is_numeric($input[$key]) ? max($min, min($max, (float) $input[$key])) : $fallback[$key];
        $colour = fn (string $key) => isset($input[$key]) && is_string($input[$key]) && preg_match('/^#[0-9a-fA-F]{6}$/', $input[$key]) ? strtolower($input[$key]) : $fallback[$key];
        // Unticked boxes aren't sent by a form, so when saving the form, missing means off.
        $bool = fn (string $key) => $checkboxes ? ! empty($input[$key]) : (isset($input[$key]) ? (bool) $input[$key] : $fallback[$key]);

        return [
            'photo_shape' => in_array($input['photo_shape'] ?? null, ['circle', 'rounded', 'none'], true) ? $input['photo_shape'] : $fallback['photo_shape'],
            'photo_x' => $num('photo_x', 5, 95),
            'photo_y' => $num('photo_y', 5, 95),
            'photo_size' => $num('photo_size', 10, 90),
            'photo_border' => ($input['photo_border_on'] ?? ($checkboxes ? null : '1')) ? $colour('photo_border') : null,
            'name_y' => $num('name_y', 5, 98),
            'name_size' => (int) $num('name_size', 24, 160),
            'name_font' => array_key_exists($input['name_font'] ?? '', BirthdayCard::NAME_FONTS) ? $input['name_font'] : $fallback['name_font'],
            'name_color' => $colour('name_color'),
            'name_upper' => $bool('name_upper'),
            'name_shadow' => $bool('name_shadow'),
            'show_date' => $bool('show_date'),
            'date_color' => $colour('date_color'),
            'show_message' => $bool('show_message'),
        ];
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
