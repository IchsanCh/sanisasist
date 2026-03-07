// resources/js/components/chat/chat-sidebar.tsx

import { useState } from 'react';
import { router } from '@inertiajs/react';
import {
    Check,
    MessageSquarePlus,
    MoreHorizontal,
    Pencil,
    Trash2,
    X,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { NavUser } from '@/components/nav-user';
import type { ChatSession } from '@/types/chat';

interface ChatSidebarProps {
    sessions: ChatSession[];
    activeSessionId: number | null;
}

export function ChatSidebar({ sessions, activeSessionId }: ChatSidebarProps) {
    const handleNewChat = () => {
        router.post(
            '/chat/sessions',
            {},
            {
                preserveScroll: false,
            },
        );
    };

    return (
        <aside className="flex h-full w-64 shrink-0 flex-col border-r bg-sidebar">
            {/* Header */}
            <div className="flex items-center justify-between px-4 py-3">
                <span className="text-sm font-semibold text-sidebar-foreground">
                    AEVA
                </span>
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-7 text-sidebar-foreground/60 hover:text-sidebar-foreground"
                    onClick={handleNewChat}
                    title="Chat baru"
                >
                    <MessageSquarePlus className="size-4" />
                </Button>
            </div>

            {/* Session list */}
            <nav className="flex-1 overflow-y-auto px-2 py-1">
                {sessions.length === 0 ? (
                    <p className="px-2 py-4 text-center text-xs text-muted-foreground">
                        Belum ada percakapan.
                        <br />
                        Klik + untuk mulai.
                    </p>
                ) : (
                    <ul className="space-y-0.5">
                        {sessions.map((session) => (
                            <SessionItem
                                key={session.id}
                                session={session}
                                isActive={session.id === activeSessionId}
                            />
                        ))}
                    </ul>
                )}
            </nav>

            {/* Footer — user info */}
            <div className="border-t px-2 py-2">
                <NavUser />
            </div>
        </aside>
    );
}

// ── Single session item ───────────────────────────────────────────────────────

interface SessionItemProps {
    session: ChatSession;
    isActive: boolean;
}

function SessionItem({ session, isActive }: SessionItemProps) {
    const [isRenaming, setIsRenaming] = useState(false);
    const [renameValue, setRenameValue] = useState(session.title);

    const handleClick = () => {
        if (!isActive && !isRenaming) {
            router.get(`/chat/sessions/${session.id}`);
        }
    };

    const handleRename = () => {
        if (!renameValue.trim() || renameValue === session.title) {
            setIsRenaming(false);
            return;
        }
        router.put(
            `/chat/sessions/${session.id}`,
            { title: renameValue },
            {
                preserveScroll: true,
                onSuccess: () => setIsRenaming(false),
            },
        );
    };

    const handleDelete = () => {
        if (!confirm(`Hapus "${session.title}"?`)) return;
        router.delete(`/chat/sessions/${session.id}`);
    };

    const handleRenameKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === 'Enter') handleRename();
        if (e.key === 'Escape') {
            setRenameValue(session.title);
            setIsRenaming(false);
        }
    };

    return (
        <li>
            <div
                className={cn(
                    'group flex cursor-pointer items-center gap-2 rounded-lg px-3 py-2 text-sm transition-colors',
                    isActive
                        ? 'bg-sidebar-accent text-sidebar-accent-foreground'
                        : 'text-sidebar-foreground/70 hover:bg-sidebar-accent/50 hover:text-sidebar-foreground',
                )}
                onClick={handleClick}
            >
                {isRenaming ? (
                    // Inline rename input
                    <div
                        className="flex flex-1 items-center gap-1"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <input
                            autoFocus
                            value={renameValue}
                            onChange={(e) => setRenameValue(e.target.value)}
                            onKeyDown={handleRenameKeyDown}
                            onBlur={handleRename}
                            className="flex-1 bg-transparent text-sm outline-none"
                        />
                        <button
                            onClick={handleRename}
                            className="text-green-500 hover:text-green-400"
                        >
                            <Check className="size-3.5" />
                        </button>
                        <button
                            onClick={() => {
                                setRenameValue(session.title);
                                setIsRenaming(false);
                            }}
                            className="text-muted-foreground hover:text-foreground"
                        >
                            <X className="size-3.5" />
                        </button>
                    </div>
                ) : (
                    <>
                        <span className="flex-1 truncate">{session.title}</span>

                        {/* Actions — muncul saat hover */}
                        <DropdownMenu>
                            <DropdownMenuTrigger
                                asChild
                                onClick={(e) => e.stopPropagation()}
                            >
                                <button
                                    className={cn(
                                        'shrink-0 rounded p-0.5 opacity-0 transition-opacity group-hover:opacity-100',
                                        isActive && 'opacity-100',
                                    )}
                                >
                                    <MoreHorizontal className="size-3.5" />
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-36">
                                <DropdownMenuItem
                                    onClick={() => setIsRenaming(true)}
                                >
                                    <Pencil className="mr-2 size-3.5" />
                                    Rename
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    className="text-destructive focus:text-destructive"
                                    onClick={handleDelete}
                                >
                                    <Trash2 className="mr-2 size-3.5" />
                                    Hapus
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </>
                )}
            </div>
        </li>
    );
}
