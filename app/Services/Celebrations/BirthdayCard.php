<?php

namespace App\Services\Celebrations;

use App\Models\BirthdayDesign;
use App\Models\Member;
use GdImage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Draws members' birthday cards (1080 × 1350, the size for Instagram, Facebook and WhatsApp
 * status). There are built-in designs, and designs the media unit uploads (a finished picture,
 * with the photo and name placed where they choose). Each card has the member's photo or
 * initials, their name and the day, never the year. Drawn at double size and scaled down so
 * every edge is smooth.
 */
class BirthdayCard
{
    public const WIDTH = 1080;

    public const HEIGHT = 1350;

    private const S = 2;

    public const DESIGNS = [
        'royal' => 'Royal crown: purple, gold and sparkle',
        'neon' => 'Neon party: glowing colours on black',
        'balloons' => 'Balloons: bright and playful',
        'gold' => 'Black and gold: elegant',
        'pastel' => 'Pastel pop: soft colours and squiggles',
        'sunset' => 'Sunset vibes: bold orange to purple',
    ];

    /** Designs from before, and what they became. */
    private const RENAMED = ['sunrise' => 'sunset', 'ivory' => 'pastel'];

    /** Fonts the uploaded designs can use for the name. */
    public const NAME_FONTS = [
        'poppins' => ['Poppins-Black.ttf', 'Bold and modern'],
        'anton' => ['Anton-Regular.ttf', 'Tall and punchy'],
        'royal' => ['CinzelDecorative-Bold.ttf', 'Royal capitals'],
        'script' => ['GreatVibes-Regular.ttf', 'Elegant script'],
        'fun' => ['Pacifico-Regular.ttf', 'Fun brush script'],
    ];

    /**
     * A member's card for this year's birthday, in their design (or the one asked for).
     */
    public function forMember(Member $member, ?string $design = null): string
    {
        $design ??= $this->designFor($member);
        $template = $this->template();
        $key = 'birthday-card:'.md5(json_encode([$member->id, $member->name, $member->photo_path, $member->birthday?->format('m-d'), $design, $template, BirthdayDesign::stamp()]));

        return Cache::remember($key, now()->addDay(), fn () => $this->render($member->name, $this->dayFor($member), $member->photo_path, $design, $member->id));
    }

    /**
     * The card as PNG bytes. $design is a built-in key or "upload-{id}".
     *
     * @param  array<string, mixed>|null  $layout  for an uploaded design: placement to try instead of the saved one
     */
    public function render(string $name, Carbon $day, ?string $photoPath = null, ?string $design = null, int $seed = 1, ?array $layout = null): string
    {
        $design = $this->normalise($design ?? $this->enabled()[0]);
        $img = imagecreatetruecolor(self::WIDTH * self::S, self::HEIGHT * self::S);
        imagealphablending($img, true);
        $photo = $photoPath ? $this->loadPhoto($photoPath) : null;

        if (str_starts_with($design, 'upload-') && ($upload = BirthdayDesign::find((int) substr($design, 7)))) {
            $this->uploaded($img, $upload, $layout ?? $upload->layout(), $name, $day, $photo);
        } else {
            mt_srand($seed);
            $this->{$design}($img, $name, $day, $photo, $this->template());
            mt_srand();
        }

        $out = imagescale($img, self::WIDTH, self::HEIGHT, IMG_BICUBIC);
        if (! $out instanceof GdImage) {
            throw new RuntimeException('The card could not be drawn.');
        }
        ob_start();
        imagepng($out, null, 6);

        return (string) ob_get_clean();
    }

    /**
     * The words the media unit set for the built-in designs.
     *
     * @return array{headline: string, message: string, verse: string}
     */
    public function template(): array
    {
        $c = config('church.birthday');

        return [
            'headline' => filled($c['headline'] ?? null) ? $c['headline'] : 'Happy Birthday',
            'message' => filled($c['message'] ?? null) ? $c['message'] : 'Celebrating you today!',
            'verse' => (string) ($c['verse'] ?? ''),
        ];
    }

    /**
     * The designs in use: built-in keys and "upload-{id}", in order.
     *
     * @return list<string>
     */
    public function enabled(): array
    {
        $uploads = BirthdayDesign::pluck('id')->map(fn ($id) => 'upload-'.$id)->all();
        $chosen = collect(explode(',', (string) config('church.birthday.design')))
            ->map(fn ($d) => $this->normalise(trim($d)))
            ->filter(fn ($d) => isset(self::DESIGNS[$d]) || in_array($d, $uploads, true))
            ->unique()->values()->all();

        return $chosen ?: ['royal'];
    }

    /**
     * Everything that can be chosen: key => label.
     *
     * @return array<string, string>
     */
    public function all(): array
    {
        return self::DESIGNS + BirthdayDesign::orderBy('id')->get()->mapWithKeys(fn (BirthdayDesign $d) => ['upload-'.$d->id => $d->name])->all();
    }

    /**
     * With several designs in use, each member gets one of them, a different one most years.
     */
    public function designFor(Member $member): string
    {
        $designs = $this->enabled();

        return $designs[crc32($member->id.':'.today()->year) % count($designs)];
    }

