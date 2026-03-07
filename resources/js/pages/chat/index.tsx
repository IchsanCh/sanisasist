import { Head, usePage } from '@inertiajs/react';
import { ChatContainer } from '@/components/chat/chat-container';
import { ChatSidebar } from '@/components/chat/chat-sidebar';
import type { ChatMessage, ChatPageProps, ChatSession } from '@/types/chat';

// Extend shared props dengan chat-specific props
interface ChatIndexProps extends ChatPageProps {
    streaming_enabled: boolean;
}

export default function ChatIndex({
    sessions,
    activeSession,
    messages,
    streaming_enabled,
}: ChatIndexProps) {
    return (
        <>
            <Head title={activeSession?.title ?? 'Chat'} />

            {/* Fullscreen layout — tanpa app header bawaan */}
            <div className="flex h-screen overflow-hidden bg-background">
                {/* Sidebar kiri */}
                <ChatSidebar
                    sessions={sessions}
                    activeSessionId={activeSession?.id ?? null}
                />

                {/* Area chat kanan */}
                <main className="relative flex flex-1 flex-col overflow-hidden">
                    {activeSession ? (
                        <ChatContainer
                            session={activeSession}
                            initialMessages={messages}
                            streamingEnabled={streaming_enabled ?? true}
                        />
                    ) : (
                        <NoSessionSelected />
                    )}
                </main>
            </div>
        </>
    );
}

// ── Empty state saat belum pilih session ─────────────────────────────────────

function NoSessionSelected() {
    return (
        <div className="flex h-full flex-col items-center justify-center gap-4 text-center">
            <div className="flex size-20 items-center justify-center rounded-full bg-primary/10">
                <span className="text-3xl font-bold text-primary">A</span>
            </div>
            <div>
                <h1 className="text-2xl font-semibold">Halo, aku AEVA</h1>
                <p className="mt-1 text-sm text-muted-foreground">
                    Pilih percakapan di sidebar atau mulai yang baru.
                </p>
            </div>
        </div>
    );
}
