// resources/js/components/chat/streaming-message.tsx

import ReactMarkdown from 'react-markdown';
import rehypeHighlight from 'rehype-highlight';
import remarkGfm from 'remark-gfm';
import { Spinner } from '@/components/ui/spinner';

interface StreamingMessageProps {
    content: string;
}

/**
 * Menampilkan assistant message yang sedang di-stream token per token.
 * Render markdown realtime + blinking cursor di akhir.
 */
export function StreamingMessage({ content }: StreamingMessageProps) {
    return (
        <div className="flex gap-3 px-4 py-3">
            {/* Avatar */}
            <div className="mt-1 flex size-7 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground">
                A
            </div>

            <div className="flex max-w-[80%] flex-col gap-1">
                {content ? (
                    <div className="prose prose-sm dark:prose-invert max-w-none leading-relaxed">
                        <ReactMarkdown
                            remarkPlugins={[remarkGfm]}
                            rehypePlugins={[rehypeHighlight]}
                        >
                            {content}
                        </ReactMarkdown>
                        {/* Blinking cursor */}
                        <span className="ml-0.5 inline-block h-4 w-0.5 animate-pulse bg-current align-middle" />
                    </div>
                ) : (
                    // Sebelum token pertama datang — loading indicator
                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                        <Spinner className="size-3" />
                        <span>AEVA sedang mengetik...</span>
                    </div>
                )}
            </div>
        </div>
    );
}
