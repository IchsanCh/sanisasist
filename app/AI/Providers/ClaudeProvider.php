<?php

namespace App\AI\Providers;

use App\AI\DTO\ChatRequest;
use App\AI\DTO\ChatResponse;
use Generator;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

/**
 * ClaudeProvider
 *
 * Implementasi AIProviderInterface untuk Anthropic Claude Messages API.
 * Claude punya beberapa perbedaan penting dari OpenAI:
 *   - System prompt adalah top-level field, bukan message
 *   - Streaming pakai event-based SSE dengan event type
 *   - Tool use pakai content blocks, bukan message.tool_calls
 *   - Versi API wajib disertakan di header (anthropic-version)
 *
 * Docs: https://docs.anthropic.com/en/api/messages
 */
class ClaudeProvider implements AIProviderInterface
{
    private Client $client;

    // Anthropic API version — update ini jika ada breaking changes dari Anthropic
    private const API_VERSION = '2023-06-01';

    public function __construct(string $apiKey, string $baseUrl = 'https://api.anthropic.com/v1')
    {
        $this->client = new Client([
            'base_uri' => rtrim($baseUrl, '/'),
            'timeout'  => 120,
            'headers'  => [
                'Content-Type'      => 'application/json',
                'x-api-key'         => $apiKey,
                'anthropic-version' => self::API_VERSION,
            ],
        ]);
    }

    // ── Interface Implementation ──────────────────────────────────────

    public function getName(): string
    {
        return 'claude';
    }

    public function supportsTools(): bool
    {
        return true;
    }

    public function supportsStreaming(): bool
    {
        return true;
    }

    public function sendMessage(ChatRequest $request): ChatResponse
    {
        try {
            $payload  = $this->buildPayload($request);
            $response = $this->client->post('/messages', ['json' => $payload]);
            $data     = json_decode($response->getBody()->getContents(), true);

            return $this->parseResponse($data, $request->model);

        } catch (RequestException $e) {
            $message = $this->extractErrorMessage($e);
            Log::error('ClaudeProvider::sendMessage failed', [
                'error'   => $message,
                'model'   => $request->model,
                'session' => $request->sessionId,
            ]);

            return ChatResponse::error("Claude error: {$message}", $this->getName());
        }
    }

    public function stream(ChatRequest $request): Generator
    {
        try {
            $payload            = $this->buildPayload($request);
            $payload['stream']  = true;

            $response = $this->client->post('/messages', [
                'json'   => $payload,
                'stream' => true,
            ]);

            $body   = $response->getBody();
            $buffer = '';

            while (! $body->eof()) {
                $buffer .= $body->read(1024);
                $lines   = explode("\n", $buffer);
                $buffer  = array_pop($lines);

                $eventType = '';
                foreach ($lines as $line) {
                    $line = trim($line);

                    // Claude SSE format menggunakan "event:" lines sebelum "data:"
                    if (str_starts_with($line, 'event: ')) {
                        $eventType = substr($line, 7);
                        continue;
                    }

                    if (! str_starts_with($line, 'data: ')) {
                        continue;
                    }

                    $json = substr($line, 6);
                    $data = json_decode($json, true);

                    // Hanya proses content_block_delta events
                    if ($eventType === 'content_block_delta') {
                        $text = $data['delta']['text'] ?? '';
                        if ($text !== '') {
                            yield $text;
                        }
                    }

                    // message_stop menandakan stream selesai
                    if ($eventType === 'message_stop') {
                        return;
                    }
                }
            }

        } catch (RequestException $e) {
            Log::error('ClaudeProvider::stream failed', ['error' => $e->getMessage()]);
            yield 'Error: ' . $this->extractErrorMessage($e);
        }
    }

    // ── Payload Builder ───────────────────────────────────────────────

    /**
     * Claude Messages API format:
     * - system → top-level string field (bukan message)
     * - messages → hanya "user" dan "assistant" roles
     * - tools → array of tool definitions
     */
    private function buildPayload(ChatRequest $request): array
    {
        $messages = [];

        foreach ($request->messages as $message) {
            // Claude tidak menerima system role dalam messages array
            if ($message['role'] === 'system') {
                continue;
            }
            $messages[] = ['role' => $message['role'], 'content' => $message['content']];
        }

        $payload = [
            'model'      => $request->model,
            'max_tokens' => $request->maxTokens,
            'messages'   => $messages,
        ];

        // System prompt sebagai top-level field (bukan message)
        if ($request->systemPrompt) {
            $payload['system'] = $request->systemPrompt;
        }

        // Temperature (Claude mendukung 0.0 - 1.0)
        $payload['temperature'] = min($request->temperature, 1.0);

        // Tool definitions
        if ($request->hasTools()) {
            $payload['tools'] = $this->formatTools($request->tools);
        }

        return $payload;
    }

    // ── Response Parser ───────────────────────────────────────────────

    private function parseResponse(array $data, string $model): ChatResponse
    {
        $content   = '';
        $toolCalls = [];

        // Claude response berisi array of content blocks
        foreach ($data['content'] ?? [] as $block) {
            if ($block['type'] === 'text') {
                $content .= $block['text'];
            }

            // Tool use block
            if ($block['type'] === 'tool_use') {
                $toolCalls[] = [
                    'name'      => $block['name'],
                    'arguments' => $block['input'] ?? [],
                ];
            }
        }

        $finishReason = $this->mapFinishReason($data['stop_reason'] ?? 'end_turn');
        $usage        = $data['usage'] ?? [];

        return new ChatResponse(
            content:      $content,
            inputTokens:  $usage['input_tokens']  ?? 0,
            outputTokens: $usage['output_tokens'] ?? 0,
            finishReason: $finishReason,
            model:        $data['model'] ?? $model,
            provider:     $this->getName(),
            toolCalls:    $toolCalls,
            raw:          $data,
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────

    private function mapFinishReason(string $reason): string
    {
        return match ($reason) {
            'end_turn'   => 'stop',
            'max_tokens' => 'length',
            'tool_use'   => 'tool_calls',
            'stop_sequence' => 'stop',
            default      => 'stop',
        };
    }

    /**
     * Format ke Anthropic tools schema.
     * Mirip OpenAI tapi tanpa wrapper "function" key.
     */
    private function formatTools(array $tools): array
    {
        return array_map(fn (array $tool) => [
            'name'         => $tool['name'],
            'description'  => $tool['description'],
            'input_schema' => $tool['parameters'] ?? [
                'type'       => 'object',
                'properties' => [],
            ],
        ], $tools);
    }

    private function extractErrorMessage(RequestException $e): string
    {
        if ($e->hasResponse()) {
            $body = json_decode($e->getResponse()->getBody()->getContents(), true);
            return $body['error']['message'] ?? $e->getMessage();
        }
        return $e->getMessage();
    }
}
