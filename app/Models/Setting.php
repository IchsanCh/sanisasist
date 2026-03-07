<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Setting Model
 *
 * Key-value store untuk konfigurasi aplikasi dan per-user.
 * Mendukung tipe: string, boolean, integer, json, encrypted.
 *
 * Usage:
 *   Setting::get('active_provider')           // global setting
 *   Setting::get('active_provider', 'gemini') // dengan default
 *   Setting::set('active_provider', 'openai') // simpan global
 *   Setting::get('theme', 'dark', $userId)    // user-specific
 *
 * @property int         $id
 * @property int|null    $user_id
 * @property string      $key
 * @property string|null $value
 * @property string      $type
 */
class Setting extends Model
{
    protected $fillable = ['user_id', 'key', 'value', 'type'];

    // ── Cache TTL ────────────────────────────────────────────────────
    private const CACHE_TTL = 3600; // 1 hour

    // ── Relationships ─────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Static Helpers ────────────────────────────────────────────────

    /**
     * Get a setting value, with optional default fallback.
     * Automatically casts value based on 'type' column.
     * Encrypted values are decrypted transparently.
     */
    public static function get(string $key, mixed $default = null, ?int $userId = null): mixed
    {
        $cacheKey = self::cacheKey($key, $userId);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($key, $default, $userId) {
            $setting = static::query()
                ->where('key', $key)
                ->where('user_id', $userId)
                ->first();

            if (! $setting) {
                return $default;
            }

            return self::castValue($setting->value, $setting->type);
        });
    }

    /**
     * Set a setting value. Creates or updates the record.
     * Encrypted values are automatically encrypted before storage.
     */
    public static function set(string $key, mixed $value, ?int $userId = null, string $type = 'string'): void
    {
        // Auto-detect type if not specified
        if ($type === 'string') {
            $type = match (true) {
                is_bool($value) => 'boolean',
                is_int($value)  => 'integer',
                is_array($value) => 'json',
                default          => 'string',
            };
        }

        $storedValue = match ($type) {
            'encrypted' => Crypt::encryptString((string) $value),
            'boolean'   => $value ? 'true' : 'false',
            'json'      => json_encode($value),
            default     => (string) $value,
        };

        static::updateOrCreate(
            ['user_id' => $userId, 'key' => $key],
            ['value' => $storedValue, 'type' => $type]
        );

        // Bust cache so next read gets fresh value
        Cache::forget(self::cacheKey($key, $userId));
    }

    /**
     * Delete a setting.
     */
    public static function remove(string $key, ?int $userId = null): void
    {
        static::query()
            ->where('key', $key)
            ->where('user_id', $userId)
            ->delete();

        Cache::forget(self::cacheKey($key, $userId));
    }

    /**
     * Check if a setting exists.
     */
    public static function has(string $key, ?int $userId = null): bool
    {
        return static::query()
            ->where('key', $key)
            ->where('user_id', $userId)
            ->exists();
    }

    // ── Private Helpers ───────────────────────────────────────────────

    private static function castValue(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean'   => in_array(strtolower($value), ['true', '1', 'yes'], true),
            'integer'   => (int) $value,
            'json'      => json_decode($value, true),
            'encrypted' => self::safeDecrypt($value),
            default     => $value,
        };
    }

    private static function safeDecrypt(string $value): ?string
    {
        try {
            return Crypt::decryptString($value);
        } catch (\Exception) {
            // Value was stored un-encrypted (migration period) or corrupted
            return null;
        }
    }

    private static function cacheKey(string $key, ?int $userId): string
    {
        return 'setting:' . ($userId ?? 'global') . ':' . $key;
    }
}
