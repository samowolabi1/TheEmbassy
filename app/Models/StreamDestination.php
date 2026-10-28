<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/**
 * Where the streaming software sends the service: a server URL and stream key (encrypted).
 */
#[Fillable(['platform', 'label', 'server_url', 'stream_key', 'is_active'])]
#[Hidden(['stream_key'])]
class StreamDestination extends Model
{
    public const PLATFORMS = ['youtube' => 'YouTube', 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'other' => 'Other (RTMP)'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stream_key' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }

    public function maskedKey(): string
    {
        $key = (string) $this->stream_key;

        return strlen($key) <= 4 ? '••••' : str_repeat('•', 8).substr($key, -4);
    }
}
