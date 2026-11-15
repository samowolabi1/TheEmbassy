<?php

namespace App\Services\Content;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Saves uploaded photos for the website: turned upright, scaled down, re-encoded as WebP
 * (which drops EXIF data such as GPS location), with a small copy for grids.
 */
class Images
{
    /** Refuse pictures bigger than this many pixels before decoding them (about 50 MP). */
    private const MAX_PIXELS = 50_000_000;

    /**
     * @return array{path: string, thumb_path: string, width: int, height: int}
     */
    public function store(UploadedFile $file, string $folder, int $maxWidth = 2000, int $thumbWidth = 640): array
    {
        $image = $this->load($file);
        $full = $this->scale($image, $maxWidth);
        $thumb = $this->scale($image, $thumbWidth);
        $name = $folder.'/'.now()->format('Y/m').'/'.Str::random(32);

        return [
            'path' => $this->save($full, $name.'.webp', 82),
            'thumb_path' => $this->save($thumb, $name.'-thumb.webp', 76),
            'width' => imagesx($full),
            'height' => imagesy($full),
        ];
    }

    /**
     * Save only a small copy of a picture (for a grid) to the given disk; returns its path.
     */
    public function thumbnail(UploadedFile $file, string $folder, int $width = 480, string $disk = 'public'): string
    {
        $name = $folder.'/'.now()->format('Y/m').'/'.Str::random(32);

        return $this->save($this->scale($this->load($file), $width), $name.'-thumb.webp', 76, $disk);
    }

    /**
     * Remove saved files (any of them may be null).
     */
    public function delete(?string ...$paths): void
    {
        $paths = array_filter($paths);
        if ($paths !== []) {
            Storage::disk('public')->delete($paths);
        }
    }

    private function load(UploadedFile $file): GdImage
    {
        $info = @getimagesize($file->getRealPath());
        if ($info === false || $info[0] * $info[1] > self::MAX_PIXELS) {
            throw new RuntimeException('That picture could not be read, or it is too large.');
        }
        $this->ensureMemory($info[0], $info[1]);

        $image = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($file->getRealPath()),
            IMAGETYPE_PNG => @imagecreatefrompng($file->getRealPath()),
            IMAGETYPE_WEBP => @imagecreatefromwebp($file->getRealPath()),
            default => false,
        };
        if (! $image instanceof GdImage) {
            throw new RuntimeException('That picture could not be read. Use a JPG, PNG or WebP.');
        }

        if ($info[2] === IMAGETYPE_JPEG) {
            $image = $this->upright($image, $file->getRealPath());
        }
        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }
        imagealphablending($image, false);
        imagesavealpha($image, true);

        return $image;
    }

    /**
     * Phones save photos sideways and record the turn in EXIF; apply it before EXIF is dropped.
     */
    private function upright(GdImage $image, string $path): GdImage
    {
        $orientation = (int) ((function_exists('exif_read_data') ? @exif_read_data($path) : [])['Orientation'] ?? 1);

        $turned = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => null,
        };

        return $turned instanceof GdImage ? $turned : $image;
    }

    private function scale(GdImage $image, int $maxWidth): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        // Tall portraits are limited by height too, so they don't become huge.
        $ratio = min(1, $maxWidth / $width, ($maxWidth * 1.5) / $height);
        $scaled = imagescale($image, max(1, (int) round($width * $ratio)), max(1, (int) round($height * $ratio)), IMG_BICUBIC);
        if (! $scaled instanceof GdImage) {
            throw new RuntimeException('That picture could not be resized.');
        }
        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);

        return $scaled;
    }

    /**
     * WebP where the server's image library supports it (most do); JPEG otherwise, as on some
     * shared hosts. The file name's ending follows.
     */
    private function save(GdImage $image, string $path, int $quality, string $disk = 'public'): string
    {
        ob_start();
        if (self::webp()) {
            imagewebp($image, null, $quality);
        } else {
            $path = preg_replace('/\.webp$/', '.jpg', $path);
            imagejpeg($image, null, $quality + 4);
        }
        Storage::disk($disk)->put($path, (string) ob_get_clean());

        return $path;
    }

    public static function webp(): bool
    {
        return function_exists('imagewebp') && (gd_info()['WebP Support'] ?? false);
    }

    /**
     * The small copy's name for a saved picture: photo.webp → photo-thumb.webp (or .jpg).
     */
    public static function thumbPath(string $path): string
    {
        return preg_replace('/(\.[a-z0-9]+)$/i', '-thumb$1', $path, 1);
    }

    /**
     * Raise PHP's memory limit for big photos where the host allows it, and turn away pictures
     * that still wouldn't fit, instead of failing with a server error.
     */
    private function ensureMemory(int $width, int $height): void
    {
        $needed = (int) ($width * $height * 5.2) + 48 * 1024 * 1024;
        if (self::limit() !== -1 && self::limit() < $needed) {
            @ini_set('memory_limit', (string) max($needed, 512 * 1024 * 1024));
        }
        if (self::limit() !== -1 && self::limit() < $needed) {
            throw new RuntimeException('That picture is too big for this server to process. Please use a smaller one (under '.max(1, (int) floor((self::limit() - 48 * 1024 * 1024) / 5.2 / 1_000_000)).' megapixels, e.g. 4000 × 3000).');
        }
    }

    private static function limit(): int
    {
        $raw = trim((string) ini_get('memory_limit'));
        if ($raw === '' || $raw === '-1') {
            return -1;
        }
        $n = (int) $raw;

        return match (strtolower(substr($raw, -1))) {
            'g' => $n * 1024 ** 3,
            'm' => $n * 1024 ** 2,
            'k' => $n * 1024,
            default => $n,
        };
    }
}
