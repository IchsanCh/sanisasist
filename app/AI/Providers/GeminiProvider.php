<?php

namespace App\AI\Providers;

use App\AI\DTO\ChatRequest;
use App\AI\DTO\ChatResponse;
use Generator;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

/**
 * GeminiProvider
 *
 * Implementasi AIProviderInterface untuk Google Gemini API.
 * Menggunakan Gemini generateContent endpoint via Guzzle.
 *
 * Docs: https://ai.google.dev/api/generate-content
 */
class GeminiProvider implements AIProviderInterface
{
    private Client $client;
    private string $apiKey;
    private string $baseUrl;

    public function __construct(string $apiKey, string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta')
    {
        $this->apiKey  = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');

        // Tidak pakai base_uri — gunakan full URL per request
        $this->client = new Client([
            'timeout' => 120,
            'headers' => ['Content-Type' => 'application/json'],
        ]);
    }

    // ── Interface Implementation ──────────────────────────────────────

    public function getName(): string
    {
        return 'gemini';
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
            $endpoint = "{$this->baseUrl}/models/{$request->model}:generateContent";

            $response = $this->client->post($endpoint, [
                'headers' => [
                    'Content-Type'    => 'application/json',
                    'X-goog-api-key'  => $this->apiKey,   // ← pindah ke header
                ],
                'json' => $payload,
            ]);
            $data     = json_decode($response->getBody()->getContents(), true);

            return $this->parseResponse($data, $request->model);

        } catch (RequestException $e) {
            $message = $this->extractErrorMessage($e);
            Log::error('GeminiProvider::sendMessage failed', [
                'error'   => $message,
                'model'   => $request->model,
                'session' => $request->sessionId,
            ]);

            return ChatResponse::error("Gemini error: {$message}", $this->getName());
        }
    }

    public function stream(ChatRequest $request): Generator
    {
        try {
            $payload  = $this->buildPayload($request);
            $endpoint = "{$this->baseUrl}/models/{$request->model}:streamGenerateContent?alt=sse";

            $response = $this->client->post($endpoint, [
                'headers' => [
                    'Content-Type'   => 'application/json',
                    'X-goog-api-key' => $this->apiKey,
                ],
                'json'   => $payload,
                'stream' => true,
            ]);
            $body   = $response->getBody();
            $buffer = '';

            while (! $body->eof()) {
                $buffer .= $body->read(1024);
                $lines   = explode("\n", $buffer);

                // Keep the last (possibly incomplete) line in buffer
                $buffer = array_pop($lines);

                foreach ($lines as $line) {
                    $line = trim($line);
                    if (! str_starts_with($line, 'data: ')) {
                        continue;
                    }

                    $json = substr($line, 6);
                    if ($json === '[DONE]') {
                        return;
                    }

                    $chunk = json_decode($json, true);
                    $text  = $chunk['candidates'][0]['content']['parts'][0]['text'] ?? '';

                    if ($text !== '') {
                        yield $text;
                    }
                }
            }

        } catch (RequestException $e) {
            Log::error('GeminiProvider::stream failed', ['error' => $e->getMessage()]);
            yield 'Error: ' . $this->extractErrorMessage($e);
        }
    }

    // ── Payload Builder ───────────────────────────────────────────────

    /**
     * Gemini API menggunakan format "contents" yang berbeda dari OpenAI.
     * - Role "assistant" → "model" di Gemini
     * - System prompt → systemInstruction field terpisah
     * - Parts adalah array, bukan string langsung
     */
    private function buildPayload(ChatRequest $request): array
    {
        $contents = [];

        foreach ($request->messages as $message) {
            // Gemini pakai "model" bukan "assistant"
            $role = $message['role'] === 'assistant' ? 'model' : $message['role'];

            // Skip system messages — sudah dihandle via systemInstruction
            if ($role === 'system') {
                continue;
            }

            $contents[] = [
                'role'  => $role,
                'parts' => [['text' => $message['content']]],
            ];
        }

        $payload = [
            'contents'         => $contents,
            'generationConfig' => [
                'temperature'     => $request->temperature,
                'maxOutputTokens' => $request->maxTokens,
            ],
        ];

        // System instruction (Gemini v1beta mendukung ini sebagai top-level field)
        if ($request->systemPrompt) {
            $payload['systemInstruction'] = [
                'parts' => [['text' => $request->systemPrompt]],
            ];
        }

        // Tool definitions
        if ($request->hasTools()) {
            $payload['tools'] = [
                ['functionDeclarations' => $this->formatTools($request->tools)],
            ];
        }

        return $payload;
    }

    // ── Response Parser ───────────────────────────────────────────────

    private function parseResponse(array $data, string $model): ChatResponse
    {
        $candidate    = $data['candidates'][0] ?? [];
        $content      = $candidate['content']['parts'][0]['text'] ?? '';
        $finishReason = $this->mapFinishReason($candidate['finishReason'] ?? 'STOP');

        // Tool calls
        $toolCalls = [];
        foreach ($candidate['content']['parts'] ?? [] as $part) {
            if (isset($part['functionCall'])) {
                $toolCalls[] = [
                    'name'      => $part['functionCall']['name'],
                    'arguments' => $part['functionCall']['args'] ?? [],
                ];
            }
        }

        // Token usage
        $usage = $data['usageMetadata'] ?? [];

        return new ChatResponse(
            content:      $content,
            inputTokens:  $usage['promptTokenCount']     ?? 0,
            outputTokens: $usage['candidatesTokenCount'] ?? 0,
            finishReason: $finishReason,
            model:        $model,
            provider:     $this->getName(),
            toolCalls:    $toolCalls,
            raw:          $data,
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────

    /**
     * Map Gemini finish reasons ke format standar kita.
     */
    private function mapFinishReason(string $geminiReason): string
    {
        return match (strtoupper($geminiReason)) {
            'STOP'           => 'stop',
            'MAX_TOKENS'     => 'length',
            'SAFETY'         => 'content_filter',
            'TOOL_CALLS',
            'FUNCTION_CALL'  => 'tool_calls',
            default          => 'stop',
        };
    }

    /**
     * Format tool definitions ke Gemini functionDeclarations schema.
     */
    private function formatTools(array $tools): array
    {
        return array_map(fn (array $tool) => [
            'name'        => $tool['name'],
            'description' => $tool['description'],
            'parameters'  => $tool['parameters'] ?? ['type' => 'object', 'properties' => []],
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