    public function dayFor(Member $member): Carbon
    {
        if ($member->birthday->format('m-d') === '02-29' && ! today()->isLeapYear()) {
            return Carbon::create(today()->year, 2, 28);
        }

        return $member->birthday->copy()->year(today()->year);
    }

    /**
     * Whether a scattered decoration would land on the photo or the words.
     *
     * @param  array{0: int, 1: int, 2: int}|null  $photo
     */
    private function inTheWay(int $x, int $y, ?array $photo): bool
    {
        if ($photo && hypot($x - $photo[0], $y - $photo[1]) < $photo[2]) {
            return true;
        }

        return $photo !== null && $y > 690 && $y < 1325 && $x > 30 && $x < 1050;
    }

    private function normalise(string $design): string
    {
        return self::RENAMED[$design] ?? $design;
    }

    // ─── The built-in designs ────────────────────────────────────────────────

    private function royal(GdImage $img, string $name, Carbon $day, ?GdImage $photo, array $t): void
    {
        $this->gradient($img, ['#3a0d63', '#1d0b45', '#0a1d3f']);
        $this->glow($img, 540, 450, 520, '#f5c542', 112);
        $this->rays($img, 540, 450, '#f5c542', 122);
        for ($i = 0; $i < 46; $i++) {
            $this->sparkle($img, mt_rand(30, 1050), mt_rand(30, 1320), mt_rand(5, 16), mt_rand(0, 2) ? '#f5c542' : '#ffffff', mt_rand(20, 70), [540, 450, 260]);
        }
        $this->logo($img, 540, 84, 40);
        $this->portrait($img, $photo, $name, 540, 470, 200, ['#f5c542', '#fff3c4'], 'circle', '#3a0d63');
        $this->crown($img, 540, 262, 190, '#f5c542');
        $this->text($img, $t['headline'], 'GreatVibes-Regular.ttf', 112, 812, '#f5c542', shadow: ['#120326', 4, 4, 30]);
        $this->name($img, $name, 'CinzelDecorative-Bold.ttf', 66, 905, '#ffffff', shadow: ['#120326', 3, 3, 30]);
        $this->text($img, mb_strtoupper($day->format('j F')), 'Poppins-SemiBold.ttf', 26, 960, '#f5c542', tracking: 8);
        $this->words($img, $t, 1028, 'PlusJakartaSans.ttf', '#ece6fa', '#c8b8ea');
        $this->text($img, mb_strtoupper(config('church.name')), 'Cinzel.ttf', 22, 1300, '#f5c542', fitWidth: 900);
    }

    private function neon(GdImage $img, string $name, Carbon $day, ?GdImage $photo, array $t): void
    {
        $this->gradient($img, ['#14002e', '#0a0019', '#05000f']);
        $this->glow($img, 120, 220, 760, '#ff2e97', 118);
        $this->glow($img, 980, 330, 820, '#7b2ff7', 116);
        $this->glow($img, 900, 1230, 760, '#00e5ff', 118);
        $this->glow($img, 120, 1150, 520, '#ffe600', 122);
        $colours = ['#ff2e97', '#00e5ff', '#ffe600', '#b388ff'];
        for ($i = 0; $i < 60; $i++) {
            $this->confetti($img, mt_rand(20, 1060), mt_rand(20, 1330), $colours[$i % 4], mt_rand(10, 60), [540, 440, 250]);
        }
        $this->logo($img, 540, 80, 38);
        $this->ringGlow($img, 540, 440, 205, '#ff2e97');
        $this->portrait($img, $photo, $name, 540, 440, 190, ['#ff2e97', '#00e5ff'], 'circle', '#14002e');
        $this->text($img, $t['headline'], 'Pacifico-Regular.ttf', 92, 775, '#ff6ac1', glow: '#ff2e97');
        $this->name($img, mb_strtoupper($name), 'Anton-Regular.ttf', 124, 915, '#ffffff', shadow: ['#00e5ff', 5, 5, 0]);
        $this->text($img, mb_strtoupper($day->format('j F')), 'Poppins-SemiBold.ttf', 28, 972, '#00e5ff', tracking: 10);
        $this->words($img, $t, 1040, 'Poppins-Medium.ttf', '#ffffff', '#cbb8ff');
        $this->text($img, mb_strtoupper(config('church.name')), 'Poppins-SemiBold.ttf', 20, 1302, '#ff6ac1', tracking: 4, fitWidth: 940);
    }

