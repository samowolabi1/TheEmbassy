<?php

namespace App\Services\Celebrations;

use App\Models\Member;
use GdImage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Draws a member's birthday card (1080 × 1350, the size for Instagram, Facebook and WhatsApp
 * status) from the church's template: their photo or initials, their name and the day (never
 * the year), the message, a verse and the church's logo. Drawn at double size and scaled down,
 * so the edges come out smooth.
 */
class BirthdayCard
{
    public const WIDTH = 1080;

    public const HEIGHT = 1350;

    private const SCALE = 2;

    public const DESIGNS = [
        'royal' => 'Royal: navy and gold',
        'sunrise' => 'Sunrise: warm gold and coral',
        'ivory' => 'Ivory: cream with a gold frame',
    ];

    private const PALETTES = [
        'royal' => ['top' => [10, 29, 63], 'bottom' => [24, 52, 112], 'accent' => [224, 168, 46], 'text' => [255, 255, 255], 'soft' => [205, 214, 235], 'confetti' => [[224, 168, 46], [255, 255, 255], [244, 205, 120]]],
        'sunrise' => ['top' => [242, 153, 74], 'bottom' => [235, 87, 87], 'accent' => [255, 241, 196], 'text' => [255, 255, 255], 'soft' => [255, 236, 214], 'confetti' => [[255, 241, 196], [255, 255, 255], [10, 29, 63]]],
        'ivory' => ['top' => [253, 248, 236], 'bottom' => [244, 232, 205], 'accent' => [176, 128, 26], 'text' => [10, 29, 63], 'soft' => [86, 98, 122], 'confetti' => [[224, 168, 46], [10, 29, 63], [235, 87, 87]]],
    ];

    /**
     * The card as PNG bytes.
     *
     * @param  array{design?: string, headline?: string, message?: string, verse?: string, background?: ?string}  $template
     */
    public function render(string $name, Carbon $day, ?string $photoPath = null, array $template = [], int $seed = 1): string
    {
        $t = [...$this->template(), ...array_filter($template, fn ($v) => $v !== null)];
        $p = self::PALETTES[$t['design']] ?? self::PALETTES['royal'];
        $s = self::SCALE;
        $w = self::WIDTH * $s;
        $h = self::HEIGHT * $s;

        $img = imagecreatetruecolor($w, $h);
        imagealphablending($img, true);
        $this->background($img, $p, $t['background'] ?? null);
        $this->confetti($img, $p, $seed);
        if ($t['design'] === 'ivory') {
            $this->frame($img, $p);
        }

        // The church's logo in a round badge at the top.
        $this->logo($img, (int) ($w / 2), 120 * $s, 60 * $s);

        // Their photo in a ring, or their initials.
        $cx = (int) ($w / 2);
        $cy = 410 * $s;
        $r = 185 * $s;
        $this->ellipse($img, $cx, $cy, $r * 2 + 26 * $s, $p['accent']);
        $photo = $photoPath ? $this->loadPhoto($photoPath) : null;
        $photo ? $this->circlePhoto($img, $photo, $cx, $cy, $r) : $this->initials($img, $name, $cx, $cy, $r, $p);

        // "Happy Birthday" in script, the name in capitals, the day.
        $y = 752 * $s;
        $this->centred($img, $t['headline'], 'GreatVibes-Regular.ttf', 104 * $s, $y, $p['accent']);
        $y += 124 * $s;
        $this->centred($img, mb_strtoupper($name), 'Cinzel.ttf', $this->fit(mb_strtoupper($name), 'Cinzel.ttf', 70 * $s, 940 * $s), $y, $p['text']);
        $y += 66 * $s;
        $this->centred($img, mb_strtoupper($day->format('j F')), 'PlusJakartaSans.ttf', 26 * $s, $y, $p['accent'], 6 * $s);

        // The message, then the verse.
        $y += 70 * $s;
        foreach ($this->wrap($t['message'], 'PlusJakartaSans.ttf', 30 * $s, 860 * $s) as $line) {
            $this->centred($img, $line, 'PlusJakartaSans.ttf', 30 * $s, $y, $p['text']);
            $y += 46 * $s;
        }
        if (filled($t['verse'])) {
            $y += 16 * $s;
            foreach (array_slice($this->wrap($t['verse'], 'PlusJakartaSans.ttf', 23 * $s, 820 * $s), 0, 3) as $line) {
                $this->centred($img, $line, 'PlusJakartaSans.ttf', 23 * $s, $y, $p['soft']);
                $y += 36 * $s;
            }
        }

        // The church's name at the foot.
        $this->centred($img, mb_strtoupper(config('church.name')), 'Cinzel.ttf', $this->fit(mb_strtoupper(config('church.name')), 'Cinzel.ttf', 24 * $s, 900 * $s), (self::HEIGHT - 76) * $s, $p['accent']);

        $out = imagescale($img, self::WIDTH, self::HEIGHT, IMG_BICUBIC);
        if (! $out instanceof GdImage) {
            throw new RuntimeException('The card could not be drawn.');
        }
        ob_start();
        imagepng($out, null, 6);

        return (string) ob_get_clean();
    }

