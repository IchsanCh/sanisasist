<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ToolCall Model
 *
 * Audit log of every AI tool invocation.
 * Stores input, output, duration, and success status.
 *
 * @property int         $id
 * @property int         $message_id
 * @property int         $tool_id
 * @property array       $input
 * @property array|null  $output
 * @property int|null    $duration_ms
 * @property bool        $success
 * @property string|null $error
 */
class ToolCall extends Model
{
    protected $fillable = [
        'message_id',
        'tool_id',
        'input',
        'output',
        'duration_ms',
        'success',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'input'       => 'array',
            'output'      => 'array',
            'duration_ms' => 'integer',
            'success'     => 'boolean',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────

    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'message_id');
    }

    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class);
    }
}