    private function balloons(GdImage $img, string $name, Carbon $day, ?GdImage $photo, array $t): void
    {
        $this->gradient($img, ['#ffe6f2', '#fff4e0', '#e4f1ff']);
        $palette = ['#ff5a7a', '#f5b82e', '#1b3a8a', '#20c3b0', '#8b5cf6'];
        foreach ([[110, 260, 0], [240, 180, 1], [80, 470, 3], [965, 250, 4], [840, 170, 2], [1000, 480, 0]] as [$x, $y, $c]) {
            $this->balloon($img, $x, $y, mt_rand(118, 150), $palette[$c]);
        }
        for ($i = 0; $i < 70; $i++) {
            $this->dot($img, mt_rand(20, 1060), mt_rand(20, 1330), mt_rand(4, 10), $palette[$i % 5], mt_rand(10, 50), [540, 450, 250]);
        }
        $this->logo($img, 540, 84, 42, shadow: true);
        $this->shadow($img, 540, 462, 210);
        $this->portrait($img, $photo, $name, 540, 450, 192, ['#ffffff', '#ffffff'], 'circle', '#1b3a8a');
        $this->text($img, $t['headline'], 'Pacifico-Regular.ttf', 90, 790, '#ff5a7a', shadow: ['#ffffff', 3, 3, 0]);
        $this->name($img, mb_strtoupper($name), 'Poppins-Black.ttf', 78, 890, '#13235b');
        $this->text($img, mb_strtoupper($day->format('j F')), 'Poppins-SemiBold.ttf', 26, 945, '#ff5a7a', tracking: 8);
        $this->words($img, $t, 1012, 'Poppins-Medium.ttf', '#2b3a67', '#5a6890');
        $this->text($img, mb_strtoupper(config('church.name')), 'Poppins-SemiBold.ttf', 20, 1302, '#13235b', tracking: 3, fitWidth: 940);
    }

    private function gold(GdImage $img, string $name, Carbon $day, ?GdImage $photo, array $t): void
    {
        $this->gradient($img, ['#17140e', '#0b0b0d', '#15120c']);
        $this->rays($img, 540, 450, '#d4af37', 118, 36);
        $this->glow($img, 540, 450, 560, '#d4af37', 116);
        $this->frame($img, '#d4af37');
        for ($i = 0; $i < 40; $i++) {
            $this->sparkle($img, mt_rand(70, 1010), mt_rand(70, 1280), mt_rand(4, 11), '#f3dc8c', mt_rand(30, 80), [540, 450, 260]);
        }
        $this->logo($img, 540, 100, 40);
        $this->ellipse($img, 540, 460, 2 * 232, '#d4af37');
        $this->ellipse($img, 540, 460, 2 * 224, '#0b0b0d');
        $this->portrait($img, $photo, $name, 540, 460, 204, ['#d4af37', '#f3dc8c'], 'circle', '#17140e');
        $this->text($img, $t['headline'], 'GreatVibes-Regular.ttf', 112, 820, '#e6c65c', shadow: ['#000000', 3, 3, 40]);
        $this->name($img, $name, 'CinzelDecorative-Bold.ttf', 62, 910, '#f3dc8c');
        $this->text($img, mb_strtoupper($day->format('j F')), 'Cinzel.ttf', 26, 962, '#d4af37', tracking: 8);
        $this->words($img, $t, 1030, 'PlusJakartaSans.ttf', '#eee6d0', '#a99d7c');
        $this->text($img, mb_strtoupper(config('church.name')), 'Cinzel.ttf', 22, 1270, '#d4af37', fitWidth: 860);
    }

    private function pastel(GdImage $img, string $name, Carbon $day, ?GdImage $photo, array $t): void
    {
        $this->gradient($img, ['#fff7ee', '#fff7ee']);
        $this->blob($img, 140, 180, 520, '#ffd2b8');
        $this->blob($img, 990, 380, 560, '#e2d4ff');
        $this->blob($img, 80, 1220, 600, '#c9f2e1');
        $this->blob($img, 1020, 1300, 420, '#ffe59e');
        $this->squiggle($img, 70, 640, 220, '#ff6b6b');
        $this->squiggle($img, 820, 120, 200, '#8a6cff');
        $this->squiggle($img, 40, 360, 160, '#20c3b0');
        $colours = ['#ff6b6b', '#8a6cff', '#20c3b0', '#f5b82e'];
        for ($i = 0; $i < 26; $i++) {
            $i % 2
                ? $this->sparkle($img, mt_rand(30, 1050), mt_rand(30, 1320), mt_rand(8, 16), $colours[$i % 4], 0, [540, 450, 260])
                : $this->ring($img, mt_rand(30, 1050), mt_rand(30, 1320), mt_rand(8, 16), $colours[$i % 4], [540, 450, 260]);
        }
        $this->logo($img, 540, 84, 42, shadow: true);
        $this->shadow($img, 548, 470, 214, square: true);
        $this->portrait($img, $photo, $name, 540, 455, 200, ['#ffffff', '#ffffff'], 'rounded', '#1d1a4d');
        $this->text($img, $t['headline'], 'Pacifico-Regular.ttf', 92, 790, '#ff6b6b');
        $this->name($img, mb_strtoupper($name), 'Poppins-Black.ttf', 80, 892, '#1d1a4d');
        $this->text($img, mb_strtoupper($day->format('j F')), 'Poppins-SemiBold.ttf', 26, 947, '#8a6cff', tracking: 8);
        $this->words($img, $t, 1014, 'Poppins-Medium.ttf', '#3b3760', '#6f6a91');
        $this->text($img, mb_strtoupper(config('church.name')), 'Poppins-SemiBold.ttf', 20, 1302, '#1d1a4d', tracking: 3, fitWidth: 940);
    }