    /**
     * A member's card for their next (or today's) birthday.
     */
    public function forMember(Member $member, array $template = []): string
    {
        $day = $member->birthday->copy()->year(today()->year);
        if ($member->birthday->format('m-d') === '02-29' && ! today()->isLeapYear()) {
            $day = Carbon::create(today()->year, 2, 28);
        }

        return $this->render($member->name, $day, $member->photo_path, $template, $member->id);
    }

    /**
     * The template saved by the media team (admin settings), over the defaults.
     *
     * @return array{design: string, headline: string, message: string, verse: string, background: ?string}
     */
    public function template(): array
    {
        $c = config('church.birthday');

        return [
            'design' => array_key_exists($c['design'] ?? '', self::DESIGNS) ? $c['design'] : 'royal',
            'headline' => filled($c['headline'] ?? null) ? $c['headline'] : 'Happy Birthday',
            'message' => filled($c['message'] ?? null) ? $c['message'] : 'Celebrating you today!',
            'verse' => (string) ($c['verse'] ?? ''),
            'background' => filled($c['background'] ?? null) ? $c['background'] : null,
        ];
    }

    private function background(GdImage $img, array $p, ?string $background): void
    {
        $w = imagesx($img);
        $h = imagesy($img);
        if ($background && Storage::disk('public')->exists($background) && ($bg = @imagecreatefromstring(Storage::disk('public')->get($background))) instanceof GdImage) {
            // Their own picture, cropped to fill, darkened so the words stand out.
            $scale = max($w / imagesx($bg), $h / imagesy($bg));
            $sw = (int) ($w / $scale);
            $sh = (int) ($h / $scale);
            imagecopyresampled($img, $bg, 0, 0, (int) ((imagesx($bg) - $sw) / 2), (int) ((imagesy($bg) - $sh) / 2), $w, $h, $sw, $sh);
            imagefilledrectangle($img, 0, 0, $w, $h, imagecolorallocatealpha($img, 6, 18, 42, 40));

            return;
        }
        for ($y = 0; $y < $h; $y++) {
            $f = $y / $h;
            $c = imagecolorallocate($img, ...array_map(fn ($a, $b) => (int) round($a + ($b - $a) * $f), $p['top'], $p['bottom']));
            imageline($img, 0, $y, $w, $y, $c);
        }
        // A soft glow behind the photo.
        for ($i = 14; $i > 0; $i--) {
            $glow = imagecolorallocatealpha($img, ...[...$p['accent'], 124]);
            imagefilledellipse($img, (int) ($w / 2), 410 * self::SCALE, $i * 90 * self::SCALE, $i * 90 * self::SCALE, $glow);
        }
    }

    private function confetti(GdImage $img, array $p, int $seed): void
    {
        mt_srand($seed);
        $w = imagesx($img);
        $s = self::SCALE;
        for ($i = 0; $i < 70; $i++) {
            $x = mt_rand(0, $w);
            $y = mt_rand(0, 700 * $s);
            // Keep clear of the photo.
            if (abs($x - $w / 2) < 270 * $s && $y > 180 * $s) {
                continue;
            }
            $c = imagecolorallocatealpha($img, ...[...$p['confetti'][$i % 3], mt_rand(10, 60)]);
            $size = mt_rand(5, 14) * $s;
            if ($i % 2) {
                imagefilledellipse($img, $x, $y, $size, $size, $c);
            } else {
                $a = deg2rad(mt_rand(0, 180));
                $dx = cos($a) * $size;
                $dy = sin($a) * $size;
                imagefilledpolygon($img, [(int) ($x - $dx), (int) ($y - $dy), (int) ($x + $dy / 2), (int) ($y - $dx / 2), (int) ($x + $dx), (int) ($y + $dy), (int) ($x - $dy / 2), (int) ($y + $dx / 2)], $c);
            }
        }
        mt_srand();
    }

    private function frame(GdImage $img, array $p): void
    {
        $s = self::SCALE;
        $c = imagecolorallocate($img, ...$p['accent']);
        imagesetthickness($img, 4 * $s);
        imagerectangle($img, 36 * $s, 36 * $s, imagesx($img) - 36 * $s, imagesy($img) - 36 * $s, $c);
        imagesetthickness($img, 1 * $s);
        imagerectangle($img, 50 * $s, 50 * $s, imagesx($img) - 50 * $s, imagesy($img) - 50 * $s, $c);
        imagesetthickness($img, 1);
    }

