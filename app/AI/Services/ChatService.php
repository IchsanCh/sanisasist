<?php

namespace App\AI\Services;

use App\AI\DTO\ChatRequest;
use App\AI\DTO\ChatResponse;
use App\AI\Providers\AIProviderInterface;
use App\Jobs\SummarizeConversationJob;
use App\Models\AiModel;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ChatService
 *
 * Orchestrator utama untuk semua chat interactions.
 * Dipanggil oleh ChatMessageController.
 *
 * Flow:
 *   1. Load/create session
 *   2. Simpan user message
 *   3. Auto-generate title jika masih "New Chat"
 *   4. Build context window via MessageBuilder
 *   5. Kirim ke AI provider
 *   6. Simpan assistant response + token usage
 *   7. Dispatch summarization job jika perlu
 *   8. Return ChatResponse
 */
class ChatService
{
    public function __construct(
        private readonly AIProviderInterface $provider,
        private readonly MessageBuilder      $messageBuilder,
        private readonly TokenUsageService   $tokenUsageService,
    ) {
    }

    /**
     * Proses pesan user dan kembalikan response AI.
     *
     * @param  int    $sessionId  ID sesi yang aktif
     * @param  string $content    Isi pesan dari user
     * @param  int    $userId     ID user yang mengirim
     * @return array{user_message: ChatMessage, assistant_message: ChatMessage, response: ChatResponse}
     */
    public function send(int $sessionId, string $content, int $userId): array
    {
        $session = ChatSession::findOrFail($sessionId);

        // Pastikan session milik user yang benar
        abort_if($session->user_id !== $userId, 403, 'Unauthorized');

        return DB::transaction(function () use ($session, $content, $userId) {

            // ── 1. Simpan user message ────────────────────────────────
            $userMessage = ChatMessage::create([
                'session_id' => $session->id,
                'role'       => 'user',
                'content'    => $content,
            ]);

            // ── 2. Auto-generate title dari pesan pertama ─────────────
            if ($session->title === 'New Chat') {
                $session->update([
                    'title' => $session->generateTitleFromMessage($content),
                ]);
            }

            // ── 3. Resolve active model ───────────────────────────────
            $model = AiModel::getActiveModel();

            // ── 4. Build context window ───────────────────────────────
            $context = $this->messageBuilder->build($session, $content, $model);

            // ── 5. Buat ChatRequest DTO ───────────────────────────────
            $chatRequest = new ChatRequest(
                model:        $model?->model_key ?? Setting::get('active_model', 'gemini-1.5-flash'),
                messages:     $context['messages'],
                systemPrompt: $context['system_prompt'],
                tools:        $context['tools'],
                stream:       false,
                sessionId:    $session->id,
                userId:       $session->user_id,
            );

            // ── 6. Kirim ke AI provider ───────────────────────────────
            $response = $this->callProvider($chatRequest);

            // ── 7. Simpan assistant message ───────────────────────────
            $assistantMessage = ChatMessage::create([
                'session_id' => $session->id,
                'role'       => 'assistant',
                'content'    => $response->content,
            ]);

            // ── 8. Record token usage ─────────────────────────────────
            $this->tokenUsageService->record($assistantMessage, $response);

            // ── 9. Touch session updated_at (push ke atas sidebar) ────
            $session->touch();

            // ── 10. Dispatch summarization job jika perlu ─────────────
            // Di-refresh dulu supaya countConversationMessages() akurat
            $session->refresh();
            if ($session->needsSummarization()) {
                SummarizeConversationJob::dispatch($session->id)
                    ->onQueue('default');
            }

            return [
                'user_message'      => $userMessage,
                'assistant_message' => $assistantMessage->fresh(), // fresh() untuk ambil tokens yg sudah di-update
                'response'          => $response,
            ];
        });
    }

    /**
     * Kirim ke provider dengan error handling.
     * Jika provider gagal, kembalikan error response
     * daripada throw exception ke user.
     */
    private function callProvider(ChatRequest $request): ChatResponse
    {
        try {
            return $this->provider->sendMessage($request);
        } catch (\Throwable $e) {
            Log::error('ChatService: Provider call failed', [
                'provider'  => $this->provider->getName(),
                'model'     => $request->model,
                'session'   => $request->sessionId,
                'error'     => $e->getMessage(),
            ]);

            return ChatResponse::error(
                "Sorry, I'm having trouble connecting to the AI service. Please try again.",
                $this->provider->getName()
            );
        }
    }
}
