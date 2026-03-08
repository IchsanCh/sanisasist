// resources/js/pages/settings/AiConfig.tsx

import { Head, useForm } from '@inertiajs/react';
import { Check, Eye, EyeOff, Loader2, Save } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import type { AiProvider } from '@/types/chat';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'AI Configuration', href: '/settings/ai' },
];

interface AiConfigProps {
    providers: AiProvider[];
    activeProvider: string;
    activeModel: string;
    systemPrompt: string;
    streamingEnabled: boolean;
    hasGeminiKey: boolean;
    hasOpenaiKey: boolean;
    hasClaudeKey: boolean;
}

export default function AiConfig({
    providers,
    activeProvider,
    activeModel,
    systemPrompt,
    streamingEnabled,
    hasGeminiKey,
    hasOpenaiKey,
    hasClaudeKey,
}: AiConfigProps) {
    const { data, setData, post, processing, errors, recentlySuccessful } =
        useForm({
            active_provider: activeProvider,
            active_model: activeModel,
            system_prompt: systemPrompt ?? '',
            streaming_enabled: streamingEnabled,
            gemini_api_key: '',
            openai_api_key: '',
            claude_api_key: '',
        });

    const [showKeys, setShowKeys] = useState({
        gemini: false,
        openai: false,
        claude: false,
    });

    const currentProvider = providers.find(
        (p) => p.slug === data.active_provider,
    );
    const availableModels = currentProvider?.models ?? [];

    const handleProviderChange = (slug: string) => {
        const provider = providers.find((p) => p.slug === slug);
        const firstModel = provider?.models?.[0]?.model_key ?? '';
        setData((prev) => ({
            ...prev,
            active_provider: slug,
            active_model: firstModel,
        }));
    };

    const submit = () => post('/settings/ai', { preserveScroll: true });

    const hasKey: Record<string, boolean> = {
        gemini: hasGeminiKey,
        openai: hasOpenaiKey,
        claude: hasClaudeKey,
    };

    const toggleShowKey = (slug: 'gemini' | 'openai' | 'claude') =>
        setShowKeys((prev) => ({ ...prev, [slug]: !prev[slug] }));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="AI Configuration" />
            <SettingsLayout>
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="AI Configuration"
                        description="Manage AI provider, model, and behavior settings"
                    />

                    {/* ── Provider & Model ─────────────────────────────── */}
                    <section className="space-y-4">
                        <h3 className="text-sm font-medium">
                            Provider & Model
                        </h3>

                        <div className="space-y-2">
                            <Label>Active Provider</Label>
                            <Select
                                value={data.active_provider}
                                onValueChange={handleProviderChange}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih provider..." />
                                </SelectTrigger>
                                <SelectContent>
                                    {providers.map((p) => (
                                        <SelectItem key={p.slug} value={p.slug}>
                                            {p.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.active_provider} />
                        </div>

                        <div className="space-y-2">
                            <Label>Active Model</Label>
                            <Select
                                value={data.active_model}
                                onValueChange={(v) =>
                                    setData('active_model', v)
                                }
                                disabled={availableModels.length === 0}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih model..." />
                                </SelectTrigger>
                                <SelectContent>
                                    {availableModels.map((m) => (
                                        <SelectItem
                                            key={m.model_key}
                                            value={m.model_key}
                                        >
                                            <span>{m.name}</span>
                                            {m.context_window && (
                                                <span className="ml-2 text-xs text-muted-foreground">
                                                    {(
                                                        m.context_window / 1000
                                                    ).toFixed(0)}
                                                    k ctx
                                                </span>
                                            )}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.active_model} />
                        </div>
                    </section>

                    {/* ── API Keys ─────────────────────────────────────── */}
                    <section className="space-y-4">
                        <h3 className="text-sm font-medium">API Keys</h3>
                        <p className="text-xs text-muted-foreground">
                            Kosongkan untuk tidak mengubah key yang sudah
                            tersimpan.
                        </p>

                        {(
                            [
                                {
                                    slug: 'gemini',
                                    label: 'Gemini API Key',
                                    field: 'gemini_api_key',
                                    hint: 'aistudio.google.com',
                                },
                                {
                                    slug: 'openai',
                                    label: 'OpenAI API Key',
                                    field: 'openai_api_key',
                                    hint: 'platform.openai.com',
                                },
                                {
                                    slug: 'claude',
                                    label: 'Claude API Key',
                                    field: 'claude_api_key',
                                    hint: 'console.anthropic.com',
                                },
                            ] as const
                        ).map(({ slug, label, field, hint }) => (
                            <div key={slug} className="space-y-2">
                                <Label className="flex items-center gap-2">
                                    {label}
                                    {hasKey[slug] && (
                                        <span className="inline-flex items-center gap-1 rounded-full bg-green-500/10 px-2 py-0.5 text-xs text-green-600 dark:text-green-400">
                                            <Check className="size-3" />{' '}
                                            Tersimpan
                                        </span>
                                    )}
                                </Label>
                                <div className="relative">
                                    <Input
                                        type={
                                            showKeys[slug] ? 'text' : 'password'
                                        }
                                        value={data[field]}
                                        onChange={(e) =>
                                            setData(field, e.target.value)
                                        }
                                        placeholder={
                                            hasKey[slug]
                                                ? '••••••••••••••••'
                                                : `Masukkan ${label}...`
                                        }
                                        className="pr-10 font-mono text-sm"
                                        autoComplete="off"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => toggleShowKey(slug)}
                                        className="absolute top-1/2 right-3 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                    >
                                        {showKeys[slug] ? (
                                            <EyeOff className="size-4" />
                                        ) : (
                                            <Eye className="size-4" />
                                        )}
                                    </button>
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    Dapatkan di {hint}
                                </p>
                                <InputError message={errors[field]} />
                            </div>
                        ))}
                    </section>

                    {/* ── System Prompt ─────────────────────────────────── */}
                    <section className="space-y-4">
                        <h3 className="text-sm font-medium">
                            Persona & System Prompt
                        </h3>
                        <div className="space-y-2">
                            <Label>System Prompt</Label>
                            <Textarea
                                value={data.system_prompt}
                                onChange={(e) =>
                                    setData('system_prompt', e.target.value)
                                }
                                rows={6}
                                className="resize-y font-mono text-sm"
                                placeholder="You are AEVA..."
                            />
                            <p className="text-xs text-muted-foreground">
                                {data.system_prompt.length} karakter
                            </p>
                            <InputError message={errors.system_prompt} />
                        </div>
                    </section>

                    {/* ── Behavior ──────────────────────────────────────── */}
                    <section className="space-y-4">
                        <h3 className="text-sm font-medium">Behavior</h3>
                        <div className="flex items-center justify-between rounded-lg border px-4 py-3">
                            <div>
                                <p className="text-sm font-medium">Streaming</p>
                                <p className="text-xs text-muted-foreground">
                                    Tampilkan respon AI secara real-time saat
                                    diketik
                                </p>
                            </div>
                            <Switch
                                checked={data.streaming_enabled}
                                onCheckedChange={(v: boolean) =>
                                    setData('streaming_enabled', v)
                                }
                            />
                        </div>
                    </section>

                    {/* ── Save ──────────────────────────────────────────── */}
                    <div className="flex items-center gap-3">
                        <Button onClick={submit} disabled={processing}>
                            {processing ? (
                                <Loader2 className="mr-2 size-4 animate-spin" />
                            ) : (
                                <Save className="mr-2 size-4" />
                            )}
                            Simpan
                        </Button>
                        {recentlySuccessful && (
                            <span className="flex items-center gap-1 text-sm text-green-600 dark:text-green-400">
                                <Check className="size-4" /> Tersimpan
                            </span>
                        )}
                    </div>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
