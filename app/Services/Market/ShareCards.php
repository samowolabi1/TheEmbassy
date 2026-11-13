<?php

namespace App\Services\Market;

use App\Models\Product;
use App\Models\Shop;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;
use GdImage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Pictures for sharing a shop or a listing on social media, drawn by the app: the photo, the name,
 * the price, the shop, the market's name and a QR code that opens the page. In three sizes:
 * square for a post, tall for WhatsApp status and stories, and wide for the preview that shows
 * when the link is pasted into a chat.
 */
class ShareCards
{
    public const FORMATS = [
        'square' => [1080, 1080, 'Square post (Instagram, Facebook)'],
        'story' => [1080, 1920, 'Status and stories (WhatsApp, Instagram)'],
        'wide' => [1200, 630, 'Link preview'],
    ];

    private const S = 2;

    private const NAVY = '#0a1d3f';

    private const GOLD = '#e0a82e';

    private const SOFT = '#c9d3ea';

    public function product(Product $product, string $format): string
    {
        $shop = $product->shop;
        $key = 'share-card:p:'.md5(json_encode([$product->id, $product->updated_at, $shop->name, $format, config('church.brand.market_name'), $this->url($product)]));

        return Cache::remember($key, now()->addWeek(), fn () => $this->draw($format, [
            'title' => $product->name,
            'badge' => $product->priceLabel(),
            'byline' => 'from '.$shop->name,
            'text' => $product->description,
            'images' => array_filter([$product->image_path]),
            'url' => $this->url($product),
            'cta' => $product->isService() ? 'Book now' : 'Order now',
        ]));
    }

    public function shop(Shop $shop, string $format): string
    {
        $products = $shop->products()->where('is_active', true)->latest()->get(['id', 'image_path', 'updated_at']);
        $key = 'share-card:s:'.md5(json_encode([$shop->id, $shop->updated_at, $products->max('updated_at'), $products->count(), $format, config('church.brand.market_name'), $this->url($shop)]));

        return Cache::remember($key, now()->addWeek(), fn () => $this->draw($format, [
            'title' => $shop->name,
            'badge' => $shop->category,
            'byline' => 'by '.$shop->owner_name.' · '.$products->count().' '.Str::plural('listing', $products->count()),
            'text' => $shop->description,
            'images' => $products->pluck('image_path')->filter()->take(4)->values()->all(),
            'url' => $this->url($shop),
            'cta' => 'Visit the shop',
        ]));
    }

    /**
     * A QR code on its own, for flyers and the shop counter.
     */
    public function qr(string $url, int $size = 800): string
    {
        return (new Writer(new GDLibRenderer($size, 2)))->writeString($url);
    }

    /**
     * The page's address, marked so visits from shared links can be counted.
     */
    public function url(Product|Shop $item, ?string $via = null): string
    {
        $url = $item instanceof Product ? route('market.product', $item) : route('market.shop', $item);

        return $via ? $url.'?s='.$via : $url;
    }

    /**
     * The words to post with it.
     */
    public function caption(Product|Shop $item, ?string $via = null): string
    {
        $market = config('church.brand.market_name');
        $tag = '#'.Str::studly($market);
        if ($item instanceof Product) {
            $lines = [
                ($item->isService() ? '✨ ' : '🛍️ ').$item->name.' · '.$item->priceLabel(),
                Str::limit(preg_replace('/\s+/', ' ', (string) $item->description), 220),
                '',
                ($item->isService() ? 'Book with ' : 'Order from ').$item->shop->name.' on '.$market.' 👉 '.$this->url($item, $via),
            ];
        } else {
            $lines = [
                '🛍️ '.$item->name.' is on '.$market.'!',
                Str::limit(preg_replace('/\s+/', ' ', (string) $item->description), 220),
                '',
                'See everything we offer 👉 '.$this->url($item, $via),
            ];
        }

        return implode("\n", [...$lines, '', $tag]);
    }

