<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Member;
use App\Models\Payment;
use App\Models\TicketOrder;
use App\Models\TicketType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Demo events for trying events and ticketing on a local copy: a featured convention with paid
 * ticket tiers, a dinner, free events with limited places, a big free service, two past events
 * with door check-ins, and a draft. Bookings come from the demo members when they're there
 * (run MemberDemoSeeder first for that), otherwise from made-up names.
 *
 *     php artisan db:seed --class=EventDemoSeeder
 *
 * Dates are set from today, so the events stay current. Safe to run again: events already made
 * are left alone. Refuses to run on a live site.
 */
class EventDemoSeeder extends Seeder
{
    private const NAMES = ['Tolu Adebayo', 'Emeka Okonkwo', 'Ngozi Eze', 'Segun Alabi', 'Funmi Ogunleye', 'Chidi Nwankwo', 'Aisha Bello', 'Daniel Okoro',
        'Blessing Udo', 'Ifeanyi Obi', 'Kemi Adeyemi', 'Tobi Fashola', 'Zainab Yusuf', 'Victor Ekwueme', 'Esther Okafor', 'Samuel Ojo', 'Precious Akande',
        'Michael Ibe', 'Deborah Olawale', 'Joshua Amadi', 'Ruth Danjuma', 'Peter Oyelaran', 'Favour Chukwu', 'David Afolabi'];

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('The events demo is for local copies only, not a live site.');
        }
        mt_srand(1414);

        $buyers = $this->buyers();
        $made = 0;
        foreach ($this->events() as $e) {
            if (Event::where('slug', $e['slug'])->exists()) {
                continue;
            }
            $made++;
            $event = Event::create([
                'slug' => $e['slug'], 'title' => $e['title'], 'tag' => $e['tag'], 'summary' => $e['summary'], 'venue' => $e['venue'],
                'starts_at' => $e['starts'], 'ends_at' => $e['ends'] ?? $e['starts']->copy()->addHours(3),
                'is_featured' => $e['featured'] ?? false, 'is_published' => $e['published'] ?? true,
            ]);
            foreach ($e['types'] as $sort => $t) {
                $event->ticketTypes()->create([
                    'name' => $t['name'], 'price' => $t['price'] * 100, 'quantity' => $t['quantity'] ?? null,
                    'max_per_order' => $t['max'] ?? 10, 'sales_end_at' => $t['sales_end'] ?? null, 'sort' => $sort,
                ]);
            }
            $this->bookings($event->load('ticketTypes'), $e, $buyers);
        }

        mt_srand();
        $this->command?->info($made ? "Made {$made} demo events." : 'The demo events are already there.');
        $this->command?->line('See them at /events, and the bookings and check-ins in admin › Events & tickets.');
    }

    /**
     * Who books: the demo members if they exist, otherwise made-up names.
     *
     * @return Collection<int, array{name: string, email: string, phone: string}>
     */
    private function buyers(): Collection
    {
        $members = Member::where('status', Member::ACTIVE)->whereNotNull('email')->inRandomOrder()->take(40)->get();
        if ($members->count() >= 10) {
            return $members->map(fn (Member $m) => ['name' => $m->name, 'email' => $m->email, 'phone' => $m->phone ?? '0803 000 0000'])->values();
        }

        return collect(self::NAMES)->map(fn ($n) => ['name' => $n, 'email' => Str::slug($n, '.').'@example.com', 'phone' => '080'.mt_rand(3, 9).' '.mt_rand(100, 999).' '.mt_rand(1000, 9999)]);
    }

    /**
     * Confirmed bookings (paid ones with their Paystack payment), a couple that were never paid,
     * and door check-ins for events that have happened.
     *
     * @param  array<string, mixed>  $e
     * @param  Collection<int, array{name: string, email: string, phone: string}>  $buyers
     */
    private function bookings(Event $event, array $e, Collection $buyers): void
    {
        $past = $event->starts_at->isPast();
        foreach ($event->ticketTypes as $i => $type) {
            $target = $e['types'][$i]['sold'] ?? 0;
            $sold = 0;
            while ($sold < $target) {
                $buyer = $buyers[mt_rand(0, $buyers->count() - 1)];
                $qty = min(mt_rand(1, min(3, $type->max_per_order ?? 3)), $target - $sold);
                $order = $this->order($event, $type, $buyer, $qty, TicketOrder::CONFIRMED, $past);
                $sold += $qty;
                if ($type->price > 0) {
                    $this->payment($order);
                }
            }
        }

        // People who started booking a paid ticket and never paid: they don't hold places.
        $paid = $event->ticketTypes->firstWhere(fn (TicketType $t) => $t->price > 0);
        if ($paid && ! $past) {
            foreach (range(1, 2) as $n) {
                $this->order($event, $paid, $buyers[mt_rand(0, $buyers->count() - 1)], 1, TicketOrder::AWAITING_PAYMENT, false, now()->subHours($n * 5));
            }
        }
    }

    /**
     * @param  array{name: string, email: string, phone: string}  $buyer
     */
    private function order(Event $event, TicketType $type, array $buyer, int $qty, string $status, bool $past, ?Carbon $at = null): TicketOrder
    {
        $at ??= $past ? $event->starts_at->copy()->subDays(mt_rand(1, 20)) : now()->subDays(mt_rand(0, 14))->subMinutes(mt_rand(0, 600));
        $order = new TicketOrder(['code' => strtoupper(Str::random(8)), 'name' => $buyer['name'], 'email' => $buyer['email'], 'phone' => $buyer['phone'], 'total' => $type->price * $qty]);
        $order->event_id = $event->id;
        $order->forceFill(['status' => $status, 'confirmed_at' => $status === TicketOrder::CONFIRMED ? $at : null, 'created_at' => $at, 'updated_at' => $at])->save();
        if ($status === TicketOrder::CONFIRMED) {
            foreach (range(1, $qty) as $n) {
                $ticket = $order->tickets()->create(['ticket_type_id' => $type->id, 'code' => strtoupper(Str::random(10))]);
                // Most people who booked came; they were scanned in at the door.
                if ($past && mt_rand(1, 100) <= 82) {
                    $ticket->forceFill(['checked_in_at' => $event->starts_at->copy()->addMinutes(mt_rand(-30, 45))])->save();
                }
            }
        }

        return $order;
    }

    private function payment(TicketOrder $order): void
    {
        $payment = new Payment(['reference' => 'DEMO-'.strtoupper(Str::random(12)), 'email' => $order->email, 'amount' => $order->total, 'platform_fee' => 0, 'currency' => 'NGN']);
        $payment->payable()->associate($order);
        $payment->forceFill(['status' => Payment::SUCCESS, 'channel' => mt_rand(0, 2) ? 'card' : 'bank_transfer', 'paid_at' => $order->confirmed_at,
            'created_at' => $order->confirmed_at, 'updated_at' => $order->confirmed_at])->save();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function events(): array
    {
        $at = fn (int $days, string $time) => today()->addDays($days)->setTimeFromTimeString($time);

        return [
            [
                'slug' => 'demo-tribe-fest', 'title' => 'Tribe Fest: Rise & Reign', 'tag' => 'Convention', 'featured' => true,
                'starts' => $at(35, '15:00'), 'ends' => $at(35, '21:00'), 'venue' => 'Main sanctuary',
                'summary' => "Our biggest youth gathering of the year: worship, a guest speaker, honest panels on faith, money and relationships, food and an after-party.\nVIP includes front seats, dinner with the speakers and a Tribe Fest hoodie.",
                'types' => [
                    ['name' => 'Early bird', 'price' => 3_000, 'quantity' => 100, 'sold' => 100, 'sales_end' => now()->subWeek()],
                    ['name' => 'Regular', 'price' => 5_000, 'quantity' => 300, 'sold' => 64],
                    ['name' => 'VIP', 'price' => 15_000, 'quantity' => 40, 'sold' => 17, 'max' => 4],
                    ['name' => 'Group of 5', 'price' => 20_000, 'quantity' => 20, 'sold' => 6, 'max' => 2],
                ],
            ],
            [
                'slug' => 'demo-dinner-and-conversations', 'title' => 'Dinner & Conversations: Love, Dating and Marriage', 'tag' => 'Relationships',
                'starts' => $at(19, '18:30'), 'venue' => 'Hall B',
                'summary' => 'A three-course dinner and a candid conversation with married couples from the church. For singles and couples. Dress code: smart and elegant.',
                'types' => [
                    ['name' => 'Single seat', 'price' => 10_000, 'quantity' => 40, 'sold' => 31, 'max' => 2],
                    ['name' => 'Couple (2 seats)', 'price' => 18_000, 'quantity' => 15, 'sold' => 12, 'max' => 1],
                ],
            ],
            [
                'slug' => 'demo-business-masterclass', 'title' => 'Business & Career Masterclass', 'tag' => 'Career',
                'starts' => $at(12, '10:00'), 'ends' => $at(12, '15:00'), 'venue' => 'Hall B',
                'summary' => 'Practical sessions on starting a side business, pricing, social media marketing and landing your next job. Lunch included.',
                'types' => [
                    ['name' => 'Early bird', 'price' => 2_000, 'quantity' => 50, 'sold' => 50, 'sales_end' => now()->subDays(2)],
                    ['name' => 'Regular', 'price' => 3_500, 'quantity' => 100, 'sold' => 23],
                ],
            ],
            [
                'slug' => 'demo-campus-bible-study', 'title' => 'Campus Bible Study Kick-off', 'tag' => 'Campus',
                'starts' => $at(6, '17:00'), 'venue' => 'Campus fellowship hall',
                'summary' => 'Starting the semester in the Word: worship, a short study and small groups by faculty. Snacks on us. Free, but places are limited.',
                'types' => [['name' => 'Free place', 'price' => 0, 'quantity' => 120, 'sold' => 47, 'max' => 2]],
            ],
            [
                'slug' => 'demo-worship-night-deeper', 'title' => 'Worship Night: Deeper', 'tag' => 'Worship',
                'starts' => $at(9, '18:00'), 'venue' => 'Main sanctuary',
                'summary' => 'An evening of extended worship and prayer with the Tribe choir and band. Come hungry for God\'s presence.',
                'types' => [['name' => 'Free entry', 'price' => 0, 'quantity' => 300, 'sold' => 112, 'max' => 5]],
            ],
            [
                'slug' => 'demo-childrens-christmas-party', 'title' => 'Children\'s Christmas Party', 'tag' => 'Children',
                'starts' => $at(40, '12:00'), 'venue' => 'Children\'s church hall',
                'summary' => 'Games, face painting, a nativity play and gifts from Father Christmas for children aged 2 to 12. Book a place for each child.',
                'types' => [['name' => 'Child', 'price' => 0, 'quantity' => 80, 'sold' => 29, 'max' => 3]],
            ],
            [
                'slug' => 'demo-crossover-service', 'title' => 'Crossover Service', 'tag' => 'Service',
                'starts' => today()->setDate(today()->year, 12, 31)->setTime(22, 0)->max(now()->addDays(3)->setTime(22, 0)), 'venue' => 'Main sanctuary',
                'summary' => 'See out the old year and step into the new one in worship, prayer and thanksgiving. Everyone is welcome; no booking needed.',
                'types' => [['name' => 'Free entry', 'price' => 0]],
            ],
            [
                'slug' => 'demo-youth-sports-day', 'title' => 'Youth Sports Day', 'tag' => 'Sports',
                'starts' => $at(-20, '09:00'), 'ends' => $at(-20, '16:00'), 'venue' => 'Community sports centre',
                'summary' => 'Football, volleyball, relay races and tug-of-war between the units. The Media unit are the defending champions.',
                'types' => [['name' => 'Player or supporter', 'price' => 0, 'quantity' => 200, 'sold' => 86, 'max' => 4]],
            ],
            [
                'slug' => 'demo-couples-retreat', 'title' => 'Couples\' Retreat', 'tag' => 'Marriage',
                'starts' => $at(-41, '10:00'), 'ends' => $at(-41, '17:00'), 'venue' => 'Lakeside resort',
                'summary' => 'A day away for married couples: teaching, games, lunch and time to talk.',
                'types' => [['name' => 'Couple', 'price' => 25_000, 'quantity' => 30, 'sold' => 22, 'max' => 1]],
            ],
            [
                'slug' => 'demo-easter-drama', 'title' => 'Easter Drama: The Empty Tomb', 'tag' => 'Drama', 'published' => false,
                'starts' => $at(130, '17:00'), 'venue' => 'Main sanctuary',
                'summary' => 'The Drama unit\'s Easter production. Still being planned, so it isn\'t on the website yet.',
                'types' => [['name' => 'Free entry', 'price' => 0, 'quantity' => 400]],
            ],
        ];
    }
}