    private function sunset(GdImage $img, string $name, Carbon $day, ?GdImage $photo, array $t): void
    {
        $this->gradient($img, ['#ff8a00', '#ff2e63', '#7b2ff7']);
        $this->text($img, 'HBD', 'Anton-Regular.ttf', 560, 820, '#ffffff', alpha: 112);
        foreach ([[130, 210, 90], [960, 330, 140], [880, 1180, 110], [160, 1100, 70]] as [$x, $y, $r]) {
            $this->outline($img, $x, $y, $r, '#ffffff', 90);
        }
        for ($i = 0; $i < 50; $i++) {
            $this->confetti($img, mt_rand(20, 1060), mt_rand(20, 1330), ['#ffe66d', '#ffffff', '#3a0d63'][$i % 3], mt_rand(10, 50), [540, 440, 250]);
        }
        $this->logo($img, 540, 80, 38);
        $this->shadow($img, 540, 455, 214);
        $this->portrait($img, $photo, $name, 540, 440, 200, ['#ffffff', '#ffe66d'], 'circle', '#7b2ff7');
        $this->text($img, $t['headline'], 'Pacifico-Regular.ttf', 90, 785, '#ffffff', shadow: ['#7a1040', 4, 4, 50]);
        $this->name($img, mb_strtoupper($name), 'Anton-Regular.ttf', 122, 925, '#ffffff', shadow: ['#4b0f6e', 5, 5, 30]);
        $this->text($img, mb_strtoupper($day->format('j F')), 'Poppins-SemiBold.ttf', 28, 982, '#ffe66d', tracking: 10);
        $this->words($img, $t, 1048, 'Poppins-Medium.ttf', '#ffffff', '#ffe3f0');
        $this->text($img, mb_strtoupper(config('church.name')), 'Poppins-SemiBold.ttf', 20, 1302, '#ffffff', tracking: 3, fitWidth: 940);
    }

    /**
     * A design the media unit uploaded: their picture, with the photo, name and day placed on it.
     *
     * @param  array<string, mixed>  $l
     */
    private function uploaded(GdImage $img, BirthdayDesign $design, array $l, string $name, Carbon $day, ?GdImage $photo): void
    {
        $bg = Storage::disk('public')->exists($design->background_path) ? @imagecreatefromstring(Storage::disk('public')->get($design->background_path)) : false;
        if ($bg instanceof GdImage) {
            $w = imagesx($img);
            $h = imagesy($img);
            $scale = max($w / imagesx($bg), $h / imagesy($bg));
            $sw = (int) round($w / $scale);
            $sh = (int) round($h / $scale);
            imagecopyresampled($img, $bg, 0, 0, (int) ((imagesx($bg) - $sw) / 2), (int) ((imagesy($bg) - $sh) / 2), $w, $h, $sw, $sh);
        } else {
            $this->gradient($img, ['#0a1d3f', '#13306a']);
        }

        if ($l['photo_shape'] !== 'none') {
            $r = (int) round($l['photo_size'] / 100 * self::WIDTH / 2);
            $x = (int) round($l['photo_x'] / 100 * self::WIDTH);
            $y = (int) round($l['photo_y'] / 100 * self::HEIGHT);
            $border = $l['photo_border'] ?: null;
            // Initials sit on a white circle, so they need a dark colour.
            $initials = $this->isLight($l['name_color']) ? '#0a1d3f' : $l['name_color'];
            $this->portrait($img, $photo, $name, $x, $y, $r, $border ? [$border, $border] : null, $l['photo_shape'], $initials);
        }
        $font = self::NAME_FONTS[$l['name_font']][0] ?? 'Poppins-Black.ttf';
        $nameY = (int) round($l['name_y'] / 100 * self::HEIGHT);
        $this->name($img, $l['name_upper'] ? mb_strtoupper($name) : $name, $font, (int) $l['name_size'], $nameY, $l['name_color'],
            shadow: $l['name_shadow'] ? ['#000000', 3, 3, 60] : null);
        if ($l['show_date']) {
            $this->text($img, mb_strtoupper($day->format('j F')), 'Poppins-SemiBold.ttf', max(18, (int) ($l['name_size'] * 0.34)), $nameY + (int) ($l['name_size'] * 0.75), $l['date_color'], tracking: 6);
        }
        if ($l['show_message']) {
            $t = $this->template();
            $t['verse'] = '';
            $this->words($img, $t, $nameY + (int) ($l['name_size'] * 1.45), 'Poppins-Medium.ttf', $l['date_color'], $l['date_color']);
        }
    }

    // ─── Drawing helpers (coordinates are in card pixels, 1080 × 1350) ───────