    /**
     * @param  array{title: string, badge: string, byline: string, text: ?string, images: list<string>, url: string, cta: string}  $c
     */
    private function draw(string $format, array $c): string
    {
        [$w, $h] = self::FORMATS[$format] ?? self::FORMATS['square'];
        $img = imagecreatetruecolor($w * self::S, $h * self::S);
        imagealphablending($img, true);
        $this->fill($img, 0, 0, $w, $h, self::NAVY);
        $photos = array_values(array_filter(array_map(fn ($p) => $this->load($p), $c['images'])));

        match ($format) {
            'story' => $this->story($img, $c, $photos),
            'wide' => $this->wide($img, $c, $photos),
            default => $this->square($img, $c, $photos),
        };

        $out = imagescale($img, $w, $h, IMG_BICUBIC);
        if (! $out instanceof GdImage) {
            throw new RuntimeException('The picture could not be drawn.');
        }
        ob_start();
        imagepng($out, null, 6);

        return (string) ob_get_clean();
    }

    /**
     * @param  list<GdImage>  $photos
     */
    private function square(GdImage $img, array $c, array $photos): void
    {
        $this->photos($img, $photos, 60, 60, 960, 560, 36, $c['title']);
        $this->badge($img, $c['badge'], 90, 560);
        $y = 700;
        foreach ($this->lines($c['title'], 'Poppins-Black.ttf', 54, 940, 2) as $line) {
            $this->text($img, $line, 'Poppins-Black.ttf', 54, 70, $y, '#ffffff');
            $y += 64;
        }
        $this->text($img, $c['byline'], 'Poppins-SemiBold.ttf', 28, 70, $y + 2, self::GOLD, 940);
        if (filled($c['text'])) {
            $this->text($img, Str::limit(preg_replace('/\s+/', ' ', $c['text']), 70), 'PlusJakartaSans.ttf', 24, 70, $y + 48, self::SOFT, 940);
        }
        $this->footer($img, $c, 60, 900, 960, 140, 140);
    }

    /**
     * @param  list<GdImage>  $photos
     */
    private function story(GdImage $img, array $c, array $photos): void
    {
        $this->brand($img, 60, 70);
        $this->photos($img, $photos, 60, 150, 960, 960, 44, $c['title']);
        $this->badge($img, $c['badge'], 96, 1040, 40);
        $y = 1215;
        foreach ($this->lines($c['title'], 'Poppins-Black.ttf', 62, 940, 3) as $line) {
            $this->text($img, $line, 'Poppins-Black.ttf', 62, 70, $y, '#ffffff');
            $y += 74;
        }
        $this->text($img, $c['byline'], 'Poppins-SemiBold.ttf', 32, 70, $y + 4, self::GOLD, 940);
        $y += 60;
        if (filled($c['text'])) {
            foreach ($this->lines(preg_replace('/\s+/', ' ', $c['text']), 'PlusJakartaSans.ttf', 28, 940, 2) as $line) {
                $this->text($img, $line, 'PlusJakartaSans.ttf', 28, 70, $y, self::SOFT);
                $y += 42;
            }
        }
        // A big code to scan, and what to do.
        $this->qrBlock($img, $c['url'], 60, 1560, 300);
        $this->text($img, $c['cta'].':', 'Poppins-Black.ttf', 44, 400, 1650, '#ffffff');
        $this->text($img, 'scan the code, or tap the', 'Poppins-Medium.ttf', 28, 400, 1705, self::SOFT);
        $this->text($img, 'link in the post', 'Poppins-Medium.ttf', 28, 400, 1745, self::SOFT);
        $this->text($img, $this->shortUrl($c['url']), 'Poppins-SemiBold.ttf', 22, 400, 1810, self::GOLD, 620);
    }

