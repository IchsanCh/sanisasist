<?php

namespace App\Providers;

use App\AI\Providers\AIProviderInterface;
use App\AI\Providers\ClaudeProvider;
use App\AI\Providers\GeminiProvider;
use App\AI\Providers\OpenAIProvider;
use App\Models\AiProvider;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * AIServiceProvider
 *
 * Melakukan binding AIProviderInterface ke implementasi konkret
 * berdasarkan setting "active_provider" di database.
 *
 * Binding bersifat scoped (per-request) — bukan singleton —
 * supaya perubahan setting langsung berlaku di request berikutnya
 * tanpa perlu restart server.
 *
 * Register di bootstrap/providers.php:
 *   App\Providers\AIServiceProvider::class,
 */
class AIServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AIProviderInterface::class, function (): AIProviderInterface {
            return $this->resolveProvider();
        });
    }

    public function boot(): void
    {
        //
    }

    // ── Resolution Logic ──────────────────────────────────────────────

    private function resolveProvider(): AIProviderInterface
    {
        // Ambil slug provider aktif dari settings table
        // Fallback ke 'gemini' jika belum diset
        $slug = Setting::get('active_provider', 'gemini');

        // Load provider record dari DB untuk mendapatkan base_url
        $providerRecord = AiProvider::findBySlug($slug);

        // Ambil API key dari settings (sudah di-decrypt oleh Setting model)
        $apiKey  = Setting::get("{$slug}_api_key");
        $baseUrl = $providerRecord?->base_url;

        return match ($slug) {
            'openai' => $this->makeOpenAI($apiKey, $baseUrl),
            'claude' => $this->makeClaude($apiKey, $baseUrl),
            default  => $this->makeGemini($apiKey, $baseUrl),
        };
    }

    // ── Provider Factories ────────────────────────────────────────────

    private function makeGemini(?string $apiKey, ?string $baseUrl): GeminiProvider
    {
        if (empty($apiKey)) {
            Log::warning('AIServiceProvider: Gemini API key is not configured.');
        }

        return new GeminiProvider(
            apiKey:  $apiKey  ?? '',
            baseUrl: $baseUrl ?? 'https://generativelanguage.googleapis.com/v1beta',
        );
    }

    private function makeOpenAI(?string $apiKey, ?string $baseUrl): OpenAIProvider
    {
        if (empty($apiKey)) {
            Log::warning('AIServiceProvider: OpenAI API key is not configured.');
        }

        return new OpenAIProvider(
            apiKey:  $apiKey  ?? '',
            baseUrl: $baseUrl ?? 'https://api.openai.com/v1',
        );
    }

    private function makeClaude(?string $apiKey, ?string $baseUrl): ClaudeProvider
    {
        if (empty($apiKey)) {
            Log::warning('AIServiceProvider: Claude API key is not configured.');
        }

        return new ClaudeProvider(
            apiKey:  $apiKey  ?? '',
            baseUrl: $baseUrl ?? 'https://api.anthropic.com/v1',
        );
    }
}
