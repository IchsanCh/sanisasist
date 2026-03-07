// resources/js/components/chat/chat-message.tsx

import { useState } from 'react';
import ReactMarkdown from 'react-markdown';
import rehypeHighlight from 'rehype-highlight';
import remarkGfm from 'remark-gfm';
import { Check, ChevronDown, ChevronUp, Copy, User } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import { isLargePaste } from '@/lib/markdown';
import type { ChatMessage as ChatMessageType } from '@/types/chat';

// Import highlight.js theme — pilih sesuai selera
// pastikan di app.css atau di sini
import 'highlight.js/styles/github-dark.css';

interface ChatMessageProps {
    message: ChatMessageType;
}

export function ChatMessage({ message }: ChatMessageProps) {
    const isUser = message.role === 'user';
    const large = isUser && isLargePaste(message.content);

    return (
        <div
            className={cn(
                'group flex gap-3 px-4 py-3',
                isUser ? 'justify-end' : 'justify-start',
            )}
        >
            {/* Avatar — hanya assistant */}
            {!isUser && (
                <div className="mt-1 flex size-7 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground">
                    A
                </div>
            )}

            <div
                className={cn(
                    'flex max-w-[80%] flex-col gap-1',
                    isUser && 'items-end',
                )}
            >
                {/* Bubble */}
                {isUser ? (
                    <UserMessage content={message.content} isLarge={large} />
                ) : (
                    <AssistantMessage content={message.content} />
                )}

                {/* Token info — hanya untuk assistant, muncul saat hover */}
                {!isUser && message.tokens && (
                    <span className="px-1 text-[11px] text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100">
                        {message.tokens} tokens
                        {message.model_used && ` · ${message.model_used}`}
                    </span>
                )}
            </div>

            {/* Avatar — user */}
            {isUser && (
                <div className="mt-1 flex size-7 shrink-0 items-center justify-center rounded-full bg-muted">
                    <User className="size-4 text-muted-foreground" />
                </div>
            )}
        </div>
    );
}

// ── User message bubble ───────────────────────────────────────────────────────

function UserMessage({
    content,
    isLarge,
}: {
    content: string;
    isLarge: boolean;
}) {
    const [expanded, setExpanded] = useState(false);
    const [copied, setCopied] = useState(false);

    const handleCopy = async () => {
        await navigator.clipboard.writeText(content);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    if (isLarge) {
        // Lampiran style — collapsed by default
        const lineCount = content.split('\n').length;
        const preview = content.split('\n').slice(0, 3).join('\n');

        return (
            <div className="w-full max-w-lg rounded-2xl border bg-muted/50 text-sm">
                {/* Header lampiran */}
                <div className="flex items-center justify-between gap-2 border-b px-4 py-2">
                    <span className="text-xs font-medium text-muted-foreground">
                        Teks panjang · {lineCount} baris
                    </span>
                    <div className="flex items-center gap-1">
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-6 px-2 text-xs"
                            onClick={handleCopy}
                        >
                            {copied ? (
                                <>
                                    <Check className="size-3" /> Disalin
                                </>
                            ) : (
                                <>
                                    <Copy className="size-3" /> Salin
                                </>
                            )}
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-6 px-2 text-xs"
                            onClick={() => setExpanded((v) => !v)}
                        >
                            {expanded ? (
                                <>
                                    <ChevronUp className="size-3" /> Tutup
                                </>
                            ) : (
                                <>
                                    <ChevronDown className="size-3" /> Lihat
                                    semua
                                </>
                            )}
                        </Button>
                    </div>
                </div>

                {/* Content */}
                <div className="px-4 py-3">
                    <pre
                        className={cn(
                            'font-mono text-xs leading-relaxed whitespace-pre-wrap text-foreground',
                            !expanded && 'line-clamp-3',
                        )}
                    >
                        {expanded ? content : preview}
                    </pre>
                </div>
            </div>
        );
    }

    // Pesan biasa
    return (
        <div className="rounded-2xl rounded-tr-sm bg-primary px-4 py-2.5 text-sm text-primary-foreground">
            <p className="leading-relaxed whitespace-pre-wrap">{content}</p>
        </div>
    );
}

// ── Assistant message — markdown rendered ─────────────────────────────────────

function AssistantMessage({ content }: { content: string }) {
    return (
        <div className="prose prose-sm dark:prose-invert max-w-none leading-relaxed">
            <ReactMarkdown
                remarkPlugins={[remarkGfm]}
                rehypePlugins={[rehypeHighlight]}
                components={{
                    // Code block dengan copy button
                    pre: ({ children, ...props }) => (
                        <CodeBlock {...props}>{children}</CodeBlock>
                    ),
                    // Inline code
                    code: ({ children, className, ...props }) => {
                        const isBlock = className?.includes('language-');
                        if (isBlock) {
                            return (
                                <code className={className} {...props}>
                                    {children}
                                </code>
                            );
                        }
                        return (
                            <code
                                className="rounded bg-muted px-1.5 py-0.5 font-mono text-[0.85em]"
                                {...props}
                            >
                                {children}
                            </code>
                        );
                    },
                    // Link — buka di tab baru
                    a: ({ children, href, ...props }) => (
                        <a
                            href={href}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-primary underline underline-offset-2 hover:no-underline"
                            {...props}
                        >
                            {children}
                        </a>
                    ),
                    // Table
                    table: ({ children, ...props }) => (
                        <div className="overflow-x-auto">
                            <table className="w-full" {...props}>
                                {children}
                            </table>
                        </div>
                    ),
                }}
            >
                {content}
            </ReactMarkdown>
        </div>
    );
}

// ── Code block dengan copy button ─────────────────────────────────────────────

function CodeBlock({ children, ...props }: React.ComponentProps<'pre'>) {
    const [copied, setCopied] = useState(false);

    const handleCopy = async () => {
        // Extract text dari code element
        const codeEl = (props as { ref?: React.RefObject<HTMLPreElement> })
            ?.ref;
        const text =
            typeof children === 'string'
                ? children
                : extractTextContent(children);

        await navigator.clipboard.writeText(text);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <div className="group/code relative my-3 overflow-hidden rounded-lg border bg-[#0d1117]">
            {/* Copy button */}
            <button
                onClick={handleCopy}
                className="absolute top-2 right-2 z-10 flex items-center gap-1 rounded-md bg-white/10 px-2 py-1 text-xs text-white/70 opacity-0 transition-all group-hover/code:opacity-100 hover:bg-white/20 hover:text-white"
            >
                {copied ? (
                    <>
                        <Check className="size-3" /> Disalin
                    </>
                ) : (
                    <>
                        <Copy className="size-3" /> Salin
                    </>
                )}
            </button>

            <pre className="overflow-x-auto p-4 text-sm" {...props}>
                {children}
            </pre>
        </div>
    );
}

// Helper extract text dari React children
function extractTextContent(children: React.ReactNode): string {
    if (typeof children === 'string') return children;
    if (typeof children === 'number') return String(children);
    if (!children) return '';
    if (Array.isArray(children))
        return children.map(extractTextContent).join('');
    if (typeof children === 'object' && 'props' in (children as object)) {
        const el = children as { props: { children?: React.ReactNode } };
        return extractTextContent(el.props.children);
    }
    return '';
}
