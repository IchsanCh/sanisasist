<?php

namespace App\AI\Services;

use App\AI\DTO\ChatRequest;
use App\AI\DTO\ChatResponse;
use App\AI\Providers\AIProviderInterface;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * StreamingService
 *
 * Wrap AI provider stream() generator ke Laravel StreamedResponse (SSE).
 * Dipanggil oleh ChatMessageController::stream().
 *
 * Format SSE yang dikirim ke frontend:
 *   data: {"type":"delta","content":"..."}\n\n   ← token per token
 *   data: {"type":"done","message":{...}}\n\n    ← selesai + full message
 *   data: {"type":"error","message":"..."}\n\n   ← jika gagal
 */
class StreamingService
{
    public function __construct(
        private readonly AIProviderInterface  $provider,
        private readonly TokenUsageService    $tokenUsageService,
    ) {
    }

    public function stream(
        ChatSession  $session,
        ChatMessage  $userMessage,
        ChatRequest  $request,
    ): StreamedResponse {
        return new StreamedResponse(function () use ($session, $userMessage, $request) {

            // Disable output buffering supaya SSE langsung ter-flush
            if (ob_get_level()) {
                ob_end_clean();
            }

            $fullContent   = '';
            $assistantMessage = null;

            try {
                // ── Streaming dari provider ───────────────────────────
                foreach ($this->provider->stream($request) as $chunk) {
                    $fullContent .= $chunk;

                    $this->sendEvent([
                        'type'    => 'delta',
                        'content' => $chunk,
                    ]);
                }

                // ── Simpan assistant message setelah stream selesai ───
                $assistantMessage = ChatMessage::create([
                    'session_id' => $session->id,
                    'role'       => 'assistant',
                    'content'    => $fullContent,
                ]);

                // Token usage untuk streaming — estimasi kasar
                // (tidak semua provider kirim token count di stream)
                $estimatedTokens = (int) (str_word_count($fullContent) * 1.3);
                $assistantMessage->update([
                    'output_tokens' => $estimatedTokens,
                    'model_used'    => $request->model,
                    'provider_used' => $this->provider->getName(),
                ]);

                $session->touch();

                // Cek summarization
                $session->refresh();
                if ($session->needsSummarization()) {
                    \App\Jobs\SummarizeConversationJob::dispatch($session->id);
                }

                // ── Kirim event done ──────────────────────────────────
                $this->sendEvent([
                    'type'    => 'done',
                    'message' => [
                        'id'      => $assistantMessage->id,
                        'role'    => 'assistant',
                        'content' => $fullContent,
                        'tokens'  => $assistantMessage->tokens,
                    ],
                ]);

            } catch (\Throwable $e) {
                $this->sendEvent([
                    'type'    => 'error',
                    'message' => 'Streaming failed. Please try again.',
                ]);

                \Illuminate\Support\Facades\Log::error('StreamingService error', [
                    'session_id' => $session->id,
                    'error'      => $e->getMessage(),
                ]);
            }

        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no', // penting untuk nginx
        ]);
    }

    private function sendEvent(array $data): void
    {
        echo 'data: ' . json_encode($data) . "\n\n";
        flush();
    }
}
