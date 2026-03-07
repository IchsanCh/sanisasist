<?php

namespace App\Http\Controllers\Chat;

use App\AI\Services\ChatService;
use App\AI\Services\MessageBuilder;
use App\AI\Services\StreamingService;
use App\AI\DTO\ChatRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\SendMessageRequest;
use App\Models\AiModel;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatMessageController extends Controller
{
    public function __construct(
        private readonly ChatService     $chatService,
        private readonly StreamingService $streamingService,
        private readonly MessageBuilder  $messageBuilder,
    ) {
    }

    /**
     * Kirim pesan (non-streaming).
     * Dipanggil jika streaming_enabled = false, atau fallback.
     */
    public function store(SendMessageRequest $request, ChatSession $session): JsonResponse
    {
        $result = $this->chatService->send(
            sessionId: $session->id,
            content:   $request->input('message'),
            userId:    $request->user()->id,
        );

        return response()->json([
            'user_message' => [
                'id'      => $result['user_message']->id,
                'role'    => 'user',
                'content' => $result['user_message']->content,
            ],
            'assistant_message' => [
                'id'      => $result['assistant_message']->id,
                'role'    => 'assistant',
                'content' => $result['assistant_message']->content,
                'tokens'  => $result['assistant_message']->tokens,
                'model'   => $result['assistant_message']->model_used,
            ],
            'session' => [
                'id'    => $session->id,
                'title' => $session->fresh()->title,
            ],
        ]);
    }

    /**
     * Kirim pesan via SSE streaming.
     * Frontend connect ke endpoint ini dengan EventSource.
     */
    public function stream(SendMessageRequest $request, ChatSession $session): StreamedResponse
    {
        $content = $request->input('message');
        $user    = $request->user();

        // Simpan user message dulu sebelum stream dimulai
        $userMessage = ChatMessage::create([
            'session_id' => $session->id,
            'role'       => 'user',
            'content'    => $content,
        ]);

        // Auto-title
        if ($session->title === 'New Chat') {
            $session->update([
                'title' => $session->generateTitleFromMessage($content),
            ]);
        }

        // Build context
        $model   = AiModel::getActiveModel();
        $context = $this->messageBuilder->build($session, $content, $model);

        $chatRequest = new ChatRequest(
            model:        $model?->model_key ?? Setting::get('active_model', 'gemini-2.5-flash'),
            messages:     $context['messages'],
            systemPrompt: $context['system_prompt'],
            tools:        $context['tools'],
            stream:       true,
            sessionId:    $session->id,
            userId:       $user->id,
        );

        return $this->streamingService->stream($session, $userMessage, $chatRequest);
    }
}