    /**
     * @param  list<string>  $stops
     */
    private function gradient(GdImage $img, array $stops): void
    {
        $h = imagesy($img);
        $w = imagesx($img);
        $rgb = array_map(fn ($hex) => $this->rgb($hex), $stops);
        $segments = max(1, count($rgb) - 1);
        for ($y = 0; $y < $h; $y++) {
            $f = $y / $h * $segments;
            $i = min((int) $f, $segments - 1);
            $p = $f - $i;
            $a = $rgb[$i];
            $b = $rgb[min($i + 1, count($rgb) - 1)];
            imageline($img, 0, $y, $w, $y, imagecolorallocate($img, ...array_map(fn ($x, $z) => (int) round($x + ($z - $x) * $p), $a, $b)));
        }
    }

    /**
     * A soft light: many faint circles, brighter towards the middle.
     */
    private function glow(GdImage $img, int $x, int $y, int $d, string $hex, int $alpha): void
    {
        for ($i = 16; $i > 0; $i--) {
            $size = (int) ($d * $i / 16) * self::S;
            imagefilledellipse($img, $x * self::S, $y * self::S, $size, $size, $this->colour($img, $hex, $alpha));
        }
    }

    private function rays(GdImage $img, int $x, int $y, string $hex, int $alpha, int $count = 24): void
    {
        $c = $this->colour($img, $hex, $alpha);
        for ($i = 0; $i < $count; $i++) {
            $a = 2 * M_PI * $i / $count;
            $b = $a + M_PI / $count / 2;
            $len = 1600;
            imagefilledpolygon($img, [
                $x * self::S, $y * self::S,
                (int) (($x + cos($a) * $len) * self::S), (int) (($y + sin($a) * $len) * self::S),
                (int) (($x + cos($b) * $len) * self::S), (int) (($y + sin($b) * $len) * self::S),
            ], $c);
        }
    }

    /**
     * A four-pointed sparkle.
     *
     * @param  array{0: int, 1: int, 2: int}|null  $avoid  a circle (x, y, radius) to keep clear of
     */
    private function sparkle(GdImage $img, int $x, int $y, int $r, string $hex, int $alpha, ?array $avoid = null): void
    {
        if ($this->inTheWay($x, $y, $avoid)) {
            return;
        }
        $s = self::S;
        $k = $r * 0.28;
        imagefilledpolygon($img, [
            $x * $s, (int) (($y - $r) * $s), (int) (($x + $k) * $s), (int) (($y - $k) * $s),
            (int) (($x + $r) * $s), $y * $s, (int) (($x + $k) * $s), (int) (($y + $k) * $s),
            $x * $s, (int) (($y + $r) * $s), (int) (($x - $k) * $s), (int) (($y + $k) * $s),
            (int) (($x - $r) * $s), $y * $s, (int) (($x - $k) * $s), (int) (($y - $k) * $s),
        ], $this->colour($img, $hex, $alpha));
    }

    private function confetti(GdImage $img, int $x, int $y, string $hex, int $alpha, ?array $avoid = null): void
    {
        if ($this->inTheWay($x, $y, $avoid)) {
            return;
        }
        $a = deg2rad(mt_rand(0, 180));
        $l = mt_rand(10, 22);
        $w = mt_rand(4, 7);
        $dx = cos($a) * $l;
        $dy = sin($a) * $l;
        $px = -sin($a) * $w;
        $py = cos($a) * $w;
        $s = self::S;
        imagefilledpolygon($img, [
            (int) (($x - $dx - $px) * $s), (int) (($y - $dy - $py) * $s), (int) (($x + $dx - $px) * $s), (int) (($y + $dy - $py) * $s),
            (int) (($x + $dx + $px) * $s), (int) (($y + $dy + $py) * $s), (int) (($x - $dx + $px) * $s), (int) (($y - $dy + $py) * $s),
        ], $this->colour($img, $hex, $alpha));
    }

    private function dot(GdImage $img, int $x, int $y, int $r, string $hex, int $alpha, ?array $avoid = null): void
    {
        if ($this->inTheWay($x, $y, $avoid)) {
            return;
        }
        imagefilledellipse($img, $x * self::S, $y * self::S, $r * 2 * self::S, $r * 2 * self::S, $this->colour($img, $hex, $alpha));
    }

    private function ring(GdImage $img, int $x, int $y, int $r, string $hex, ?array $avoid = null): void
    {
        if ($this->inTheWay($x, $y, $avoid)) {
            return;
        }
        $this->ellipse($img, $x, $y, $r * 2, $hex);
        $this->ellipse($img, $x, $y, $r * 2 - 10, '#fff7ee');
    }

    private function outline(GdImage $img, int $x, int $y, int $r, string $hex, int $alpha): void
    {
        imagesetthickness($img, 6 * self::S);
        imageellipse($img, $x * self::S, $y * self::S, $r * 2 * self::S, $r * 2 * self::S, $this->colour($img, $hex, $alpha));
        imagesetthickness($img, 1);
    }