    /**
     * @param  list<GdImage>  $photos
     */
    private function wide(GdImage $img, array $c, array $photos): void
    {
        $this->photos($img, $photos, 0, 0, 630, 630, 0, $c['title']);
        $this->badge($img, $c['badge'], 680, 70, 30);
        $y = 210;
        foreach ($this->lines($c['title'], 'Poppins-Black.ttf', 48, 480, 3) as $line) {
            $this->text($img, $line, 'Poppins-Black.ttf', 48, 680, $y, '#ffffff');
            $y += 58;
        }
        $this->text($img, $c['byline'], 'Poppins-SemiBold.ttf', 24, 680, $y + 4, self::GOLD, 480);
        if (filled($c['text'])) {
            $this->text($img, Str::limit(preg_replace('/\s+/', ' ', $c['text']), 48), 'PlusJakartaSans.ttf', 20, 680, $y + 44, self::SOFT, 480);
        }
        $this->brand($img, 680, 560);
    }

    /**
     * The market's name and the church's logo, the link, and the QR code.
     */
    private function footer(GdImage $img, array $c, int $x, int $y, int $w, int $h, int $qr): void
    {
        $this->rounded($img, $x, $y, $w, $h, 28, '#13306a');
        $this->brand($img, $x + 30, $y + 50);
        $this->text($img, $c['cta'].' · scan or tap the link', 'Poppins-Medium.ttf', 22, $x + 30, $y + 104, self::SOFT, $w - $qr - 80);
        $this->qrBlock($img, $c['url'], $x + $w - $qr - 10, $y + (int) (($h - $qr) / 2), $qr);
    }

    private function brand(GdImage $img, int $x, int $y): void
    {
        $path = public_path(config('church.brand.logo'));
        $logo = is_file($path) ? @imagecreatefromstring((string) file_get_contents($path)) : false;
        if ($logo instanceof GdImage) {
            $this->circle($img, $x + 26, $y - 10, 56, '#ffffff');
            $size = 40 * self::S;
            $scale = min($size / imagesx($logo), $size / imagesy($logo));
            imagecopyresampled($img, $logo, (int) (($x + 26) * self::S - imagesx($logo) * $scale / 2), (int) (($y - 10) * self::S - imagesy($logo) * $scale / 2), 0, 0,
                (int) (imagesx($logo) * $scale), (int) (imagesy($logo) * $scale), imagesx($logo), imagesy($logo));
        }
        $this->text($img, config('church.brand.market_name'), 'Poppins-Black.ttf', 30, $x + 68, $y, '#ffffff', 520);
    }

    private function qrBlock(GdImage $img, string $url, int $x, int $y, int $size): void
    {
        $this->rounded($img, $x, $y, $size, $size, 18, '#ffffff');
        $qr = imagecreatefromstring($this->qr($url, ($size - 24) * self::S));
        if ($qr instanceof GdImage) {
            imagecopy($img, $qr, ($x + 12) * self::S, ($y + 12) * self::S, 0, 0, imagesx($qr), imagesy($qr));
        }
    }

