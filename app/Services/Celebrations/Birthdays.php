<?php

namespace App\Services\Celebrations;

use App\Models\BirthdayCelebration;
use App\Models\CommunityMessage;
use App\Models\CommunityRoom;
use App\Models\Member;
use App\Models\PushSubscription;
use App\Services\Messaging\WebPush;
use GdImage;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Celebrating members on their birthday: their card is made from the church's template, posted in
 * the community (for members with an account), sent to their phone, and shown on their account.
 * Members who'd rather not be celebrated publicly can switch it off in their account.
 */
class Birthdays
{
    public function __construct(private BirthdayCard $cards, private WebPush $push) {}

    /**
     * Members to celebrate today.
     *
     * @return Collection<int, Member>
     */
    public function today(): Collection
    {
        return Member::active()->whereNotNull('birthday')->where('celebrate_birthday', true)->with('user')->get()
            ->filter(fn (Member $m) => $m->hasBirthdayToday())->values();
    }

    /**
     * Celebrate everyone whose birthday is today; returns how many were celebrated now.
     */
    public function run(): int
    {
        $done = 0;
        foreach ($this->today() as $member) {
            try {
                $done += $this->celebrate($member) ? 1 : 0;
            } catch (Throwable $e) {
                Log::error('Could not celebrate a birthday', ['member' => $member->id, 'error' => $e->getMessage()]);
            }
        }

        return $done;
    }

    /**
     * Once a year per member; false if they were already celebrated this year.
     */
    public function celebrate(Member $member): ?BirthdayCelebration
    {
        if (BirthdayCelebration::where('member_id', $member->id)->where('year', today()->year)->exists()) {
            return null;
        }

        $path = 'celebrations/'.today()->year.'/'.Str::random(32).'.png';
        Storage::disk('public')->put($path, $this->cards->forMember($member));
        try {
            $celebration = BirthdayCelebration::create(['member_id' => $member->id, 'year' => today()->year, 'card_path' => $path]);
        } catch (UniqueConstraintViolationException) {
            Storage::disk('public')->delete($path); // celebrated at the same moment by another run

            return null;
        }

        if ($member->user && config('church.birthday.community') && ($room = $this->room())) {
            $message = new CommunityMessage([
                'community_room_id' => $room->id,
                'user_id' => $member->user_id,
                'kind' => 'birthday',
                'body' => '🎉 Today is '.$member->name.'\'s birthday! Join the Tribe in celebrating them: reply with your wishes and prayers. 🎂',
                'image_path' => $path,
                'image_thumb_path' => $this->thumbnail($path),
            ]);
            $message->save();
            $celebration->forceFill(['community_message_id' => $message->id])->save();
        }

        if ($member->user && $this->push->configured()) {
            $this->push->send(PushSubscription::where('user_id', $member->user_id)->get(), [
                'title' => 'Happy birthday, '.$member->firstName().'! 🎉',
                'body' => 'The whole '.config('church.short_name').' family celebrates you today. Your birthday card is waiting.',
                'url' => route('account').'#birthday',
                'tag' => 'birthday',
            ]);
        }

        return $celebration;
    }

    /**
     * This year's celebration for a member, if their birthday was in the last few days.
     */
    public function recent(Member $member, int $days = 3): ?BirthdayCelebration
    {
        return BirthdayCelebration::with('message')->where('member_id', $member->id)
            ->where('created_at', '>=', now()->subDays($days))->latest('id')->first();
    }

    /**
     * Where birthday posts go: the general room, or the first open room.
     */
    private function room(): ?CommunityRoom
    {
        return CommunityRoom::where('slug', 'general')->whereNull('unit_id')->first()
            ?? CommunityRoom::whereNull('unit_id')->orderBy('position')->first();
    }

    private function thumbnail(string $path): string
    {
        $img = imagecreatefromstring(Storage::disk('public')->get($path));
        $thumb = $img instanceof GdImage ? imagescale($img, 640) : false;
        if (! $thumb instanceof GdImage) {
            return $path;
        }
        ob_start();
        imagewebp($thumb, null, 80);
        $thumbPath = Str::beforeLast($path, '.').'-thumb.webp';
        Storage::disk('public')->put($thumbPath, (string) ob_get_clean());

        return $thumbPath;
    }
}