    private function blob(GdImage $img, int $x, int $y, int $d, string $hex): void
    {
        $c = $this->colour($img, $hex, 0);
        // A few overlapping circles make a soft, uneven shape.
        foreach ([[0, 0, 1], [0.18, -0.12, 0.72], [-0.2, 0.15, 0.7], [0.1, 0.22, 0.6]] as [$ox, $oy, $f]) {
            imagefilledellipse($img, (int) (($x + $ox * $d) * self::S), (int) (($y + $oy * $d) * self::S), (int) ($d * $f * self::S), (int) ($d * $f * self::S), $c);
        }
    }

    private function squiggle(GdImage $img, int $x, int $y, int $length, string $hex): void
    {
        $c = $this->colour($img, $hex, 0);
        for ($i = 0; $i <= $length; $i += 2) {
            $yy = $y + sin($i / 18) * 14;
            imagefilledellipse($img, ($x + $i) * self::S, (int) ($yy * self::S), 10 * self::S, 10 * self::S, $c);
        }
    }

    private function balloon(GdImage $img, int $x, int $y, int $w, string $hex): void
    {
        $h = (int) ($w * 1.22);
        $s = self::S;
        // The string, swaying down.
        $string = $this->colour($img, '#7a7f99', 40);
        for ($i = 0; $i < 220; $i += 2) {
            imagefilledellipse($img, (int) (($x + sin($i / 22) * 9) * $s), ($y + (int) ($h / 2) + 10 + $i) * $s, 3 * $s, 3 * $s, $string);
        }
        imagefilledpolygon($img, [($x - 10) * $s, ($y + (int) ($h / 2) + 12) * $s, ($x + 10) * $s, ($y + (int) ($h / 2) + 12) * $s, $x * $s, ($y + (int) ($h / 2) - 4) * $s], $this->colour($img, $hex, 0));
        imagefilledellipse($img, $x * $s, $y * $s, $w * $s, $h * $s, $this->colour($img, $hex, 0));
        // A shine.
        imagefilledellipse($img, (int) (($x - $w * 0.2) * $s), (int) (($y - $h * 0.22) * $s), (int) ($w * 0.22 * $s), (int) ($h * 0.3 * $s), $this->colour($img, '#ffffff', 70));
    }

    private function crown(GdImage $img, int $cx, int $base, int $w, string $hex): void
    {
        $s = self::S;
        $h = (int) ($w * 0.62);
        $l = $cx - (int) ($w / 2);
        $r = $cx + (int) ($w / 2);
        $points = [$l, $base, $l - 8, $base - $h + 20, $cx - (int) ($w * 0.25), $base - (int) ($h * 0.45), $cx, $base - $h, $cx + (int) ($w * 0.25), $base - (int) ($h * 0.45), $r + 8, $base - $h + 20, $r, $base];
        imagefilledpolygon($img, array_map(fn ($v, $i) => $v * $s + ($i % 2 ? 6 * $s : 4 * $s), $points, array_keys($points)), $this->colour($img, '#120326', 60));
        imagefilledpolygon($img, array_map(fn ($v) => $v * $s, $points), $this->colour($img, $hex, 0));
        imagefilledrectangle($img, ($l - 2) * $s, ($base - 6) * $s, ($r + 2) * $s, ($base + 16) * $s, $this->colour($img, '#e0a020', 0));
        foreach ([[$l - 8, $base - $h + 20], [$cx, $base - $h], [$r + 8, $base - $h + 20]] as [$px, $py]) {
            $this->ellipse($img, $px, $py, 22, '#fff3c4');
        }
        foreach ([[$cx - 50, '#e5394d'], [$cx, '#2f80ed'], [$cx + 50, '#27ae60']] as [$px, $jewel]) {
            $this->ellipse($img, $px, $base + 5, 18, $jewel);
        }
    }

    private function frame(GdImage $img, string $hex): void
    {
        $s = self::S;
        $c = $this->colour($img, $hex, 0);
        imagesetthickness($img, 4 * $s);
        imagerectangle($img, 36 * $s, 36 * $s, (self::WIDTH - 36) * $s, (self::HEIGHT - 36) * $s, $c);
        imagesetthickness($img, 1 * $s);
        imagerectangle($img, 50 * $s, 50 * $s, (self::WIDTH - 50) * $s, (self::HEIGHT - 50) * $s, $c);
        imagesetthickness($img, 1);
        foreach ([[36, 36], [self::WIDTH - 36, 36], [36, self::HEIGHT - 36], [self::WIDTH - 36, self::HEIGHT - 36]] as [$x, $y]) {
            $this->sparkle($img, $x, $y, 22, $hex, 0);
        }
    }

    private function logo(GdImage $img, int $cx, int $cy, int $r, bool $shadow = false): void
    {
        $path = public_path(config('church.brand.logo'));
        $logo = is_file($path) ? @imagecreatefromstring((string) file_get_contents($path)) : false;
        if (! $logo instanceof GdImage) {
            return;
        }
        if ($shadow) {
            $this->shadow($img, $cx, $cy + 4, $r + 2);
        }
        $this->ellipse($img, $cx, $cy, $r * 2, '#ffffff');
        $size = (int) ($r * 1.5 * self::S);
        $scale = min($size / imagesx($logo), $size / imagesy($logo));
        $lw = (int) (imagesx($logo) * $scale);
        $lh = (int) (imagesy($logo) * $scale);
        imagecopyresampled($img, $logo, $cx * self::S - (int) ($lw / 2), $cy * self::S - (int) ($lh / 2), 0, 0, $lw, $lh, imagesx($logo), imagesy($logo));
    }

