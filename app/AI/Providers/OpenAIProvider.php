<?php

namespace App\AI\Providers;

use App\AI\DTO\ChatRequest;
use App\AI\DTO\ChatResponse;
use Generator;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

/**
 * OpenAIProvider
 *
 * Implementasi AIProviderInterface untuk OpenAI Chat Completions API.
 * Format OpenAI juga kompatibel dengan provider lain yang OpenAI-compatible
 * (e.g. Groq, Together AI, local Ollama).
 *
 * Docs: https://platform.openai.com/docs/api-reference/chat
 */
class OpenAIProvider implements AIProviderInterface
{
    private Client $client;

    public function __construct(string $apiKey, string $baseUrl = 'https://api.openai.com/v1')
    {
        $this->client = new Client([
            'base_uri' => rtrim($baseUrl, '/'),
            'timeout'  => 120,
            'headers'  => [
                'Content-Type'  => 'application/json',
                'Authorization' => "Bearer {$apiKey}",
            ],
        ]);
    }

    // ── Interface Implementation ──────────────────────────────────────

    public function getName(): string
    {
        return 'openai';
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
            $payload  = $this->buildPayload($request, stream: false);
            $response = $this->client->post('/chat/completions', ['json' => $payload]);
            $data     = json_decode($response->getBody()->getContents(), true);

            return $this->parseResponse($data, $request->model);

        } catch (RequestException $e) {
            $message = $this->extractErrorMessage($e);
            Log::error('OpenAIProvider::sendMessage failed', [
                'error'   => $message,
                'model'   => $request->model,
                'session' => $request->sessionId,
            ]);

            return ChatResponse::error("OpenAI error: {$message}", $this->getName());
        }
    }

    public function stream(ChatRequest $request): Generator
    {
        try {
            $payload  = $this->buildPayload($request, stream: true);
            $response = $this->client->post('/chat/completions', [
                'json'   => $payload,
                'stream' => true,
            ]);

            $body   = $response->getBody();
            $buffer = '';

            while (! $body->eof()) {
                $buffer .= $body->read(1024);
                $lines   = explode("\n", $buffer);
                $buffer  = array_pop($lines);

                foreach ($lines as $line) {
                    $line = trim($line);
                    if (! str_starts_with($line, 'data: ')) {
                        continue;
                    }

                    $json = substr($line, 6);
                    if ($json === '[DONE]') {
                        return;
                    }

                    $chunk   = json_decode($json, true);
                    $content = $chunk['choices'][0]['delta']['content'] ?? '';

                    if ($content !== '') {
                        yield $content;
                    }
                }
            }

        } catch (RequestException $e) {
            Log::error('OpenAIProvider::stream failed', ['error' => $e->getMessage()]);
            yield 'Error: ' . $this->extractErrorMessage($e);
        }
    }

    // ── Payload Builder ───────────────────────────────────────────────

    /**
     * OpenAI format adalah yang paling "standar" — messages array langsung,
     * system prompt masuk sebagai message pertama dengan role "system".
     */
    private function buildPayload(ChatRequest $request, bool $stream = false): array
    {
        $messages = [];

        // System prompt sebagai message pertama
        if ($request->systemPrompt) {
            $messages[] = ['role' => 'system', 'content' => $request->systemPrompt];
        }

        // Append conversation messages
        foreach ($request->messages as $message) {
            // Skip system messages yang sudah kita inject — hindari duplikasi
            if ($message['role'] === 'system') {
                continue;
            }
            $messages[] = ['role' => $message['role'], 'content' => $message['content']];
        }

        $payload = [
            'model'       => $request->model,
            'messages'    => $messages,
            'max_tokens'  => $request->maxTokens,
            'temperature' => $request->temperature,
            'stream'      => $stream,
        ];

        // Tool definitions
        if ($request->hasTools()) {
            $payload['tools']       = $this->formatTools($request->tools);
            $payload['tool_choice'] = 'auto';
        }

        return $payload;
    }

    // ── Response Parser ───────────────────────────────────────────────

    private function parseResponse(array $data, string $model): ChatResponse
    {
        $choice       = $data['choices'][0] ?? [];
        $message      = $choice['message'] ?? [];
        $content      = $message['content'] ?? '';
        $finishReason = $this->mapFinishReason($choice['finish_reason'] ?? 'stop');

        // Tool calls
        $toolCalls = [];
        foreach ($message['tool_calls'] ?? [] as $call) {
            $toolCalls[] = [
                'name'      => $call['function']['name'],
                'arguments' => json_decode($call['function']['arguments'], true) ?? [],
            ];
        }

        $usage = $data['usage'] ?? [];

        return new ChatResponse(
            content:      $content ?? '',
            inputTokens:  $usage['prompt_tokens']     ?? 0,
            outputTokens: $usage['completion_tokens'] ?? 0,
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
            'stop'          => 'stop',
            'length'        => 'length',
            'tool_calls'    => 'tool_calls',
            'content_filter' => 'content_filter',
            default         => 'stop',
        };
    }

    /**
     * Format ke OpenAI tools schema (function calling).
     */
    private function formatTools(array $tools): array
    {
        return array_map(fn (array $tool) => [
            'type'     => 'function',
            'function' => [
                'name'        => $tool['name'],
                'description' => $tool['description'],
                'parameters'  => $tool['parameters'] ?? [
                    'type'       => 'object',
                    'properties' => [],
                ],
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
