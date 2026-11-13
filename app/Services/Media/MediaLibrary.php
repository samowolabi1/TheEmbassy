<?php

namespace App\Services\Media;

use App\Models\MediaFile;
use App\Models\MediaRequest;
use App\Models\User;
use App\Services\Content\Images;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Throwable;

/**
 * Puts uploaded files on the private disk, with a small preview for pictures.
 */
class MediaLibrary
{
    public function __construct(private Images $images) {}

    /**
     * @param  array{media_folder_id?: ?int, tags?: ?string, description?: ?string}  $attributes
     */
    public function store(UploadedFile $file, User $by, string $purpose = 'library', ?MediaRequest $request = null, array $attributes = []): MediaFile
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: (string) $file->extension());
        $kind = MediaFile::kindFor($extension);
        $folder = 'media/'.($purpose === 'library' ? 'library' : 'requests').'/'.now()->format('Y/m');

        $media = new MediaFile([
            'media_folder_id' => $attributes['media_folder_id'] ?? null,
            'title' => Str::limit(trim(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'Untitled', 150, ''),
            'tags' => $attributes['tags'] ?? null,
            'description' => $attributes['description'] ?? null,
        ]);
        $media->forceFill([
            'purpose' => $purpose,
            'media_request_id' => $request?->id,
            'kind' => $kind,
            // Stored under a random name with its own extension; the original name is kept for downloads.
            'path' => $file->storeAs($folder, Str::random(40).'.'.$extension, MediaFile::DISK),
            'original_name' => Str::limit($file->getClientOriginalName(), 200, ''),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $by->id,
        ]);
        if ($kind === 'image') {
            try {
                $media->thumb_path = $this->images->thumbnail($file, 'media/thumbs', 480, MediaFile::DISK);
            } catch (Throwable) {
                // HEIC and some GIFs can't be read here; they show without a preview.
            }
        }
        $media->save();

        return $media;
    }

    /**
     * @param  array{media_folder_id?: ?int, tags?: ?string, description?: ?string}  $attributes
     */
    public function link(string $url, string $title, User $by, string $purpose = 'library', ?MediaRequest $request = null, array $attributes = []): MediaFile
    {
        $media = new MediaFile(['title' => $title, ...$attributes]);
        $media->forceFill([
            'purpose' => $purpose,
            'media_request_id' => $request?->id,
            'kind' => 'link',
            'url' => $url,
            'uploaded_by' => $by->id,
        ])->save();

        return $media;
    }

    public function delete(MediaFile $file): void
    {
        $file->deleteStoredFiles();
        $file->delete();
    }

    /**
     * Rules for an uploaded file. Checked by extension (design files have no reliable type);
     * files are stored under random names and only shown in the browser when they're safe to.
     *
     * @return list<string>
     */
    public static function fileRules(): array
    {
        return ['file', 'extensions:'.implode(',', MediaFile::allowedExtensions()), 'max:'.(int) config('church.media.max_upload_mb', 500) * 1024];
    }
}
