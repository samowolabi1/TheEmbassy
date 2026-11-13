<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A file in the media unit's library, kept on the private disk and only handed out through the
 * app: photos, videos, designs, logos, documents, or a link to something stored elsewhere.
 */
#[Fillable(['media_folder_id', 'title', 'tags', 'description'])]
class MediaFile extends Model
{
    public const DISK = 'local';

    public const KINDS = [
        'image' => 'Photo or image',
        'video' => 'Video',
        'audio' => 'Audio',
        'design' => 'Design file',
        'document' => 'Document',
        'archive' => 'Zip',
        'link' => 'Link',
    ];

    /** What can be uploaded, by kind. */
    public const EXTENSIONS = [
        'image' => ['jpg', 'jpeg', 'png', 'webp', 'gif', 'heic'],
        'video' => ['mp4', 'mov', 'm4v', 'webm'],
        'audio' => ['mp3', 'wav', 'm4a', 'aac'],
        'design' => ['psd', 'ai', 'eps', 'svg', 'cdr', 'fig', 'xd', 'indd'],
        'document' => ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'key'],
        'archive' => ['zip'],
    ];

    /** Shown in the browser; everything else is only downloaded (an SVG can carry script). */
    public const INLINE = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'mp4', 'm4v', 'webm', 'mov', 'mp3', 'wav', 'm4a', 'aac', 'pdf'];

    /**
     * @return BelongsTo<MediaFolder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(MediaFolder::class, 'media_folder_id');
    }

    /**
     * @return BelongsTo<MediaRequest, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(MediaRequest::class, 'media_request_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public static function allowedExtensions(): array
    {
        return array_merge(...array_values(self::EXTENSIONS));
    }

    public static function kindFor(string $extension): string
    {
        foreach (self::EXTENSIONS as $kind => $extensions) {
            if (in_array(strtolower($extension), $extensions, true)) {
                return $kind;
            }
        }

        return 'document';
    }

    public function extension(): string
    {
        return strtolower(pathinfo((string) ($this->original_name ?? $this->path), PATHINFO_EXTENSION));
    }

    public function isLink(): bool
    {
        return $this->url !== null;
    }

    public function showsInline(): bool
    {
        return ! $this->isLink() && in_array($this->extension(), self::INLINE, true);
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? Str::headline($this->kind);
    }

    public function sizeLabel(): string
    {
        return self::bytes((int) $this->size);
    }

    public static function bytes(int $bytes): string
    {
        return match (true) {
            $bytes >= 1024 ** 3 => round($bytes / 1024 ** 3, 1).' GB',
            $bytes >= 1024 ** 2 => round($bytes / 1024 ** 2, 1).' MB',
            $bytes >= 1024 => round($bytes / 1024).' KB',
            default => $bytes.' bytes',
        };
    }

    /**
     * @return list<string>
     */
    public function tagList(): array
    {
        return collect(explode(',', (string) $this->tags))->map(fn ($t) => trim($t))->filter()->values()->all();
    }

    /**
     * Who may open it: the media team, and the person who asked for the request it belongs to.
     */
    public function isVisibleTo(User $user): bool
    {
        if ($user->inMediaTeam()) {
            return true;
        }

        return $this->purpose !== 'library' && $this->request && $this->request->requested_by === $user->id;
    }

    public function deleteStoredFiles(): void
    {
        Storage::disk(self::DISK)->delete(array_filter([$this->path, $this->thumb_path]));
    }
}