    private function logo(GdImage $img, int $cx, int $cy, int $r): void
    {
        $path = public_path(config('church.brand.logo'));
        $logo = is_file($path) ? @imagecreatefromstring((string) file_get_contents($path)) : false;
        if (! $logo instanceof GdImage) {
            return;
        }
        $this->ellipse($img, $cx, $cy, $r * 2, [255, 255, 255]);
        $size = (int) ($r * 1.5);
        $scale = min($size / imagesx($logo), $size / imagesy($logo));
        $lw = (int) (imagesx($logo) * $scale);
        $lh = (int) (imagesy($logo) * $scale);
        imagecopyresampled($img, $logo, $cx - (int) ($lw / 2), $cy - (int) ($lh / 2), 0, 0, $lw, $lh, imagesx($logo), imagesy($logo));
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
     * The photo cropped to a circle: copied in, then everything outside the circle painted over
     * with a mask whose key colour is made see-through.
     */
    private function circlePhoto(GdImage $img, GdImage $photo, int $cx, int $cy, int $r): void
    {
        $d = $r * 2;
        $side = min(imagesx($photo), imagesy($photo));
        $square = imagecreatetruecolor($d, $d);
        // A little above centre, where faces usually are.
        imagecopyresampled($square, $photo, 0, 0, (int) ((imagesx($photo) - $side) / 2), (int) max(0, (imagesy($photo) - $side) / 3), $d, $d, $side, $side);

        $mask = imagecreatetruecolor($d, $d);
        $key = imagecolorallocate($mask, 255, 0, 255);
        $clear = imagecolorallocate($mask, 0, 0, 0);
        imagefill($mask, 0, 0, $key);
        imagefilledellipse($mask, $r, $r, $d, $d, $clear);
        imagecolortransparent($mask, $clear);
        imagecopymerge($square, $mask, 0, 0, 0, 0, $d, $d, 100);
        imagecolortransparent($square, imagecolorclosest($square, 255, 0, 255));
        imagecopy($img, $square, $cx - $r, $cy - $r, 0, 0, $d, $d);
    }

    private function initials(GdImage $img, string $name, int $cx, int $cy, int $r, array $p): void
    {
        $light = $p['text'] === [255, 255, 255];
        $this->ellipse($img, $cx, $cy, $r * 2, $light ? [255, 255, 255] : [10, 29, 63]);
        $words = array_values(array_filter(preg_split('/\s+/', preg_replace('/^(bro|sis|pst|dcn|dcns|mr|mrs|miss|dr)\.?\s+/i', '', $name))));
        $letters = mb_strtoupper(mb_substr($words[0] ?? '?', 0, 1).mb_substr($words[1] ?? '', 0, 1));
        $box = imagettfbbox(150 * self::SCALE, 0, $this->font('Cinzel.ttf'), $letters);
        $this->centred($img, $letters, 'Cinzel.ttf', 150 * self::SCALE, $cy + (int) ((abs($box[7]) - abs($box[1])) / 2), $light ? [10, 29, 63] : $p['accent']);
    }

    private function ellipse(GdImage $img, int $cx, int $cy, int $d, array $rgb): void
    {
        imagefilledellipse($img, $cx, $cy, $d, $d, imagecolorallocate($img, ...$rgb));
    }

    private function centred(GdImage $img, string $text, string $font, float $size, int $baseline, array $rgb, int $tracking = 0): void
    {
        $file = $this->font($font);
        $colour = imagecolorallocate($img, ...$rgb);
        if ($tracking === 0) {
            $box = imagettfbbox($size, 0, $file, $text);
            imagettftext($img, $size, 0, (int) ((imagesx($img) - ($box[2] - $box[0])) / 2 - $box[0]), $baseline, $colour, $file, $text);

            return;
        }
        // Letter by letter, spaced out.
        $chars = mb_str_split($text);
        $widths = array_map(fn ($ch) => imagettfbbox($size, 0, $file, $ch)[2], $chars);
        $x = (imagesx($img) - array_sum($widths) - $tracking * (count($chars) - 1)) / 2;
        foreach ($chars as $i => $ch) {
            imagettftext($img, $size, 0, (int) $x, $baseline, $colour, $file, $ch);
            $x += $widths[$i] + $tracking;
        }
    }

    /**
     * The biggest size up to $size at which the text fits the width.
     */
    private function fit(string $text, string $font, float $size, int $width): float
    {
        $box = imagettfbbox($size, 0, $this->font($font), $text);
        $actual = $box[2] - $box[0];

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

    private function font(string $file): string
    {
        return resource_path('fonts/'.$file);
    }
}