    private function shadow(GdImage $img, int $x, int $y, int $r, bool $square = false): void
    {
        for ($i = 6; $i > 0; $i--) {
            $d = ($r + $i * 4) * 2;
            $c = $this->colour($img, '#1d1a4d', 122);
            $square
                ? $this->roundedRect($img, $x - (int) ($d / 2), $y - (int) ($d / 2), $d, $d, 60 + $i * 4, $c)
                : imagefilledellipse($img, $x * self::S, $y * self::S, $d * self::S, $d * self::S, $c);
        }
    }

    private function ringGlow(GdImage $img, int $x, int $y, int $r, string $hex): void
    {
        for ($i = 10; $i > 0; $i--) {
            $d = ($r + $i * 5) * 2 * self::S;
            imagefilledellipse($img, $x * self::S, $y * self::S, $d, $d, $this->colour($img, $hex, 100 + $i * 2));
        }
    }

    /**
     * The member's photo (or their initials), with a two-colour border.
     *
     * @param  array{0: string, 1: string}|null  $border
     */
    private function portrait(GdImage $img, ?GdImage $photo, string $name, int $x, int $y, int $r, ?array $border, string $shape, string $initialsColour): void
    {
        $s = self::S;
        if ($border) {
            $this->shape($img, $shape, $x, $y, $r + 16, $border[0]);
            $this->shape($img, $shape, $x, $y, $r + 5, $border[1]);
        }
        if (! $photo) {
            $this->shape($img, $shape, $x, $y, $r, '#ffffff');
            $words = array_values(array_filter(preg_split('/\s+/', (string) preg_replace('/^((bro|sis|pst|pastor|dcn|dcns|mr|mrs|miss|dr)\.?\s+)+/i', '', $name))));
            $letters = mb_strtoupper(mb_substr($words[0] ?? '?', 0, 1).mb_substr($words[1] ?? '', 0, 1));
            $size = (int) ($r * 0.72);
            $box = imagettfbbox($size * $s, 0, $this->font('Poppins-Black.ttf'), $letters);
            $this->text($img, $letters, 'Poppins-Black.ttf', $size, $y + (int) ((abs($box[7]) - abs($box[1])) / 2 / $s), $initialsColour, x: $x);

            return;
        }
        $d = $r * 2 * $s;
        $side = min(imagesx($photo), imagesy($photo));
        $square = imagecreatetruecolor($d, $d);
        // A little above the middle, where faces usually are.
        imagecopyresampled($square, $photo, 0, 0, (int) ((imagesx($photo) - $side) / 2), (int) max(0, (imagesy($photo) - $side) / 3), $d, $d, $side, $side);

        // Paint everything outside the shape in a key colour, then make that colour see-through.
        $mask = imagecreatetruecolor($d, $d);
        $key = imagecolorallocate($mask, 255, 0, 255);
        $clear = imagecolorallocate($mask, 0, 0, 0);
        imagefill($mask, 0, 0, $key);
        $shape === 'rounded'
            ? $this->roundedRect($mask, 0, 0, $d / $s, $d / $s, (int) ($r * 0.35), $clear)
            : imagefilledellipse($mask, (int) ($d / 2), (int) ($d / 2), $d, $d, $clear);
        imagecolortransparent($mask, $clear);
        imagecopymerge($square, $mask, 0, 0, 0, 0, $d, $d, 100);
        imagecolortransparent($square, imagecolorclosest($square, 255, 0, 255));
        imagecopy($img, $square, ($x - $r) * $s, ($y - $r) * $s, 0, 0, $d, $d);
    }

    private function shape(GdImage $img, string $shape, int $x, int $y, int $r, string $hex): void
    {
        $shape === 'rounded'
            ? $this->roundedRect($img, $x - $r, $y - $r, $r * 2, $r * 2, (int) ($r * 0.35), $this->colour($img, $hex, 0))
            : $this->ellipse($img, $x, $y, $r * 2, $hex);
    }

    private function roundedRect(GdImage $img, int $x, int $y, int $w, int $h, int $radius, int $colour): void
    {
        $s = self::S;
        [$x, $y, $w, $h, $radius] = [$x * $s, $y * $s, $w * $s, $h * $s, $radius * $s];
        imagefilledrectangle($img, $x + $radius, $y, $x + $w - $radius, $y + $h, $colour);
        imagefilledrectangle($img, $x, $y + $radius, $x + $w, $y + $h - $radius, $colour);
        foreach ([[$x + $radius, $y + $radius], [$x + $w - $radius, $y + $radius], [$x + $radius, $y + $h - $radius], [$x + $w - $radius, $y + $h - $radius]] as [$cx, $cy]) {
            imagefilledellipse($img, $cx, $cy, $radius * 2, $radius * 2, $colour);
        }
    }

