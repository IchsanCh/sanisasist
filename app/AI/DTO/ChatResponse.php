<?php

namespace App\AI\DTO;

/**
 * ChatResponse DTO
 *
 * Value object yang membawa hasil response dari AI provider.
 * Dibuat oleh Provider, dikonsumsi oleh ChatService.
 *
 * Menormalisasi perbedaan format response antar provider
 * ke satu struktur yang konsisten.
 */
readonly class ChatResponse
{
    /**
     * @param string      $content        Teks response dari AI
     * @param int         $inputTokens    Token yang dikonsumsi untuk input
     * @param int         $outputTokens   Token yang dihasilkan untuk output
     * @param string      $finishReason   stop | length | tool_calls | content_filter | error
     * @param string      $model          Model key yang benar-benar digunakan
     * @param string      $provider       Provider slug
     * @param array       $toolCalls      Tool calls jika AI meminta eksekusi tool
     *                                    Format: [['name' => ..., 'arguments' => [...]], ...]
     * @param array       $raw            Raw response dari provider (untuk debugging)
     */
    public function __construct(
        public readonly string $content,
        public readonly int    $inputTokens,
        public readonly int    $outputTokens,
        public readonly string $finishReason = 'stop',
        public readonly string $model        = '',
        public readonly string $provider     = '',
        public readonly array  $toolCalls    = [],
        public readonly array  $raw          = [],
    ) {
    }

    /**
     * Total token usage.
     */
    public function totalTokens(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }

    /**
     * Apakah AI meminta eksekusi tool.
     */
    public function hasToolCalls(): bool
    {
        return ! empty($this->toolCalls);
    }

    /**
     * Apakah response berhenti karena limit token (bukan selesai natural).
     */
    public function isTruncated(): bool
    {
        return $this->finishReason === 'length';
    }

    /**
     * Factory: buat response error yang aman dikembalikan ke user.
     */
    public static function error(string $message, string $provider = ''): self
    {
        return new self(
            content:      $message,
            inputTokens:  0,
            outputTokens: 0,
            finishReason: 'error',
            provider:     $provider,
        );
    }

    /**
     * Konversi ke array untuk disimpan di metadata chat_messages.
     */
    public function toMetadata(): array
    {
        return [
            'finish_reason' => $this->finishReason,
            'tool_calls'    => $this->toolCalls,
            'model'         => $this->model,
        ];
    }
}
