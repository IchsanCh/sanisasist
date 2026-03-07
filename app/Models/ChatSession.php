<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ChatSession Model
 *
 * Represents a single conversation thread.
 * Key fields:
 *   - summary: AI-generated rolling summary (updated every N messages)
 *   - metadata: extensible JSON for pinning, tags, system prompt overrides, etc.
 *
 * @property int         $id
 * @property int         $user_id
 * @property int|null    $model_id
 * @property string      $title
 * @property string|null $summary
 * @property array|null  $metadata
 */
class ChatSession extends Model
{
    protected $fillable = [
        'user_id',
        'model_id',
        'title',
        'summary',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(AiModel::class, 'model_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'session_id')
                    ->orderBy('created_at');
    }

    public function files(): HasMany
    {
        return $this->hasMany(File::class, 'session_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────

    /** Sidebar: load user's sessions newest first */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId)
                     ->orderByDesc('updated_at');
    }

    // ── Helpers ───────────────────────────────────────────────────────

    /**
     * Count only user + assistant messages (excludes system).
     * Used to determine when to trigger summarization.
     */
    public function countConversationMessages(): int
    {
        return $this->messages()
                    ->whereIn('role', ['user', 'assistant'])
                    ->count();
    }

    /**
     * Get the last N messages for context window.
     * Returns in chronological order (oldest first).
     */
    public function getLastMessages(int $count = 5): \Illuminate\Database\Eloquent\Collection
    {
        return $this->messages()
                    ->whereIn('role', ['user', 'assistant'])
                    ->orderByDesc('created_at')
                    ->limit($count)
                    ->get()
                    ->reverse()
                    ->values();
    }

    /**
     * Check if this session needs summarization.
     */
    public function needsSummarization(): bool
    {
        $triggerCount = (int) Setting::get('summary_trigger_count', 10);
        $count = $this->countConversationMessages();

        return $count > 0 && $count % $triggerCount === 0;
    }

    /**
     * Auto-generate title from first user message.
     * Truncates to 60 chars.
     */
    public function generateTitleFromMessage(string $messageContent): string
    {
        $title = trim(preg_replace('/\s+/', ' ', $messageContent));
        return strlen($title) > 60
            ? substr($title, 0, 57) . '...'
            : $title;
    }

    /**
     * Update summary and touch updated_at to push session to top of sidebar.
     */
    public function updateSummary(string $summary): void
    {
        $this->update(['summary' => $summary]);
    }
}
