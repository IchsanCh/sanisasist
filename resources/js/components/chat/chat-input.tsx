// resources/js/components/chat/chat-input.tsx

import { useEffect, useRef, useState } from 'react';
import { ArrowUp, Square } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';

interface ChatInputProps {
    onSend: (content: string) => void;
    isLoading: boolean;
    isStreaming: boolean;
    disabled?: boolean;
    placeholder?: string;
}

export function ChatInput({
    onSend,
    isLoading,
    isStreaming,
    disabled = false,
    placeholder = 'Ketik pesan...',
}: ChatInputProps) {
    const [value, setValue] = useState('');
    const textareaRef = useRef<HTMLTextAreaElement>(null);
    const isBusy = isLoading || isStreaming;

    // Auto-resize textarea
    useEffect(() => {
        const el = textareaRef.current;
        if (!el) return;
        el.style.height = 'auto';
        el.style.height = Math.min(el.scrollHeight, 200) + 'px';
    }, [value]);

    const handleSend = () => {
        const trimmed = value.trim();
        if (!trimmed || isBusy || disabled) return;
        onSend(trimmed);
        setValue('');
        // Reset textarea height
        if (textareaRef.current) {
            textareaRef.current.style.height = 'auto';
        }
    };

    const handleKeyDown = (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
        // Enter = kirim, Shift+Enter = newline
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleSend();
        }
    };

    const handlePaste = (e: React.ClipboardEvent<HTMLTextAreaElement>) => {
        // Paste besar langsung masuk sebagai teks biasa
        // isLargePaste detection dilakukan di ChatMessage saat render
        const text = e.clipboardData.getData('text');
        if (!text) return;
        // Biarkan default paste behavior berjalan
    };

    const isEmpty = !value.trim();

    return (
        <div className="border-t bg-background px-4 py-3">
            <div
                className={cn(
                    'relative flex items-end gap-2 rounded-2xl border bg-muted/30 px-4 py-3 transition-all',
                    'focus-within:border-ring focus-within:ring-1 focus-within:ring-ring/30',
                    disabled && 'opacity-50',
                )}
            >
                <textarea
                    ref={textareaRef}
                    value={value}
                    onChange={(e) => setValue(e.target.value)}
                    onKeyDown={handleKeyDown}
                    onPaste={handlePaste}
                    placeholder={isBusy ? 'Menunggu respons...' : placeholder}
                    disabled={disabled || isBusy}
                    rows={1}
                    className={cn(
                        'max-h-[200px] flex-1 resize-none bg-transparent text-sm outline-none',
                        'placeholder:text-muted-foreground',
                        'disabled:cursor-not-allowed',
                        'scrollbar-thin',
                    )}
                />

                {/* Send / Stop button */}
                <Button
                    size="icon"
                    className={cn(
                        'size-8 shrink-0 rounded-full transition-all',
                        isEmpty && !isBusy && 'opacity-40',
                    )}
                    onClick={handleSend}
                    disabled={disabled || (isEmpty && !isBusy)}
                    aria-label={isBusy ? 'Stop' : 'Kirim'}
                >
                    {isStreaming ? (
                        <Square className="size-3.5 fill-current" />
                    ) : (
                        <ArrowUp className="size-4" />
                    )}
                </Button>
            </div>

            <p className="mt-1.5 text-center text-[11px] text-muted-foreground">
                Enter untuk kirim · Shift+Enter untuk baris baru
            </p>
        </div>
    );
}
