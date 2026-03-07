<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * File Model
 *
 * Tracks uploaded files (images, documents).
 * Phase 2 feature — structure is ready but upload routes are not yet active.
 *
 * @property int         $id
 * @property int         $user_id
 * @property int|null    $session_id
 * @property string      $filename
 * @property string      $path
 * @property string      $mime_type
 * @property int         $size
 */
class File extends Model
{
    protected $fillable = [
        'user_id',
        'session_id',
        'filename',
        'path',
        'mime_type',
        'size',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class, 'session_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    public function getUrl(): string
    {
        return Storage::url($this->path);
    }

    public function getSizeForHumans(): string
    {
        $bytes = $this->size;
        if ($bytes < 1024) {
            return "{$bytes} B";
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return round($bytes / 1048576, 1) . ' MB';
    }
}
