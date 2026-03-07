// resources/js/components/chat/chat-container.tsx

import { useEffect, useRef } from 'react';
import { MessageSquare } from 'lucide-react';
import { ChatMessage } from '@/components/chat/chat-message';
import { StreamingMessage } from '@/components/chat/streaming-message';
import { ChatInput } from '@/components/chat/chat-input';
import { useAutoScroll } from '@/hooks/use-auto-scroll';
import { useChat } from '@/hooks/use-chat';
import type { ChatMessage as ChatMessageType, ChatSession } from '@/types/chat';

interface ChatContainerProps {
    session: ChatSession;
    initialMessages: ChatMessageType[];
    streamingEnabled?: boolean;
}

export function ChatContainer({
    session,
    initialMessages,
    streamingEnabled = true,
}: ChatContainerProps) {
    const {
        messages,
        streamingContent,
        isLoading,
        isStreaming,
        error,
        send,
        clearError,
    } = useChat({
        sessionId: session.id,
        initialMessages,
        streamingEnabled,
    });

    // Auto-scroll saat messages atau streaming content berubah
    const scrollDep = `${messages.length}-${streamingContent.length}`;
    const { scrollRef, isAtBottom, scrollToBottom } = useAutoScroll(scrollDep);

    return (
        <div className="flex h-full flex-col">
            {/* Messages area */}
            <div ref={scrollRef} className="flex-1 overflow-y-auto">
                {messages.length === 0 && !isStreaming ? (
                    <EmptyState sessionTitle={session.title} />
                ) : (
                    <div className="mx-auto max-w-3xl py-4">
                        {messages.map((message) => (
                            <ChatMessage key={message.id} message={message} />
                        ))}

                        {/* Streaming placeholder */}
                        {isStreaming && (
                            <StreamingMessage content={streamingContent} />
                        )}

                        {/* Spacer bawah */}
                        <div className="h-4" />
                    </div>
                )}
            </div>

            {/* Scroll to bottom button — muncul jika user scroll ke atas */}
            {!isAtBottom && messages.length > 0 && (
                <div className="absolute bottom-24 left-1/2 -translate-x-1/2">
                    <button
                        onClick={() => scrollToBottom('smooth')}
                        className="flex items-center gap-1.5 rounded-full border bg-background px-3 py-1.5 text-xs shadow-md transition-all hover:bg-muted"
                    >
                        ↓ Gulir ke bawah
                    </button>
                </div>
            )}

            {/* Error banner */}
            {error && (
                <div className="mx-4 mb-2 flex items-center justify-between rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-2 text-sm text-destructive">
                    <span>{error}</span>
                    <button
                        onClick={clearError}
                        className="ml-2 text-xs underline"
                    >
                        Tutup
                    </button>
                </div>
            )}

            {/* Input */}
            <div className="mx-auto w-full max-w-3xl">
                <ChatInput
                    onSend={send}
                    isLoading={isLoading}
                    isStreaming={isStreaming}
                />
            </div>
        </div>
    );
}

// ── Empty state ───────────────────────────────────────────────────────────────

function EmptyState({ sessionTitle }: { sessionTitle: string }) {
    const isNewChat = sessionTitle === 'New Chat';

    return (
        <div className="flex h-full flex-col items-center justify-center gap-4 px-4 text-center">
            <div className="flex size-16 items-center justify-center rounded-full bg-primary/10">
                <MessageSquare className="size-8 text-primary" />
            </div>
            <div>
                <h2 className="text-xl font-semibold">
                    {isNewChat
                        ? 'Halo! Ada yang bisa aku bantu?'
                        : sessionTitle}
                </h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Ketik pesan di bawah untuk memulai percakapan.
                </p>
            </div>
        </div>
    );
}
