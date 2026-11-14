<?php

namespace Database\Seeders;

use App\Models\Fellowship;
use App\Models\FirstTimer;
use App\Models\Member;
use App\Models\PrayerRequest;
use App\Models\Service;
use App\Models\ServiceFeedback;
use App\Models\Suggestion;
use App\Models\Testimony;
use App\Models\Unit;
use App\Models\User;
use App\Services\Care\FollowUps;
use App\Services\Tokens\Ledger;
use App\Services\Tokens\Rewards;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Demo members for trying the app on a local copy: about 36 members with full profiles and
 * sign-ins, spread over the units and house fellowships, unit heads, two months of services with
 * attendance (and the tokens it earns), a few members waiting for approval, first-timers, and some
 * prayer requests, testimonies, service feedback and suggestions.
 *
 *     php artisan db:seed --class=MemberDemoSeeder
 *
 * Members sign in at /login as member1@demo.test … with the password "demo-password".
 * Safe to run again: members already made are left alone. Refuses to run on a live site.
 */
class MemberDemoSeeder extends Seeder
{
    public const PASSWORD = 'demo-password';

    private const AREAS = ['Yaba', 'Surulere', 'Ikeja', 'Lekki', 'Ajah', 'Gbagada', 'Akoka', 'Ikorodu', 'Festac', 'Ogba', 'Magodo', 'Ilupeju'];

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('The member demo is for local copies only, not a live site.');
        }
        mt_srand(2026); // the same demo every time

        $units = $this->units();
        $fellowships = $this->fellowships();
        $made = collect();
        foreach ($this->people() as $n => $p) {
            $email = 'member'.($n + 1).'@demo.test';
            if (User::where('email', $email)->exists()) {
                continue;
            }
            $made->push($this->member($n + 1, $email, $p, $units, $fellowships));
        }

        if ($made->isNotEmpty()) {
            $this->leaders($units);
            $services = $this->services();
            $this->attendance($made->where('status', Member::ACTIVE)->values(), $services);
            $this->tokens($made);
            $this->activity($made->where('status', Member::ACTIVE)->values(), $services);
            // Follow-ups for members who've missed the last few Sundays, as the daily check does.
            app(FollowUps::class)->scanAbsences();
        }

        mt_srand();
        $this->command?->info($made->isNotEmpty() ? "Made {$made->count()} demo members." : 'The demo members are already there.');
        $this->command?->line('Member logins: member1@demo.test … member'.count($this->people()).'@demo.test, password '.self::PASSWORD.' (at /login).');
        $this->command?->line('member1 has a birthday today: run `php artisan birthdays:celebrate` to see the celebration.');
    }

    /**
     * @return Collection<string, Unit>
     */
    private function units(): Collection
    {
        foreach (['Choir' => 'general', 'Media & tech' => 'media', 'Ushering' => 'general', 'Drama' => 'general', 'Prayer' => 'general', 'Welfare' => 'welfare'] as $name => $kind) {
            if (Unit::count() < 6) {
                Unit::firstOrCreate(['name' => $name], ['kind' => $kind]);
            }
        }

        return Unit::orderBy('id')->get()->keyBy('name');
    }

    /**
     * @return Collection<int, Fellowship>
     */
    private function fellowships(): Collection
    {
        if (Fellowship::count() === 0) {
            foreach (['Yaba & Akoka', 'Surulere', 'Ikeja & Ogba', 'Lekki & Ajah'] as $name) {
                Fellowship::create(['name' => $name, 'area' => $name, 'meets' => 'Thursdays, 6:30 PM']);
            }
        }

        return Fellowship::orderBy('id')->get();
    }

    /**
     * @param  array<string, mixed>  $p
     * @param  Collection<string, Unit>  $units
     * @param  Collection<int, Fellowship>  $fellowships
     */
    private function member(int $n, string $email, array $p, Collection $units, Collection $fellowships): Member
    {
        $user = User::create(['name' => $p['name'], 'email' => $email, 'password' => Hash::make(self::PASSWORD)]);
        $age = mt_rand(17, 36);
        // member1 has a birthday today, a few more this week and this month.
        $birthday = match (true) {
            $n === 1 => today()->subYears($age),
            $n <= 3 => today()->addDays($n)->subYears($age),
            $n <= 6 => today()->addDays(mt_rand(7, 28))->subYears($age),
            default => Carbon::create(today()->year - $age, mt_rand(1, 12), mt_rand(1, 28)),
        };
        $joined = today()->subDays($p['status'] === Member::PENDING ? mt_rand(0, 6) : mt_rand(60, 1500));
        $years = $joined->diffInYears(today());
        $member = new Member([
            'name' => $p['name'],
            'phone' => '080'.mt_rand(3, 9).' '.mt_rand(100, 999).' '.mt_rand(1000, 9999),
            'email' => $email,
            'gender' => $p['gender'],
            'birthday' => $birthday,
            'anniversary' => $p['married'] ?? false ? today()->subYears(mt_rand(1, 8))->subDays(mt_rand(-40, 300)) : null,
            'address' => mt_rand(1, 60).' '.['Adeola', 'Bode Thomas', 'Herbert Macaulay', 'Allen', 'Admiralty', 'Ogunlana'][mt_rand(0, 5)].' Street, '.self::AREAS[mt_rand(0, count(self::AREAS) - 1)],
            'unit_id' => isset($p['unit']) ? $units->get($p['unit'])?->id : null,
            'fellowship_id' => mt_rand(1, 10) <= 7 ? $fellowships->random()->id : null,
            'joined_on' => $joined,
            'baptised' => $years >= 1 || mt_rand(0, 1),
            'believers_class' => $years >= 1 || mt_rand(0, 2) === 0,
            'workers_training' => isset($p['unit']) && $years >= 1,
            'notify_sms' => (bool) mt_rand(0, 1),
        ]);
        $member->forceFill(['user_id' => $user->id, 'status' => $p['status']])->save();

        return $member;
    }

    /**
     * A head and an assistant for each unit that doesn't have them yet.
     *
     * @param  Collection<string, Unit>  $units
     */
    private function leaders(Collection $units): void
    {
        foreach ($units as $unit) {
            $team = Member::where('unit_id', $unit->id)->where('status', Member::ACTIVE)->orderBy('joined_on')->get();
            $unit->forceFill([
                'hod_member_id' => $unit->hod_member_id ?? $team->get(0)?->id,
                'assistant_member_id' => $unit->assistant_member_id ?? $team->get(1)?->id,
            ])->save();
        }
    }

    /**
     * The last eight Sundays and four Wednesdays, made if they aren't there.
     *
     * @return Collection<int, Service>
     */
    private function services(): Collection
    {
        $services = collect();
        $sunday = today()->isSunday() && now()->hour < 12 ? today()->subWeek() : today()->previous(Carbon::SUNDAY);
        for ($i = 0; $i < 8; $i++) {
            $day = $sunday->copy()->subWeeks($i);
            $services->push(Service::firstOrCreate(['held_on' => $day, 'kind' => 'sunday'], [
                'starts_at' => $day->copy()->setTime(9, 0), 'ends_at' => $day->copy()->setTime(11, 0),
            ]));
        }
        $wednesday = today()->isWednesday() ? today()->subWeek() : today()->previous(Carbon::WEDNESDAY);
        for ($i = 0; $i < 4; $i++) {
            $day = $wednesday->copy()->subWeeks($i);
            $services->push(Service::firstOrCreate(['held_on' => $day, 'kind' => 'midweek'], [
                'title' => 'Midweek Bible study', 'starts_at' => $day->copy()->setTime(18, 0), 'ends_at' => $day->copy()->setTime(19, 30),
            ]));
        }

        return $services->sortBy('held_on')->values();
    }

    /**
     * Who came to what. Most members come often; a few haven't been seen for three weeks, so the
     * care list has people to follow up.
     *
     * @param  Collection<int, Member>  $members
     * @param  Collection<int, Service>  $services
     */
    private function attendance(Collection $members, Collection $services): void
    {
        $recentCutoff = today()->subWeeks(3);
        foreach ($members as $i => $member) {
            $faithful = mt_rand(55, 95);
            $missing = $i % 9 === 4; // not seen lately
            foreach ($services as $service) {
                if ($member->joined_on && $service->held_on->lt($member->joined_on)) {
                    continue;
                }
                $chance = $service->kind === 'midweek' ? $faithful - 30 : $faithful;
                if (($missing && $service->held_on->gte($recentCutoff)) || mt_rand(1, 100) > $chance) {
                    continue;
                }
                $mode = mt_rand(1, 100) <= 20 ? 'online' : 'in_person';
                $member->services()->syncWithoutDetaching([$service->id => [
                    'mode' => $mode, 'checked_in_at' => $service->starts_at?->copy()->addMinutes(mt_rand(-15, 40)) ?? $service->held_on,
                ]]);
            }
        }
        // Each member's last time at church, as check-in records it.
        foreach ($members as $member) {
            $last = $member->services()->max('held_on');
            if ($last) {
                $member->forceFill(['last_attended_on' => Carbon::parse($last)])->save();
            }
        }

        // Head counts include visitors and children, not only members who checked in.
        foreach ($services as $service) {
            $inPerson = $service->members()->wherePivot('mode', 'in_person')->count();
            $online = $service->members()->wherePivot('mode', 'online')->count();
            $service->forceFill([
                'in_person' => max($service->in_person, $inPerson + mt_rand(25, 70)),
                'online' => max($service->online, $online + mt_rand(15, 60)),
            ])->save();
        }
    }

    /**
     * Tokens, the way members would have earned them: a welcome gift and attendance rewards,
     * paid from a demo treasury.
     *
     * @param  Collection<int, Member>  $members
     */
    private function tokens(Collection $members): void
    {
        $ledger = app(Ledger::class);
        $rewards = app(Rewards::class);
        if ($ledger->treasury()->fresh()->balance < 50_000) {
            $ledger->move(null, $ledger->treasury(), 100_000, 'mint', 'Demo tokens for the demo members', 'demo:members:mint:'.now()->timestamp, null, 'amount');
        }
        foreach ($members->where('status', Member::ACTIVE) as $member) {
            $member->load('user');
            $rewards->welcome($member->user);
            foreach ($member->services()->withPivot('mode')->orderBy('held_on')->get() as $service) {
                $rewards->attended($member, $service, $service->pivot->mode);
            }
        }
    }

    /**
     * A little of everything else members do: first-timers, prayer, testimonies, feedback, ideas.
     *
     * @param  Collection<int, Member>  $members
     * @param  Collection<int, Service>  $services
     */
    private function activity(Collection $members, Collection $services): void
    {
        $lastSunday = $services->where('kind', 'sunday')->sortByDesc('held_on')->first();

        foreach ([['Chiamaka Eze', 'Ada Okafor', true], ['Seun Afolabi', null, false], ['Joy Nnamdi', 'Tunde Bakare', true], ['Kelvin Musa', null, true], ['Rita Okoro', 'Grace Nwosu', false]] as $i => [$name, $invitedBy, $join]) {
            FirstTimer::firstOrCreate(['name' => $name, 'visited_on' => $lastSunday->held_on->copy()->subWeeks($i % 2)], [
                'phone' => '0809 '.mt_rand(100, 999).' '.mt_rand(1000, 9999), 'invited_by' => $invitedBy, 'wants_to_join' => $join,
                'notes' => $join ? 'Would like to join a unit.' : null,
            ]);
        }

        $prayers = [
            ['health', 'Please pray for my mum, she has surgery on Friday. Pray for the doctors and a quick recovery.', 'church', false],
            ['work', 'I have a job interview on Thursday with a bank. Pray for favour and a calm mind.', 'church', false],
            ['finance', 'Trusting God for my final-year school fees. The deadline is in two weeks.', 'church', true],
            ['family', 'Pray for peace in my home, and for my younger brother to come back to church.', 'team', false],
            ['guidance', 'I have two offers and need wisdom on which one to take.', 'pastors', false],
            ['thanks', 'Thanking God for traveling mercies to and from Abuja!', 'church', false],
        ];
        foreach ($prayers as $i => [$category, $text, $audience, $anonymous]) {
            $member = $members->get($i * 3 % max(1, $members->count()));
            $prayer = new PrayerRequest([
                'name' => $member->name, 'contact' => $member->phone, 'request' => $text, 'category' => $category,
                'is_private' => $audience === 'pastors', 'on_wall' => $audience === 'church', 'anonymous' => $anonymous,
            ]);
            $prayer->forceFill(['member_id' => $member->id, 'prayed_count' => $audience === 'church' ? mt_rand(2, 18) : 0, 'created_at' => now()->subDays(mt_rand(0, 12))])->save();
        }

        $testimonies = [
            ['God provided my school fees', 'provision', "At the start of the semester I had no way to pay my fees and was about to defer. I brought it to the prayer meeting and the Tribe stood with me.\n\nTwo weeks later a scholarship I had forgotten I applied for came through, covering the whole year. God is faithful!", 'published'],
            ['Healed after three months of pain', 'healing', "For three months I had back pain that no drug could stop. The pastors prayed with me after service, and I kept declaring that by His stripes I am healed.\n\nA week later the pain was gone, and it hasn't come back. Glory to God!", 'published'],
            ['A new job after a long wait', 'career', 'After eighteen months of applications, I got a job offer the same week I joined the workers in training. God\'s timing is perfect.', 'pending'],
        ];
        foreach ($testimonies as $i => [$title, $category, $body, $status]) {
            $member = $members->get($i * 5 + 1) ?? $members->first();
            $t = new Testimony(['title' => $title, 'body' => $body, 'category' => $category, 'name_display' => $i === 1 ? 'first' : 'full', 'share_publicly' => true, 'share_in_church' => true]);
            $t->forceFill(['member_id' => $member->id, 'status' => $status, 'published_at' => $status === 'published' ? now()->subDays($i * 4 + 1) : null, 'praises' => $status === 'published' ? mt_rand(5, 30) : 0])->save();
        }

        // Feedback on last Sunday from some of the members who were there.
        $aspects = array_keys(ServiceFeedback::ASPECTS);
        foreach ($lastSunday->members()->take(10)->get() as $i => $member) {
            $rating = [5, 5, 4, 5, 4, 3, 5, 4, 2, 5][$i] ?? 4;
            $feedback = new ServiceFeedback([
                'rating' => $rating,
                'went_well' => array_values(array_intersect($aspects, $rating >= 4 ? ['worship', 'word', 'welcome'] : ['worship'])),
                'could_improve' => $rating <= 3 ? ['sound', 'timing'] : ($i % 3 === 0 ? ['venue'] : []),
                'comment' => [0 => 'The word on purpose was exactly what I needed this week.', 5 => 'We started 25 minutes late and the sound at the back was low.', 8 => 'Could not hear well from the gallery.'][$i] ?? null,
                'anonymous' => $i === 5,
            ]);
            $feedback->forceFill(['service_id' => $lastSunday->id, 'member_id' => $member->id])->save();
        }

        foreach ([
            ['services', 'A class for new believers', 'Many of us joined this year and would love a short foundations class after service.', 'planned', 'Thank you! A six-week foundations class starts in January.'],
            ['facilities', 'More fans at the back', 'It gets very hot at the back during the second half of service.', 'new', null],
            ['youth', 'A monthly hangout for students', 'Something social for campus members, like games night or a picnic.', 'reviewing', null],
        ] as $i => [$category, $subject, $body, $status, $response]) {
            $member = $members->get($i * 4 + 2) ?? $members->first();
            $s = new Suggestion(['category' => $category, 'subject' => $subject, 'body' => $body, 'anonymous' => $i === 1]);
            $s->forceFill(['member_id' => $member->id, 'status' => $status, 'response' => $response, 'responded_at' => $response ? now()->subDay() : null])->save();
        }
    }

    /**
     * @return list<array{name: string, gender: string, status: string, unit?: string, married?: bool}>
     */
    private function people(): array
    {
        $a = Member::ACTIVE;

        return [
            ['name' => 'Tolu Adebayo', 'gender' => 'female', 'status' => $a, 'unit' => 'Choir'],
            ['name' => 'Emeka Okonkwo', 'gender' => 'male', 'status' => $a, 'unit' => 'Media & tech'],
            ['name' => 'Ngozi Eze', 'gender' => 'female', 'status' => $a, 'unit' => 'Welfare', 'married' => true],
            ['name' => 'Segun Alabi', 'gender' => 'male', 'status' => $a, 'unit' => 'Ushering'],
            ['name' => 'Funmi Ogunleye', 'gender' => 'female', 'status' => $a, 'unit' => 'Prayer', 'married' => true],
            ['name' => 'Chidi Nwankwo', 'gender' => 'male', 'status' => $a, 'unit' => 'Drama'],
            ['name' => 'Aisha Bello', 'gender' => 'female', 'status' => $a, 'unit' => 'Choir'],
            ['name' => 'Daniel Okoro', 'gender' => 'male', 'status' => $a, 'unit' => 'Media & tech'],
            ['name' => 'Blessing Udo', 'gender' => 'female', 'status' => $a, 'unit' => 'Ushering'],
            ['name' => 'Ifeanyi Obi', 'gender' => 'male', 'status' => $a, 'unit' => 'Welfare'],
            ['name' => 'Kemi Adeyemi', 'gender' => 'female', 'status' => $a, 'unit' => 'Choir', 'married' => true],
            ['name' => 'Tobi Fashola', 'gender' => 'male', 'status' => $a, 'unit' => 'Media & tech'],
            ['name' => 'Zainab Yusuf', 'gender' => 'female', 'status' => $a, 'unit' => 'Prayer'],
            ['name' => 'Victor Ekwueme', 'gender' => 'male', 'status' => $a, 'unit' => 'Ushering'],
            ['name' => 'Esther Okafor', 'gender' => 'female', 'status' => $a, 'unit' => 'Drama'],
            ['name' => 'Samuel Ojo', 'gender' => 'male', 'status' => $a, 'unit' => 'Choir', 'married' => true],
            ['name' => 'Precious Akande', 'gender' => 'female', 'status' => $a, 'unit' => 'Media & tech'],
            ['name' => 'Michael Ibe', 'gender' => 'male', 'status' => $a],
            ['name' => 'Deborah Olawale', 'gender' => 'female', 'status' => $a, 'unit' => 'Welfare'],
            ['name' => 'Joshua Amadi', 'gender' => 'male', 'status' => $a, 'unit' => 'Ushering'],
            ['name' => 'Ruth Danjuma', 'gender' => 'female', 'status' => $a],
            ['name' => 'Peter Oyelaran', 'gender' => 'male', 'status' => $a, 'unit' => 'Prayer', 'married' => true],
            ['name' => 'Favour Chukwu', 'gender' => 'female', 'status' => $a, 'unit' => 'Choir'],
            ['name' => 'David Afolabi', 'gender' => 'male', 'status' => $a],
            ['name' => 'Mercy Johnson', 'gender' => 'female', 'status' => $a, 'unit' => 'Drama'],
            ['name' => 'Gbenga Salami', 'gender' => 'male', 'status' => $a, 'unit' => 'Media & tech'],
            ['name' => 'Hannah Etim', 'gender' => 'female', 'status' => $a],
            ['name' => 'Uche Nnaji', 'gender' => 'male', 'status' => $a, 'unit' => 'Ushering', 'married' => true],
            ['name' => 'Joy Ikenna', 'gender' => 'female', 'status' => $a],
            ['name' => 'Femi Oladipo', 'gender' => 'male', 'status' => $a],
            // Joined online this week, waiting for approval in admin › Members.
            ['name' => 'Ada Nwachukwu', 'gender' => 'female', 'status' => Member::PENDING],
            ['name' => 'Ibrahim Lawal', 'gender' => 'male', 'status' => Member::PENDING],
            ['name' => 'Chioma Agu', 'gender' => 'female', 'status' => Member::PENDING],
            ['name' => 'Tunji Oseni', 'gender' => 'male', 'status' => Member::PENDING],
            // Moved away or stopped coming.
            ['name' => 'Bisi Coker', 'gender' => 'female', 'status' => Member::INACTIVE],
            ['name' => 'Kayode Ajayi', 'gender' => 'male', 'status' => Member::INACTIVE],
        ];
    }
}
