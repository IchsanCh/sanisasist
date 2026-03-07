<?php

namespace App\AI\Services;

use App\Models\AiModel;
use App\Models\ChatSession;
use App\Models\Setting;
use App\Models\Tool;

/**
 * MessageBuilder
 *
 * Menyusun context window yang dikirim ke AI provider.
 * Urutan komposisi:
 *   1. System prompt
 *   2. Summary injection (jika ada)
 *   3. Last N messages dari history
 *   4. User message baru
 *
 * Juga menyiapkan tool definitions jika model support tools.
 */
class MessageBuilder
{
    /**
     * Susun array messages siap kirim ke AI provider.
     *
     * @param  ChatSession  $session      Sesi aktif
     * @param  string       $userMessage  Pesan baru dari user
     * @param  AiModel|null $model        Model aktif (untuk cek supports_tools)
     * @return array{messages: array, system_prompt: string, tools: array}
     */
    public function build(ChatSession $session, string $userMessage, ?AiModel $model = null): array
    {
        $messages = [];

        // ── 1. Summary injection ──────────────────────────────────────
        // Inject summary sebagai context awal jika ada.
        // Ditempatkan sebagai message pertama dengan role "system"
        // supaya AI tahu konteks percakapan sebelumnya.
        if (! empty($session->summary)) {
            $messages[] = [
                'role'    => 'system',
                'content' => "Previous conversation summary:\n{$session->summary}",
            ];
        }

        // ── 2. Last N messages dari history ───────────────────────────
        $maxMessages = (int) Setting::get('max_context_messages', 5);
        $history     = $session->getLastMessages($maxMessages);

        foreach ($history as $message) {
            $messages[] = $message->toContextArray();
        }

        // ── 3. User message baru ──────────────────────────────────────
        $messages[] = [
            'role'    => 'user',
            'content' => $userMessage,
        ];

        // ── 4. Tool definitions ───────────────────────────────────────
        // Hanya load jika model support tools
        $tools = [];
        if ($model?->supports_tools) {
            $tools = $this->buildToolDefinitions();
        }

        return [
            'messages'      => $messages,
            'system_prompt' => $this->buildSystemPrompt($session),
            'tools'         => $tools,
        ];
    }

    // ── Private Helpers ───────────────────────────────────────────────

    /**
     * System prompt — persona dan instruksi dasar AI.
     * Bisa di-extend nanti untuk per-user custom prompt via settings.
     */
    private function buildSystemPrompt(?ChatSession $session = null): string
    {
        // Cek override per-sesi dulu di metadata
        $sessionPrompt = $session?->metadata['system_prompt'] ?? null;

        if (! empty($sessionPrompt)) {
            return $sessionPrompt;
        }

        // Fallback ke global setting, lalu hardcoded default
        return Setting::get(
            'system_prompt',
            'You are AEVA (Adaptive Empathic Virtual Assistant), a helpful and intelligent AI assistant. You provide clear, accurate, and thoughtful responses. When writing code, always use proper formatting with code blocks. When you don\'t know something, say so honestly.'
        );
    }

    /**
     * Load enabled tools dan merge dengan parameter schema
     * dari masing-masing ToolInterface implementation.
     */
    private function buildToolDefinitions(): array
    {
        $enabledTools = Tool::enabled()->get();

        if ($enabledTools->isEmpty()) {
            return [];
        }

        // Map slug ke concrete class
        $toolClassMap = [
            'database_query' => \App\AI\Tools\DatabaseQueryTool::class,
        ];

        $definitions = [];

        foreach ($enabledTools as $tool) {
            $className = $toolClassMap[$tool->slug] ?? null;

            // Skip jika class belum diimplementasi
            if (! $className || ! class_exists($className)) {
                continue;
            }

            /** @var \App\AI\Tools\ToolInterface $instance */
            $instance = app($className);

            $definitions[] = [
                'name'        => $tool->slug,
                'description' => $tool->description,
                'parameters'  => $instance->getParameters(),
            ];
        }

        return $definitions;
    }
}
