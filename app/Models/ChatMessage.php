<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ChatMessage Model
 *
 * A single message in a chat session.
 * Tracks token usage per message for cost monitoring and analytics.
 * Metadata JSON stores AI-specific data (finish_reason, tool_calls, etc.)
 *
 * @property int         $id
 * @property int         $session_id
 * @property string      $role         user | assistant | system
 * @property string      $content
 * @property int|null    $input_tokens
 * @property int|null    $output_tokens
 * @property int|null    $tokens
 * @property string|null $model_used
 * @property string|null $provider_used
 * @property array|null  $metadata
 */
class ChatMessage extends Model
{
    protected $fillable = [
        'session_id',
        'role',
        'content',
        'input_tokens',
        'output_tokens',
        'tokens',
        'model_used',
        'provider_used',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata'      => 'array',
            'input_tokens'  => 'integer',
            'output_tokens' => 'integer',
            'tokens'        => 'integer',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────

    public function session(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class, 'session_id');
    }

    public function toolCalls(): HasMany
    {
        return $this->hasMany(ToolCall::class, 'message_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────

    public function scopeByUser(Builder $query): Builder
    {
        return $query->where('role', 'user');
    }

    public function scopeByAssistant(Builder $query): Builder
    {
        return $query->where('role', 'assistant');
    }

    public function scopeConversational(Builder $query): Builder
    {
        // Excludes system messages — for context window building
        return $query->whereIn('role', ['user', 'assistant']);
    }

    // ── Helpers ───────────────────────────────────────────────────────

    public function isUser(): bool
    {
        return $this->role === 'user';
    }

    public function isAssistant(): bool
    {
        return $this->role === 'assistant';
    }

    public function isSystem(): bool
    {
        return $this->role === 'system';
    }

    public function hasToolCalls(): bool
    {
        return ! empty($this->metadata['tool_calls']);
    }

    /**
     * Compute and auto-fill the total token count before saving.
     */
    protected static function booted(): void
    {
        static::saving(function (ChatMessage $message) {
            if ($message->input_tokens !== null || $message->output_tokens !== null) {
                $message->tokens = ($message->input_tokens ?? 0) + ($message->output_tokens ?? 0);
            }
        });
    }

    /**
     * Format as array for AI context window.
     * This is the structure most AI APIs expect.
     */
    public function toContextArray(): array
    {
        return [
            'role'    => $this->role,
            'content' => $this->content,
        ];
    }
}
