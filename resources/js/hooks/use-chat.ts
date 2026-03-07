import { router } from '@inertiajs/react';
import { useCallback, useRef, useState } from 'react';
import type { ChatMessage, SendMessageResponse } from '@/types/chat';

interface UseChatOptions {
    sessionId: number | null;
    initialMessages?: ChatMessage[];
    streamingEnabled?: boolean;
    onSessionTitleChange?: (sessionId: number, title: string) => void;
}

interface UseChatReturn {
    messages: ChatMessage[];
    streamingContent: string;
    isLoading: boolean;
    isStreaming: boolean;
    error: string | null;
    send: (content: string) => Promise<void>;
    clearError: () => void;
}

export function useChat({
    sessionId,
    initialMessages = [],
    streamingEnabled = true,
    onSessionTitleChange,
}: UseChatOptions): UseChatReturn {
    const [messages, setMessages] = useState<ChatMessage[]>(initialMessages);
    const [streamingContent, setStreamingContent] = useState('');
    const [isLoading, setIsLoading] = useState(false);
    const [isStreaming, setIsStreaming] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const abortControllerRef = useRef<AbortController | null>(null);

    const getCsrfToken = (): string =>
        (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)
            ?.content ?? '';

    // URL builder — pakai string langsung karena Wayfinder generate saat npm run dev
    const getStreamUrl = (id: number) => `/chat/sessions/${id}/messages/stream`;
    const getStoreUrl = (id: number) => `/chat/sessions/${id}/messages`;

    // ── Streaming via SSE ─────────────────────────────────────────────────
    const sendStreaming = useCallback(
        async (content: string, tempId: number) => {
            if (!sessionId) return;

            setIsStreaming(true);
            setStreamingContent('');
            abortControllerRef.current = new AbortController();

            try {
                const response = await fetch(getStreamUrl(sessionId), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken(),
                        Accept: 'text/event-stream',
                    },
                    body: JSON.stringify({ message: content }),
                    signal: abortControllerRef.current.signal,
                });

                if (!response.ok || !response.body) {
                    throw new Error('Stream connection failed');
                }

                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';
                let accumulated = '';

                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;

                    buffer += decoder.decode(value, { stream: true });
                    const lines = buffer.split('\n');
                    buffer = lines.pop() ?? '';

                    for (const line of lines) {
                        if (!line.startsWith('data: ')) continue;
                        const jsonStr = line.slice(6).trim();
                        if (!jsonStr) continue;

                        try {
                            const event = JSON.parse(jsonStr);

                            if (event.type === 'delta') {
                                accumulated += event.content as string;
                                setStreamingContent(accumulated);
                            } else if (event.type === 'done') {
                                const assistantMessage: ChatMessage = {
                                    ...event.message,
                                    created_at: new Date().toISOString(),
                                };
                                setMessages((prev) => [
                                    ...prev,
                                    assistantMessage,
                                ]);
                                setStreamingContent('');
                                // Reload sidebar sessions untuk update title
                                router.reload({
                                    only: ['sessions', 'activeSession'],
                                });
                            } else if (event.type === 'error') {
                                throw new Error(event.message as string);
                            }
                        } catch {
                            // skip malformed SSE line
                        }
                    }
                }
            } catch (err: unknown) {
                if (err instanceof Error && err.name === 'AbortError') return;
                setStreamingContent('');
                setError('Gagal mengirim pesan. Coba lagi.');
                setMessages((prev) => prev.filter((m) => m.id !== tempId));
            } finally {
                setIsStreaming(false);
                setStreamingContent('');
            }
        },
        [sessionId],
    );

    // ── Non-streaming via JSON ────────────────────────────────────────────
    const sendNonStreaming = useCallback(
        async (content: string, tempId: number) => {
            if (!sessionId) return;

            try {
                const response = await fetch(getStoreUrl(sessionId), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken(),
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({ message: content }),
                });

                if (!response.ok) throw new Error('Request failed');

                const data: SendMessageResponse = await response.json();

                setMessages((prev) => [
                    ...prev.filter((m) => m.id !== tempId),
                    data.user_message,
                    data.assistant_message,
                ]);

                if (data.session.title !== 'New Chat') {
                    onSessionTitleChange?.(data.session.id, data.session.title);
                }
            } catch {
                setError('Gagal mengirim pesan. Coba lagi.');
                setMessages((prev) => prev.filter((m) => m.id !== tempId));
            }
        },
        [sessionId, onSessionTitleChange],
    );

    // ── Main send ─────────────────────────────────────────────────────────
    const send = useCallback(
        async (content: string) => {
            if (!sessionId || !content.trim() || isLoading || isStreaming)
                return;

            setIsLoading(true);
            setError(null);

            const tempId = Date.now();
            const tempUserMessage: ChatMessage = {
                id: tempId,
                role: 'user',
                content: content.trim(),
                created_at: new Date().toISOString(),
            };
            setMessages((prev) => [...prev, tempUserMessage]);

            try {
                if (streamingEnabled) {
                    await sendStreaming(content.trim(), tempId);
                } else {
                    await sendNonStreaming(content.trim(), tempId);
                }
            } finally {
                setIsLoading(false);
            }
        },
        [
            sessionId,
            isLoading,
            isStreaming,
            streamingEnabled,
            sendStreaming,
            sendNonStreaming,
        ],
    );

    const clearError = useCallback(() => setError(null), []);

    return {
        messages,
        streamingContent,
        isLoading,
        isStreaming,
        error,
        send,
        clearError,
    };
}
