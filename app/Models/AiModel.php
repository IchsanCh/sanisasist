<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * AiModel Model
 *
 * Represents a specific AI model from a provider.
 * Stores capability flags used by the UI and ChatService.
 *
 * @property int         $id
 * @property int         $provider_id
 * @property string      $name
 * @property string      $model_key
 * @property bool        $supports_image
 * @property bool        $supports_file
 * @property bool        $supports_tools
 * @property bool        $supports_streaming
 * @property int|null    $context_window
 * @property int         $sort_order
 */
class AiModel extends Model
{
    protected $fillable = [
        'provider_id',
        'name',
        'model_key',
        'supports_image',
        'supports_file',
        'supports_tools',
        'supports_streaming',
        'context_window',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'supports_image'     => 'boolean',
            'supports_file'      => 'boolean',
            'supports_tools'     => 'boolean',
            'supports_streaming' => 'boolean',
            'context_window'     => 'integer',
            'sort_order'         => 'integer',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'provider_id');
    }

    public function chatSessions(): HasMany
    {
        return $this->hasMany(ChatSession::class, 'model_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────

    public function scopeWithTools(Builder $query): Builder
    {
        return $query->where('supports_tools', true);
    }

    public function scopeWithStreaming(Builder $query): Builder
    {
        return $query->where('supports_streaming', true);
    }

    public function scopeWithImage(Builder $query): Builder
    {
        return $query->where('supports_image', true);
    }

    // ── Helpers ───────────────────────────────────────────────────────

    /**
     * Get model by provider slug + model_key.
     * Used in settings resolution.
     */
    public static function findByProviderAndKey(string $providerSlug, string $modelKey): ?self
    {
        return static::whereHas('provider', fn ($q) => $q->where('slug', $providerSlug))
                     ->where('model_key', $modelKey)
                     ->first();
    }

    /**
     * Get the currently active model from settings.
     */
    public static function getActiveModel(?int $userId = null): ?self
    {
        $providerSlug = Setting::get('active_provider', 'gemini', $userId);
        $modelKey     = Setting::get('active_model', 'gemini-2.0-flash', $userId);

        return static::findByProviderAndKey($providerSlug, $modelKey);
    }

    /**
     * Display-friendly label: "Gemini 2.0 Flash (Google Gemini)"
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->name} ({$this->provider->name})";
    }
}
