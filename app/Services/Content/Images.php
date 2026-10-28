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

    private function save(GdImage $image, string $path, int $quality): string
    {
        ob_start();
        imagewebp($image, null, $quality);
        Storage::disk('public')->put($path, (string) ob_get_clean());

        return $path;
    }
}
