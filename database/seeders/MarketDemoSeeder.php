<?php

namespace Database\Seeders;

use App\Models\Coupon;
use App\Models\Enquiry;
use App\Models\Member;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Models\VendorPlan;
use App\Models\VendorSubscription;
use GdImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Demo shops for trying the Tribe Market on a local copy: eight sellers (church members, so
 * tokens work) with products and services, pictures, subscriptions, enquiries, plan prices and a
 * coupon. One shop's subscription has lapsed and one is waiting for approval, to show those too.
 *
 *     php artisan db:seed --class=MarketDemoSeeder
 *
 * Safe to run again: shops already made are left alone. Refuses to run on a live site.
 */
class MarketDemoSeeder extends Seeder
{
    public const PASSWORD = 'demo-password';

    /** Pictures are drawn in the shop's colours. */
    private const COLOURS = [
        'Food' => ['#f97316', '#b45309'],
        'Fashion' => ['#7c3aed', '#3b0764'],
        'Beauty' => ['#ec4899', '#9d174d'],
        'Services' => ['#0ea5e9', '#0a1d3f'],
        'Books & media' => ['#16a34a', '#14532d'],
        'Crafts & gifts' => ['#e0aa1e', '#92400e'],
        'Other' => ['#64748b', '#1e293b'],
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('The market demo is for local copies only, not a live site.');
        }

        $this->plans();
        Coupon::firstOrCreate(['code' => 'DEMO20'], ['percent_off' => 20, 'max_uses' => 50, 'expires_at' => now()->addMonths(6), 'is_active' => true]);

        $made = 0;
        foreach ($this->shops() as $i => $s) {
            if (Shop::where('slug', $s['slug'])->exists()) {
                continue;
            }
            $made++;
            $owner = $this->owner($i + 1, $s['owner']);
            $shop = new Shop([
                'slug' => $s['slug'], 'name' => $s['name'], 'owner_name' => $s['owner'], 'email' => $owner->email,
                'phone' => $s['phone'], 'whatsapp' => $s['phone'], 'category' => $s['category'], 'description' => $s['about'],
            ]);
            $shop->forceFill([
                'user_id' => $owner->id,
                'status' => $s['status'] ?? Shop::APPROVED,
                'approved_at' => ($s['status'] ?? Shop::APPROVED) === Shop::APPROVED ? now()->subMonths(2) : null,
                'share_visits' => random_int(0, 40),
            ])->save();

            if (($s['status'] ?? Shop::APPROVED) === Shop::APPROVED) {
                $this->subscribe($shop, $s['lapsed'] ?? false);
            }

            foreach ($s['items'] as $item) {
                $product = new Product([
                    'type' => $item['type'] ?? 'product',
                    'slug' => $this->slug($item['name']),
                    'name' => $item['name'],
                    'description' => $item['about'],
                    'price' => $item['price'] * 100,
                    'price_unit' => $item['unit'] ?? null,
                    'stock' => ($item['type'] ?? 'product') === 'service' ? null : ($item['stock'] ?? null),
                    'is_active' => true,
                    'token_price' => $item['tokens'] ?? null,
                ]);
                $product->shop_id = $shop->id;
                $product->image_path = $this->picture($item['name'], $item['price'], $item['type'] ?? 'product', $s['category']);
                $product->share_visits = random_int(0, 25);
                $product->save();
            }

            foreach ($s['enquiries'] ?? [] as [$itemIndex, $name, $message, $read]) {
                $enquiry = new Enquiry([
                    'product_id' => $shop->products()->orderBy('id')->skip($itemIndex)->value('id'),
                    'name' => $name, 'phone' => '0803 '.random_int(100, 999).' '.random_int(1000, 9999),
                    'email' => Str::slug($name, '.').'@example.com', 'message' => $message,
                ]);
                $enquiry->shop_id = $shop->id;
                $enquiry->read_at = $read ? now()->subDay() : null;
                $enquiry->save();
            }
        }

