<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
     */
    public static function findByProviderAndKey(string $providerSlug, string $modelKey): ?self
    {
        return static::whereHas('provider', fn ($q) => $q->where('slug', $providerSlug))
                     ->where('model_key', $modelKey)
                     ->first();
    }

    /**
     * Get the currently active model from settings.
     * Fallback default diupdate ke gemini-2.5-flash (bukan 2.0 yang sudah retired).
     */
    public static function getActiveModel(?int $userId = null): ?self
    {
        $providerSlug = Setting::get('active_provider', 'gemini', $userId);
        $modelKey     = Setting::get('active_model', 'gemini-2.5-flash', $userId);

        $model = static::findByProviderAndKey($providerSlug, $modelKey);

        // Fallback: kalau model di setting tidak ketemu di DB,
        // ambil model pertama dari provider yang aktif
        if (! $model) {
            $model = static::whereHas('provider', fn ($q) => $q->where('slug', $providerSlug))
                           ->orderBy('sort_order')
                           ->first();
        }

        return $model;
    }

    /**
     * Display-friendly label: "Gemini 2.5 Flash (Google Gemini)"
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->name} ({$this->provider->name})";
    }
}
