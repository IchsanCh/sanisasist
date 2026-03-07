<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * AiProvider Model
 *
 * Registry of AI providers: Gemini, OpenAI, Claude.
 * Each provider has many AiModels.
 *
 * @property int         $id
 * @property string      $name
 * @property string      $slug
 * @property string|null $base_url
 * @property bool        $active
 */
class AiProvider extends Model
{
    protected $fillable = ['name', 'slug', 'base_url', 'active'];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────

    public function models(): HasMany
    {
        return $this->hasMany(AiModel::class, 'provider_id')
                    ->orderBy('sort_order');
    }

    public function chatSessions(): HasManyThrough
    {
        // Through ai_models
        return $this->hasManyThrough(ChatSession::class, AiModel::class, 'provider_id', 'model_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    // ── Helpers ───────────────────────────────────────────────────────

    /**
     * Find provider by slug. Used in AIServiceProvider binding.
     */
    public static function findBySlug(string $slug): ?self
    {
        return static::where('slug', $slug)->first();
    }

    /**
     * Get the API key for this provider from settings.
     * Returns decrypted value or null if not set.
     */
    public function getApiKey(?int $userId = null): ?string
    {
        return Setting::get("{$this->slug}_api_key", null, $userId);
    }

    /**
     * Check if this provider has an API key configured.
     */
    public function hasApiKey(?int $userId = null): bool
    {
        return ! empty($this->getApiKey($userId));
    }
}
