<?php

namespace App\AI\DTO;

/**
 * ChatRequest DTO
 *
 * Value object yang membawa semua data yang dibutuhkan
 * oleh AI provider untuk menghasilkan response.
 *
 * Dibuat oleh ChatService, dikonsumsi oleh Provider.
 * Immutable setelah konstruksi.
 */
readonly class ChatRequest
{
    /**
     * @param string       $model          Model key, e.g. "gemini-2.0-flash"
     * @param array        $messages       Array of ['role' => ..., 'content' => ...],
     *                                     sudah disusun oleh MessageBuilder
     *                                     (system prompt + summary + last N + user message baru)
     * @param string|null  $systemPrompt   Instruksi sistem untuk AI
     * @param array        $tools          Tool definitions (JSON Schema) jika ada
     * @param bool         $stream         Apakah request ini untuk streaming
     * @param int          $maxTokens      Batas maksimum output tokens
     * @param float        $temperature    Kreativitas (0.0 - 2.0, default 0.7)
     * @param int          $sessionId      ID sesi untuk reference di service
     * @param int          $userId         ID user untuk reference
     */
    public function __construct(
        public readonly string  $model,
        public readonly array   $messages,
        public readonly ?string $systemPrompt = null,
        public readonly array   $tools        = [],
        public readonly bool    $stream        = false,
        public readonly int     $maxTokens    = 2048,
        public readonly float   $temperature  = 0.7,
        public readonly int     $sessionId    = 0,
        public readonly int     $userId       = 0,
    ) {
    }

    /**
     * Cek apakah request ini membawa tool definitions.
     */
    public function hasTools(): bool
    {
        return ! empty($this->tools);
    }

    /**
     * Buat instance baru dengan override beberapa field.
     * Berguna untuk modifikasi minimal tanpa reconstruct semua.
     */
    public function with(array $overrides): self
    {
        return new self(
            model:        $overrides['model']        ?? $this->model,
            messages:     $overrides['messages']     ?? $this->messages,
            systemPrompt: $overrides['systemPrompt'] ?? $this->systemPrompt,
            tools:        $overrides['tools']        ?? $this->tools,
            stream:       $overrides['stream']       ?? $this->stream,
            maxTokens:    $overrides['maxTokens']    ?? $this->maxTokens,
            temperature:  $overrides['temperature']  ?? $this->temperature,
            sessionId:    $overrides['sessionId']    ?? $this->sessionId,
            userId:       $overrides['userId']       ?? $this->userId,
        );
    }
}
