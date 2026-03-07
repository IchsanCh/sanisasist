// resources/js/hooks/use-auto-scroll.ts

import { useCallback, useEffect, useRef, useState } from 'react';

interface UseAutoScrollReturn {
    scrollRef: React.RefObject<HTMLDivElement | null>;
    isAtBottom: boolean;
    scrollToBottom: (behavior?: ScrollBehavior) => void;
}

/**
 * Auto-scroll ke bawah saat messages baru masuk,
 * tapi tidak scroll jika user sedang scroll ke atas (baca history).
 */
export function useAutoScroll(dependency: unknown): UseAutoScrollReturn {
    const scrollRef = useRef<HTMLDivElement>(null);
    const [isAtBottom, setIsAtBottom] = useState(true);

    const scrollToBottom = useCallback(
        (behavior: ScrollBehavior = 'smooth') => {
            const el = scrollRef.current;
            if (!el) return;
            el.scrollTo({ top: el.scrollHeight, behavior });
        },
        [],
    );

    // Deteksi apakah user sedang di bawah atau scroll ke atas
    useEffect(() => {
        const el = scrollRef.current;
        if (!el) return;

        const handleScroll = () => {
            const threshold = 80; // px dari bawah — masih dianggap "at bottom"
            const atBottom =
                el.scrollHeight - el.scrollTop - el.clientHeight < threshold;
            setIsAtBottom(atBottom);
        };

        el.addEventListener('scroll', handleScroll, { passive: true });
        return () => el.removeEventListener('scroll', handleScroll);
    }, []);

    // Auto-scroll saat dependency berubah (messages baru / streaming chunk)
    useEffect(() => {
        if (isAtBottom) {
            scrollToBottom('smooth');
        }
    }, [dependency, isAtBottom, scrollToBottom]);

    return { scrollRef, isAtBottom, scrollToBottom };
}
