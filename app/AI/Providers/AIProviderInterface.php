<?php

namespace App\AI\Providers;

use App\AI\DTO\ChatRequest;
use App\AI\DTO\ChatResponse;
use Generator;

/**
 * AIProviderInterface
 *
 * Kontrak yang harus diimplementasikan oleh semua AI provider.
 * ChatService hanya berinteraksi dengan interface ini —
 * tidak pernah langsung ke GeminiProvider, OpenAIProvider, dsb.
 *
 * Ini memungkinkan:
 *   - Swap provider tanpa ubah ChatService
 *   - Testing dengan MockProvider
 *   - Tambah provider baru tanpa sentuh logic lain
 */
interface AIProviderInterface
{
    /**
     * Kirim pesan ke AI dan tunggu response penuh.
     * Digunakan ketika streaming dinonaktifkan.
     */
    public function sendMessage(ChatRequest $request): ChatResponse;

    /**
     * Kirim pesan ke AI dan stream response token per token.
     * Yield string chunk — dikonsumsi oleh StreamingService.
     * Implementasi harus yield string kosong ('') jika provider
     * tidak support streaming, bukan throw exception.
     *
     * @return Generator<string>
     */
    public function stream(ChatRequest $request): Generator;

    /**
     * Apakah provider ini support tool/function calling.
     * Digunakan oleh MessageBuilder untuk memutuskan
     * apakah perlu menyertakan tool definitions.
     */
    public function supportsTools(): bool;

    /**
     * Apakah provider ini support streaming.
     */
    public function supportsStreaming(): bool;

    /**
     * Nama provider, e.g. "gemini", "openai", "claude".
     * Harus match dengan slug di tabel ai_providers.
     */
    public function getName(): string;
}
