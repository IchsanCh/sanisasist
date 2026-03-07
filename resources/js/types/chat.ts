// resources/js/types/chat.ts

export type MessageRole = 'user' | 'assistant' | 'system';

export interface ChatMessage {
    id: number;
    role: MessageRole;
    content: string;
    tokens?: number;
    model_used?: string;
    created_at: string;
}

export interface ChatSession {
    id: number;
    title: string;
    metadata?: {
        system_prompt?: string;
        [key: string]: unknown;
    };
    created_at?: string;
    updated_at?: string;
}

export interface AiModel {
    id: number;
    name: string;
    model_key: string;
    supports_image: boolean;
    supports_file: boolean;
    supports_tools: boolean;
    supports_streaming: boolean;
    context_window?: number;
}

export interface AiProvider {
    id: number;
    name: string;
    slug: string;
    active: boolean;
    models: AiModel[];
}

// Inertia page props untuk halaman chat
export interface ChatPageProps {
    sessions: ChatSession[];
    activeSession: ChatSession | null;
    messages: ChatMessage[];
}

// SSE streaming event types
export type StreamEventType = 'delta' | 'done' | 'error';

export interface StreamDeltaEvent {
    type: 'delta';
    content: string;
}

export interface StreamDoneEvent {
    type: 'done';
    message: {
        id: number;
        role: 'assistant';
        content: string;
        tokens?: number;
    };
}

export interface StreamErrorEvent {
    type: 'error';
    message: string;
}

export type StreamEvent = StreamDeltaEvent | StreamDoneEvent | StreamErrorEvent;

// Response dari store() endpoint (non-streaming)
export interface SendMessageResponse {
    user_message: ChatMessage;
    assistant_message: ChatMessage;
    session: {
        id: number;
        title: string;
    };
}
