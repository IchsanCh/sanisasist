<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use Notifiable;
    use TwoFactorAuthenticatable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'      => 'datetime',
            'password'               => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────

    public function chatSessions(): HasMany
    {
        return $this->hasMany(ChatSession::class)
                    ->orderByDesc('updated_at');
    }

    public function settings(): HasMany
    {
        return $this->hasMany(Setting::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(File::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────

    /**
     * Get a user-specific setting, falling back to global if not set.
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        // Try user-specific first
        $value = Setting::get($key, null, $this->id);

        // Fall back to global setting
        return $value ?? Setting::get($key, $default);
    }

    /**
     * Set a user-specific setting.
     */
    public function setSetting(string $key, mixed $value, string $type = 'string'): void
    {
        Setting::set($key, $value, $this->id, $type);
    }
}
