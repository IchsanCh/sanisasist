// resources/js/lib/markdown.ts
// Konfigurasi react-markdown + rehype-highlight + remark-gfm

import rehypeHighlight from 'rehype-highlight';
import remarkGfm from 'remark-gfm';
import type { Options } from 'react-markdown';

/**
 * Shared options untuk ReactMarkdown component.
 * Import ini di ChatMessage component.
 */
export const markdownOptions: Partial<Options> = {
    remarkPlugins: [remarkGfm],
    rehypePlugins: [rehypeHighlight],
};

/**
 * Deteksi apakah konten adalah "large paste" yang perlu dilipat.
 * Threshold: lebih dari 100 baris ATAU lebih dari 3000 karakter.
 */
export function isLargePaste(content: string): boolean {
    const lineCount = content.split('\n').length;
    return lineCount > 100 || content.length > 3000;
}

/**
 * Deteksi apakah konten mengandung code block.
 */
export function hasCodeBlock(content: string): boolean {
    return content.includes('```');
}

/**
 * Ambil preview singkat dari konten panjang (untuk collapsed state).
 * Ambil 3 baris pertama.
 */
export function getPreview(content: string, lines: number = 3): string {
    return content.split('\n').slice(0, lines).join('\n');
}