    /**
     * One photo filling the space, or a grid of up to four (for a shop); the name's first letter
     * when there are none.
     *
     * @param  list<GdImage>  $photos
     */
    private function photos(GdImage $img, array $photos, int $x, int $y, int $w, int $h, int $radius, string $name): void
    {
        $s = self::S;
        $area = imagecreatetruecolor($w * $s, $h * $s);
        if ($photos === []) {
            $this->gradient($area, ['#f5c542', '#e0a82e', '#b7791f']);
            $letter = mb_strtoupper(mb_substr(trim($name), 0, 1));
            $size = min($w, $h) * 0.45 * $s;
            $box = imagettfbbox($size, 0, $this->font('Poppins-Black.ttf'), $letter);
            imagettftext($area, $size, 0, (int) (($w * $s - ($box[2] - $box[0])) / 2 - $box[0]), (int) (($h * $s + abs($box[7])) / 2), imagecolorallocate($area, 10, 29, 63), $this->font('Poppins-Black.ttf'), $letter);
        } else {
            $cells = count($photos) >= 4 ? [[0, 0, 0.5, 0.5], [0.5, 0, 0.5, 0.5], [0, 0.5, 0.5, 0.5], [0.5, 0.5, 0.5, 0.5]]
                : (count($photos) >= 2 ? [[0, 0, 0.6, 1], [0.6, 0, 0.4, 1]] : [[0, 0, 1, 1]]);
            foreach ($cells as $i => [$cx, $cy, $cw, $ch]) {
                $this->cover($area, $photos[$i], (int) ($cx * $w * $s), (int) ($cy * $h * $s), (int) ($cw * $w * $s), (int) ($ch * $h * $s));
            }
            if (count($cells) > 1) {
                $line = imagecolorallocate($area, 10, 29, 63);
                imagesetthickness($area, 6 * $s);
                foreach ($cells as [$cx, $cy]) {
                    $cx > 0 && imageline($area, (int) ($cx * $w * $s), 0, (int) ($cx * $w * $s), $h * $s, $line);
                    $cy > 0 && imageline($area, 0, (int) ($cy * $h * $s), $w * $s, (int) ($cy * $h * $s), $line);
                }
            }
        }
        if ($radius > 0) {
            // Round the corners by painting them in the background colour.
            $bg = imagecolorallocate($area, 10, 29, 63);
            $r = $radius * $s;
            foreach ([[0, 0, $r, $r], [$w * $s - $r, 0, $w * $s - $r, $r], [0, $h * $s - $r, $r, $h * $s - $r], [$w * $s - $r, $h * $s - $r, $w * $s - $r, $h * $s - $r]] as [$px, $py, $ex, $ey]) {
                for ($yy = 0; $yy < $r; $yy++) {
                    for ($xx = 0; $xx < $r; $xx++) {
                        $ax = $px + $xx;
                        $ay = $py + $yy;
                        if (hypot($ax - $ex, $ay - $ey) > $r) {
                            imagesetpixel($area, $ax, $ay, $bg);
                        }
                    }
                }
            }
        }
        imagecopy($img, $area, $x * $s, $y * $s, 0, 0, $w * $s, $h * $s);
    }

    private function cover(GdImage $dst, GdImage $src, int $x, int $y, int $w, int $h): void
    {
        $scale = max($w / imagesx($src), $h / imagesy($src));
        $sw = (int) round($w / $scale);
        $sh = (int) round($h / $scale);
        imagecopyresampled($dst, $src, $x, $y, (int) ((imagesx($src) - $sw) / 2), (int) ((imagesy($src) - $sh) / 2), $w, $h, $sw, $sh);
    }

    private function badge(GdImage $img, string $text, int $x, int $y, int $size = 34): void
    {
        // Anton has the naira sign; Poppins doesn't.
        $file = $this->font('Anton-Regular.ttf');
        $box = imagettfbbox($size * self::S, 0, $file, $text);
        $tw = (int) (($box[2] - $box[0]) / self::S);
        $h = (int) ($size * 1.75);
        $this->rounded($img, $x, $y, min($tw + 48, 900), $h, (int) ($h / 2), self::GOLD);
        $this->text($img, $text, 'Anton-Regular.ttf', $size, $x + 24, $y + (int) ($h * 0.72), self::NAVY, 852);
    }

    private function text(GdImage $img, string $text, string $font, float $size, int $x, int $y, string $hex, ?int $maxWidth = null): void
    {
        $file = $this->font($font);
        if ($maxWidth) {
            $box = imagettfbbox($size, 0, $file, $text);
            if ($maxWidth < $box[2] - $box[0]) {
                $text = Str::limit($text, max(4, (int) (mb_strlen($text) * $maxWidth / ($box[2] - $box[0])) - 2));
            }
        }
        imagettftext($img, $size * self::S, 0, $x * self::S, $y * self::S, $this->colour($img, $hex), $file, $text);
    }

