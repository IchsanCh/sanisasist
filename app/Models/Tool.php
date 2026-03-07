<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tool Model
 *
 * Registry of available AI tools (function calling).
 * The slug maps to a concrete PHP class in app/AI/Tools/.
 *
 * @property int    $id
 * @property string $name
 * @property string $slug
 * @property string $description
 * @property bool   $enabled
 */
class Tool extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'enabled'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────

    public function toolCalls(): HasMany
    {
        return $this->hasMany(ToolCall::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    // ── Helpers ───────────────────────────────────────────────────────

    /**
     * Get all enabled tools formatted as AI function definitions.
     * Structure matches OpenAI / Gemini tool schema.
     *
     * This is called by MessageBuilder before sending to AI provider.
     */
    public static function getEnabledDefinitions(): array
    {
        return static::enabled()
            ->get()
            ->map(fn (Tool $tool) => [
                'name'        => $tool->slug,
                'description' => $tool->description,
                // Parameters schema is provided by the concrete ToolInterface class.
                // MessageBuilder resolves the class and calls getParameters().
                'parameters'  => [],
            ])
            ->toArray();
    }
}