    private function ellipse(GdImage $img, int $x, int $y, int $d, string $hex): void
    {
        imagefilledellipse($img, $x * self::S, $y * self::S, $d * self::S, $d * self::S, $this->colour($img, $hex, 0));
    }

    /**
     * The name, made smaller if it's long.
     */
    private function name(GdImage $img, string $name, string $font, int $size, int $y, string $hex, ?array $shadow = null): void
    {
        $this->text($img, $name, $font, $size, $y, $hex, shadow: $shadow, fitWidth: 960);
    }

    /**
     * The message, then the verse, centred and wrapped.
     *
     * @param  array{message: string, verse: string}  $t
     */
    private function words(GdImage $img, array $t, int $y, string $font, string $colour, string $soft): void
    {
        foreach (array_slice($this->wrap($t['message'], $font, 29, 860), 0, 3) as $line) {
            $this->text($img, $line, $font, 29, $y, $colour);
            $y += 44;
        }
        if (filled($t['verse'])) {
            $y += 14;
            foreach (array_slice($this->wrap($t['verse'], 'PlusJakartaSans.ttf', 22, 820), 0, 3) as $line) {
                $this->text($img, $line, 'PlusJakartaSans.ttf', 22, $y, $soft);
                $y += 34;
            }
        }
    }

    /**
     * Text centred on $x (the middle of the card by default), with optional letter spacing,
     * shadow, glow and see-through-ness. Sizes are in card pixels.
     *
     * @param  array{0: string, 1: int, 2: int, 3: int}|null  $shadow  colour, x and y offset, alpha
     */
    private function text(GdImage $img, string $text, string $font, float $size, int $y, string $hex, int $tracking = 0, ?array $shadow = null, ?string $glow = null, int $alpha = 0, ?int $fitWidth = null, ?int $x = null): void
    {
        $s = self::S;
        $file = $this->font($font);
        if ($fitWidth) {
            $size = $this->fit($text, $file, $size, $fitWidth, $tracking);
        }
        $size *= $s;
        $chars = $tracking ? mb_str_split($text) : [$text];
        $widths = array_map(fn ($ch) => ($b = imagettfbbox($size, 0, $file, $ch))[2] - $b[0], $chars);
        $total = array_sum($widths) + $tracking * $s * (count($chars) - 1);
        $start = ($x ?? self::WIDTH / 2) * $s - $total / 2;

        $draw = function (int $dx, int $dy, int $colour) use ($img, $size, $file, $chars, $widths, $start, $y, $s, $tracking) {
            $cx = $start;
            foreach ($chars as $i => $ch) {
                imagettftext($img, $size, 0, (int) $cx + $dx, $y * $s + $dy, $colour, $file, $ch);
                $cx += $widths[$i] + $tracking * $s;
            }
        };
        if ($glow) {
            foreach ([10, 7, 4] as $radius) {
                for ($a = 0; $a < 360; $a += 45) {
                    $draw((int) (cos(deg2rad($a)) * $radius * $s), (int) (sin(deg2rad($a)) * $radius * $s), $this->colour($img, $glow, 108));
                }
            }
        }
        if ($shadow) {
            $draw($shadow[1] * $s, $shadow[2] * $s, $this->colour($img, $shadow[0], $shadow[3]));
        }
        $draw(0, 0, $this->colour($img, $hex, $alpha));
    }

    private function fit(string $text, string $file, float $size, int $width, int $tracking = 0): float
    {
        $box = imagettfbbox($size, 0, $file, $text);
        $actual = $box[2] - $box[0] + $tracking * max(0, mb_strlen($text) - 1);

        return $actual > $width ? $size * $width / $actual : $size;
    }

    /**
     * @return list<string>
     */
    private function wrap(string $text, string $font, float $size, int $width): array
    {
        $lines = [];
        $line = '';
        foreach (preg_split('/\s+/', trim($text)) as $word) {
            $try = $line === '' ? $word : $line.' '.$word;
            $box = imagettfbbox($size, 0, $this->font($font), $try);
            if ($width < $box[2] - $box[0] && $line !== '') {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $try;
            }
        }

        return $line === '' ? $lines : [...$lines, $line];
    }

    private function loadPhoto(string $path): ?GdImage
    {
        if (! Storage::disk('public')->exists($path)) {
            return null;
        }
        $photo = @imagecreatefromstring(Storage::disk('public')->get($path));

        return $photo instanceof GdImage ? $photo : null;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    private function isLight(string $hex): bool
    {
        [$r, $g, $b] = $this->rgb($hex);

        return 0.299 * $r + 0.587 * $g + 0.114 * $b > 160;
    }

    /**
     * A colour, from fully solid (alpha 0) to fully see-through (127).
     */
    private function colour(GdImage $img, string $hex, int $alpha): int
    {
        return imagecolorallocatealpha($img, ...[...$this->rgb($hex), max(0, min(127, $alpha))]);
    }

    private function font(string $file): string
    {
        return resource_path('fonts/'.$file);
    }
}