        $this->command?->info($made ? "Made {$made} demo shops." : 'The demo shops are already there.');
        $this->command?->line('Seller logins: seller1@demo.test … seller'.count($this->shops()).'@demo.test, password '.self::PASSWORD.' (at /vendor/login).');
        $this->command?->line('Coupon for subscriptions: DEMO20 (20% off).');
    }

    /**
     * Prices for the subscription plans, unless an admin has already set them.
     */
    private function plans(): void
    {
        $prices = ['monthly' => 5_000, 'quarterly' => 13_500, 'half_yearly' => 25_000, 'yearly' => 45_000, 'three_years' => 120_000];
        foreach (VendorPlan::catalogue() as $plan) {
            if ((int) $plan->price === 0 && isset($prices[$plan->key])) {
                $plan->forceFill(['price' => $prices[$plan->key] * 100, 'is_active' => true])->save();
            }
        }
    }

    /**
     * A seller's sign-in, as an approved church member (so their listings can take tokens).
     */
    private function owner(int $n, string $name): User
    {
        $user = User::firstOrCreate(['email' => "seller{$n}@demo.test"], ['name' => $name, 'password' => Hash::make(self::PASSWORD)]);
        if (! $user->member) {
            $member = new Member(['name' => $name, 'phone' => '0803 000 '.str_pad((string) $n, 4, '0', STR_PAD_LEFT), 'email' => $user->email, 'joined_on' => now()->subYear()]);
            $member->forceFill(['user_id' => $user->id, 'status' => Member::ACTIVE])->save();
        }

        return $user;
    }

    private function subscribe(Shop $shop, bool $lapsed): void
    {
        $plan = VendorPlan::where('key', $lapsed ? 'monthly' : (random_int(0, 1) ? 'monthly' : 'quarterly'))->firstOrFail();
        $subscription = new VendorSubscription(['vendor_plan_id' => $plan->id, 'months' => $plan->months, 'list_price' => $plan->price, 'discount' => 0, 'amount' => $plan->price]);
        $subscription->shop_id = $shop->id;
        $subscription->forceFill([
            'status' => VendorSubscription::ACTIVE,
            'starts_at' => $lapsed ? now()->subMonths(2) : now()->subDays(5),
            'ends_at' => $lapsed ? now()->subMonth() : now()->subDays(5)->addMonths($plan->months),
        ])->save();
    }

    private function slug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        for ($n = 2; Product::where('slug', $slug)->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }

    /**
     * A simple picture for the listing: the shop's colours, the name and the price. Real sellers
     * upload photos; these just make the demo look like a market.
     */
    private function picture(string $name, int $price, string $type, string $category): ?string
    {
        if (! function_exists('imagettftext')) {
            return null; // GD without FreeType: the listing shows without a picture
        }
        [$from, $to] = self::COLOURS[$category] ?? self::COLOURS['Other'];
        $w = 1200;
        $h = 900;
        $img = imagecreatetruecolor($w, $h);
        $a = sscanf(ltrim($from, '#'), '%02x%02x%02x');
        $b = sscanf(ltrim($to, '#'), '%02x%02x%02x');
        for ($y = 0; $y < $h; $y++) {
            $t = $y / $h;
            imageline($img, 0, $y, $w, $y, imagecolorallocate($img, ...array_map(fn ($p, $q) => (int) ($p + ($q - $p) * $t), $a, $b)));
        }
        // Soft circles for texture.
        mt_srand(crc32($name));
        for ($i = 0; $i < 7; $i++) {
            $d = mt_rand(160, 520);
            imagefilledellipse($img, mt_rand(0, $w), mt_rand(0, $h), $d, $d, imagecolorallocatealpha($img, 255, 255, 255, mt_rand(105, 118)));
        }
        mt_srand();

        $white = imagecolorallocate($img, 255, 255, 255);
        $bold = resource_path('fonts/Poppins-Black.ttf');
        $price_font = resource_path('fonts/Anton-Regular.ttf');
        $label = resource_path('fonts/Poppins-SemiBold.ttf');
        $this->centred($img, mb_strtoupper($type === 'service' ? 'Service' : $category), $label, 30, 250, imagecolorallocatealpha($img, 255, 255, 255, 30));
        $lines = $this->wrap($name, $bold, 76, 1040);
        $y = 420 - (count($lines) - 1) * 50;
        foreach (array_slice($lines, 0, 3) as $line) {
            $this->centred($img, $line, $bold, 76, $y, $white);
            $y += 100;
        }
        $this->centred($img, '₦'.number_format($price), $price_font, 72, $y + 70, imagecolorallocate($img, 255, 241, 199));
        $this->centred($img, 'DEMO LISTING', $label, 22, 850, imagecolorallocatealpha($img, 255, 255, 255, 60));

        ob_start();
        imagejpeg($img, null, 85);
        $path = 'products/demo-'.Str::slug($name).'-'.Str::random(6).'.jpg';
        Storage::disk('public')->put($path, (string) ob_get_clean());

        return $path;
    }

    private function centred(GdImage $img, string $text, string $font, int $size, int $y, int $colour): void
    {
        $box = imagettfbbox($size, 0, $font, $text);
        imagettftext($img, $size, 0, (int) ((imagesx($img) - ($box[2] - $box[0])) / 2 - $box[0]), $y, $colour, $font, $text);
    }

    /**
     * @return list<string>
     */
    private function wrap(string $text, string $font, int $size, int $width): array
    {
        $lines = [];
        $line = '';
        foreach (preg_split('/\s+/', $text) as $word) {
            $try = trim($line.' '.$word);
            $box = imagettfbbox($size, 0, $font, $try);
            if ($width < $box[2] - $box[0] && $line !== '') {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $try;
            }
        }

        return [...$lines, $line];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function shops(): array
    {
        return [
            [
                'slug' => 'demo-adas-kitchen', 'name' => 'Ada\'s Kitchen', 'owner' => 'Ada Okafor', 'phone' => '0803 111 2001', 'category' => 'Food',
                'about' => "Home-cooked Nigerian food for parties, birthdays and church events. Jollof, small chops, zobo and more, made fresh to order.\nDelivery around the city, or pick up after service.",
                'items' => [
                    ['name' => 'Party jollof rice (cooler)', 'price' => 45_000, 'about' => 'Smoky party jollof with fried plantain. Feeds about 25 people. Order 3 days ahead.', 'stock' => 10],
                    ['name' => 'Small chops tray', 'price' => 18_000, 'about' => 'Puff-puff, samosa, spring rolls, peppered gizzard and chicken wings for 15 to 20 guests.', 'stock' => 12, 'tokens' => 9_000],
                    ['name' => 'Zobo drink, 6 bottles', 'price' => 4_500, 'about' => 'Chilled hibiscus drink with ginger and pineapple, no artificial colour. 50cl bottles.', 'stock' => 40, 'tokens' => 2_250],
                    ['name' => 'Meat pies (dozen)', 'price' => 6_000, 'about' => 'Buttery, flaky meat pies filled with minced beef, potato and carrot.', 'stock' => 30],
                ],
                'enquiries' => [[0, 'Bola Adeyemi', 'Can you do two coolers for my wedding on the 21st? Pick up from church.', false], [1, 'Femi Johnson', 'Do you deliver to Yaba?', true]],
            ],
            [
                'slug' => 'demo-royal-threads', 'name' => 'Royal Threads', 'owner' => 'Tunde Bakare', 'phone' => '0803 111 2002', 'category' => 'Fashion',
                'about' => 'Ankara, adire and modern native wear for men and women. Ready-to-wear and made to measure. Ask about matching outfits for families and couples.',
                'items' => [
                    ['name' => 'Ankara shirt (men)', 'price' => 15_000, 'about' => 'Short-sleeve Ankara shirt in bold prints. Sizes S to XXL.', 'stock' => 20],
                    ['name' => 'Adire maxi dress', 'price' => 28_000, 'about' => 'Flowing hand-dyed adire dress, lined, with pockets. Made in Abeokuta.', 'stock' => 8],
                    ['name' => 'Tribe hoodie (navy and gold)', 'price' => 22_000, 'about' => 'Heavyweight navy hoodie with the gold crown. Unisex, sizes S to XXL.', 'stock' => 35, 'tokens' => 11_000],
                    ['name' => 'Agbada tailoring', 'type' => 'service', 'price' => 60_000, 'unit' => 'per outfit', 'about' => 'Three-piece agbada sewn to your measurements. Bring your fabric, ready in 2 weeks.'],
                ],
                'enquiries' => [[3, 'Emeka Obi', 'I need an agbada for my dad\'s 60th next month. Can we meet after service on Sunday?', false]],
            ],
            [
                'slug' => 'demo-glow-by-temi', 'name' => 'Glow by Temi', 'owner' => 'Temi Alade', 'phone' => '0803 111 2003', 'category' => 'Beauty',
                'about' => 'Natural skincare made with shea butter and black soap, plus makeup and gele for weddings, birthdays and photo shoots.',
                'items' => [
                    ['name' => 'Whipped shea butter (250g)', 'price' => 5_500, 'about' => 'Raw shea whipped with coconut oil and vitamin E. For dry skin and hair.', 'stock' => 50, 'tokens' => 2_750],
                    ['name' => 'Black soap bundle', 'price' => 4_000, 'about' => 'Three bars of African black soap with honey and oats.', 'stock' => 40],
                    ['name' => 'Bridal makeup', 'type' => 'service', 'price' => 70_000, 'unit' => 'per session', 'about' => 'Trial, wedding-day makeup and lashes. I come to you.'],
                    ['name' => 'Gele tying', 'type' => 'service', 'price' => 8_000, 'unit' => 'per gele', 'about' => 'Classic and modern gele styles for parties and church events.'],
                ],
            ],
            [
                'slug' => 'demo-pixelpraise-studio', 'name' => 'PixelPraise Studio', 'owner' => 'David Eze', 'phone' => '0803 111 2004', 'category' => 'Services',
                'about' => 'Photography and graphic design by a member of the media unit. Events, portraits, flyers and logos for churches and small businesses.',
                'items' => [
                    ['name' => 'Event photography', 'type' => 'service', 'price' => 35_000, 'unit' => 'per hour', 'about' => 'Edited photos within a week, shared in an online gallery. Weddings, birthdays, dedications.'],
                    ['name' => 'Logo design', 'type' => 'service', 'price' => 50_000, 'unit' => 'per logo', 'about' => 'Three ideas, two rounds of changes, and every file you need.'],
                    ['name' => 'Birthday or event flyer', 'type' => 'service', 'price' => 10_000, 'unit' => 'per design', 'about' => 'A bold flyer sized for WhatsApp, Instagram and print. Ready in 2 days.', 'tokens' => 5_000],
                    ['name' => 'Passport photos (8 copies)', 'type' => 'service', 'price' => 3_000, 'unit' => 'per sitting', 'about' => 'Visa and ID passport photos, printed while you wait.'],
                ],
                'enquiries' => [[2, 'Choir unit', 'We need a flyer for our Christmas concert. Can you have it by Friday?', false]],
            ],
            [
                'slug' => 'demo-campus-tutors', 'name' => 'Campus Tutors', 'owner' => 'Grace Nwosu', 'phone' => '0803 111 2005', 'category' => 'Services',
                'about' => 'Graduate tutors for secondary school and university students. Maths, sciences, English, JAMB and WAEC, in person or online.',
                'items' => [
                    ['name' => 'Maths and physics tutoring', 'type' => 'service', 'price' => 6_000, 'unit' => 'per hour', 'about' => 'One-to-one lessons, at home or on Zoom.', 'tokens' => 3_000],
                    ['name' => 'JAMB prep class', 'type' => 'service', 'price' => 30_000, 'unit' => 'per month', 'about' => 'Three evenings a week with mock exams and past questions.'],
                    ['name' => 'CV and cover letter review', 'type' => 'service', 'price' => 7_500, 'unit' => 'per review', 'about' => 'A rewritten CV and cover letter for your next application, within 3 days.'],
                ],
            ],
            [
                'slug' => 'demo-faith-bookshelf', 'name' => 'Faith Bookshelf', 'owner' => 'Samuel Adebayo', 'phone' => '0803 111 2006', 'category' => 'Books & media',
                'about' => 'Bibles, devotionals and Christian books for all ages. Ask about gifts for dedications, birthdays and new believers.',
                'items' => [
                    ['name' => 'Study Bible (NKJV)', 'price' => 18_500, 'about' => 'Leather-bound study Bible with notes, maps and a concordance.', 'stock' => 15, 'tokens' => 9_250],
                    ['name' => '365-day devotional journal', 'price' => 7_000, 'about' => 'A verse, a short reflection and space to write for every day of the year.', 'stock' => 25, 'tokens' => 3_500],
                    ['name' => 'Bible stories for children', 'price' => 6_500, 'about' => 'Illustrated hardback with 60 stories, for ages 3 to 9.', 'stock' => 20],
                ],
            ],
            [
                'slug' => 'demo-crown-crafts', 'name' => 'Crown Crafts', 'owner' => 'Funke Bello', 'phone' => '0803 111 2007', 'category' => 'Crafts & gifts',
                'about' => 'Handmade gifts and hampers for birthdays, anniversaries and thank-yous. Personalised with names and scriptures.',
                'items' => [
                    ['name' => 'Birthday gift hamper', 'price' => 25_000, 'about' => 'Snacks, a scented candle, a mug and a handwritten card, wrapped and delivered.', 'stock' => 10],
                    ['name' => 'Scripture wall frame', 'price' => 12_000, 'about' => 'A framed verse of your choice, A3 size, in gold or black.', 'stock' => 12, 'tokens' => 6_000],
                    ['name' => 'Beaded bracelet set', 'price' => 3_500, 'about' => 'Three handmade bracelets in royal blue and gold.', 'stock' => 30, 'tokens' => 1_750],
                ],
                'lapsed' => true, // its subscription ran out, so it's hidden from the market
            ],
            [
                'slug' => 'demo-fixit-tunde', 'name' => 'FixIt Tech', 'owner' => 'Kunle Adewale', 'phone' => '0803 111 2008', 'category' => 'Other',
                'about' => 'Phone and laptop repairs with a 30-day warranty. Same-day screen replacement for most phones.',
                'items' => [
                    ['name' => 'Phone screen replacement', 'type' => 'service', 'price' => 25_000, 'unit' => 'per phone', 'about' => 'Original-quality screens for most Samsung, Tecno, Infinix and iPhone models.'],
                    ['name' => 'Laptop servicing', 'type' => 'service', 'price' => 15_000, 'unit' => 'per laptop', 'about' => 'Cleaning, a fresh install, updates and a health check.'],
                ],
                'status' => Shop::PENDING, // an application waiting for an admin to approve it
            ],
        ];
    }
}
