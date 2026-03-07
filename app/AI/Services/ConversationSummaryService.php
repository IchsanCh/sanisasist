<?php

namespace App\AI\Services;

use App\AI\DTO\ChatRequest;
use App\AI\Providers\AIProviderInterface;
use App\Models\ChatSession;
use Illuminate\Support\Facades\Log;

/**
 * ConversationSummaryService
 *
 * Generate ringkasan percakapan via AI dan simpan ke chat_sessions.summary.
 * Dipanggil oleh SummarizeConversationJob (async queue).
 *
 * Summary digunakan oleh MessageBuilder sebagai long-term memory —
 * inject ke context window setiap request untuk menjaga konteks
 * percakapan panjang tanpa harus kirim seluruh history.
 */
class ConversationSummaryService
{
    public function __construct(
        private readonly AIProviderInterface $provider,
    ) {
    }

    /**
     * Generate summary untuk satu session dan simpan ke DB.
     */
    public function summarize(ChatSession $session): void
    {
        $messages = $session->messages()
            ->conversational()
            ->orderBy('created_at')
            ->get();

        if ($messages->count() < 2) {
            return;
        }

        // Susun conversation history sebagai teks plain
        $history = $messages->map(function ($message) {
            $role = ucfirst($message->role);
            return "{$role}: {$message->content}";
        })->implode("\n\n");

        // Jika sudah ada summary sebelumnya, include sebagai context
        $existingSummary = '';
        if (! empty($session->summary)) {
            $existingSummary = "\n\nPrevious summary (update this with new information):\n{$session->summary}";
        }

        $summaryPrompt = <<<PROMPT
Please create a concise summary of the following conversation.

The summary should preserve:
- Key topics and questions discussed
- Important answers or information provided
- Any user preferences, context, or personal details mentioned
- Decisions or conclusions reached

Keep the summary under 400 words. Write in the same language as the conversation.{$existingSummary}

Conversation to summarize:
{$history}
PROMPT;

        try {
            $request = new ChatRequest(
                model:     $this->getActiveModelKey(),
                messages:  [['role' => 'user', 'content' => $summaryPrompt]],
                maxTokens: 600,
                sessionId: $session->id,
                userId:    $session->user_id,
            );

            $response = $this->provider->sendMessage($request);

            if (! empty($response->content) && $response->finishReason !== 'error') {
                $session->updateSummary($response->content);

                Log::info('ConversationSummaryService: Summary updated', [
                    'session_id'    => $session->id,
                    'message_count' => $messages->count(),
                    'tokens_used'   => $response->totalTokens(),
                ]);
            }

        } catch (\Throwable $e) {
            // Jangan sampai summarization failure mengganggu chat utama
            Log::error('ConversationSummaryService: Failed to summarize', [
                'session_id' => $session->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    private function getActiveModelKey(): string
    {
        return \App\Models\Setting::get('active_model', 'gemini-2.5-flash');
    }
}