    /**
     * Wrapped to at most $max lines; if it doesn't all fit, the last line ends with "…".
     *
     * @return list<string>
     */
    private function lines(string $text, string $font, float $size, int $width, int $max): array
    {
        $lines = $this->wrap($text, $font, $size, $width);
        if (count($lines) <= $max) {
            return $lines;
        }
        $lines = array_slice($lines, 0, $max);
        $last = $lines[$max - 1].'…';
        while (mb_strlen($last) > 2 && ($b = imagettfbbox($size, 0, $this->font($font), $last))[2] - $b[0] > $width) {
            $last = rtrim(mb_substr($last, 0, -2)).'…';
        }
        $lines[$max - 1] = $last;

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function wrap(string $text, string $font, float $size, int $width): array
    {
        $lines = [];
        $line = '';
        foreach (preg_split('/\s+/', trim($text)) ?: [] as $word) {
            $try = $line === '' ? $word : $line.' '.$word;
            $box = imagettfbbox($size, 0, $this->font($font), $try);
            if ($width < $box[2] - $box[0] && $line !== '') {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $try;
            }
        }
        $lines = $line === '' ? $lines : [...$lines, $line];

        return $lines;
    }

    private function shortUrl(string $url): string
    {
        return Str::limit(preg_replace('#^https?://(www\.)?#', '', $url), 44);
    }

    private function load(string $path): ?GdImage
    {
        if (! Storage::disk('public')->exists($path)) {
            return null;
        }
        $image = @imagecreatefromstring(Storage::disk('public')->get($path));

        return $image instanceof GdImage ? $image : null;
    }

    private function fill(GdImage $img, int $x, int $y, int $w, int $h, string $hex): void
    {
        imagefilledrectangle($img, $x * self::S, $y * self::S, ($x + $w) * self::S, ($y + $h) * self::S, $this->colour($img, $hex));
    }

    private function rounded(GdImage $img, int $x, int $y, int $w, int $h, int $r, string $hex): void
    {
        $s = self::S;
        $c = $this->colour($img, $hex);
        [$x, $y, $w, $h, $r] = [$x * $s, $y * $s, $w * $s, $h * $s, min($r * $s, (int) ($h * $s / 2), (int) ($w * $s / 2))];
        imagefilledrectangle($img, $x + $r, $y, $x + $w - $r, $y + $h, $c);
        imagefilledrectangle($img, $x, $y + $r, $x + $w, $y + $h - $r, $c);
        foreach ([[$x + $r, $y + $r], [$x + $w - $r, $y + $r], [$x + $r, $y + $h - $r], [$x + $w - $r, $y + $h - $r]] as [$cx, $cy]) {
            imagefilledellipse($img, $cx, $cy, $r * 2, $r * 2, $c);
        }
    }

    private function circle(GdImage $img, int $x, int $y, int $d, string $hex): void
    {
        imagefilledellipse($img, $x * self::S, $y * self::S, $d * self::S, $d * self::S, $this->colour($img, $hex));
    }

    /**
     * @param  list<string>  $stops
     */
    private function gradient(GdImage $img, array $stops): void
    {
        $h = imagesy($img);
        $rgb = array_map(fn ($hex) => sscanf(ltrim($hex, '#'), '%02x%02x%02x'), $stops);
        $n = count($rgb) - 1;
        for ($y = 0; $y < $h; $y++) {
            $f = $y / $h * $n;
            $i = min((int) $f, $n - 1);
            $p = $f - $i;
            imageline($img, 0, $y, imagesx($img), $y, imagecolorallocate($img, ...array_map(fn ($a, $b) => (int) round($a + ($b - $a) * $p), $rgb[$i], $rgb[$i + 1])));
        }
    }

    private function colour(GdImage $img, string $hex, int $alpha = 0): int
    {
        [$r, $g, $b] = sscanf(ltrim($hex, '#'), '%02x%02x%02x');

        return imagecolorallocatealpha($img, $r, $g, $b, $alpha);
    }

    private function font(string $file): string
    {
        return resource_path('fonts/'.$file);
    }
}
