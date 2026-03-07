<?php

namespace App\AI\Services;

use App\AI\DTO\ChatResponse;
use App\Models\ChatMessage;

/**
 * TokenUsageService
 *
 * Menyimpan token usage dari AI response ke chat_messages.
 * Simple service — sengaja dipisah dari ChatService
 * supaya mudah di-extend nanti (e.g. quota enforcement, analytics).
 */
class TokenUsageService
{
    /**
     * Update token usage pada assistant message yang sudah tersimpan.
     * Dipanggil setelah AI response diterima.
     */
    public function record(ChatMessage $message, ChatResponse $response): void
    {
        $message->update([
            'input_tokens'  => $response->inputTokens,
            'output_tokens' => $response->outputTokens,
            // tokens (total) dihitung otomatis via ChatMessage::booted()
            'model_used'    => $response->model,
            'provider_used' => $response->provider,
            'metadata'      => $response->toMetadata(),
        ]);
    }

    /**
     * Hitung total token usage untuk satu session.
     * Berguna untuk analytics page nanti.
     */
    public function getTotalForSession(int $sessionId): array
    {
        $result = ChatMessage::where('session_id', $sessionId)
            ->selectRaw('
                SUM(input_tokens)  as total_input,
                SUM(output_tokens) as total_output,
                SUM(tokens)        as total_tokens,
                COUNT(*)           as message_count
            ')
            ->first();

        return [
            'total_input'    => (int) $result->total_input,
            'total_output'   => (int) $result->total_output,
            'total_tokens'   => (int) $result->total_tokens,
            'message_count'  => (int) $result->message_count,
        ];
    }
}
