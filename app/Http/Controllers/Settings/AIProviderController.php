<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AIProviderController extends Controller
{
    /**
     * Tampilkan halaman settings AI.
     * Data yang dikirim ke frontend: semua provider + models + current settings.
     */
    public function index(): Response
    {
        $providers = AiProvider::with(['models' => function ($q) {
            $q->orderBy('sort_order');
        }])->orderBy('id')->get();

        return Inertia::render('settings/AiConfig', [
            'providers'       => $providers,
            'activeProvider'  => Setting::get('active_provider', 'gemini'),
            'activeModel'     => Setting::get('active_model', 'gemini-2.5-flash'),
            'systemPrompt'    => Setting::get('system_prompt', ''),
            'streamingEnabled' => (bool) Setting::get('streaming_enabled', true),
            'hasGeminiKey'    => Setting::has('gemini_api_key'),
            'hasOpenaiKey'    => Setting::has('openai_api_key'),
            'hasClaudeKey'    => Setting::has('claude_api_key'),
        ]);
    }

    /**
     * Simpan settings AI.
     * API key di-encrypt otomatis via Setting model (type: encrypted).
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'active_provider'  => ['required', 'string', 'exists:ai_providers,slug'],
            'active_model'     => ['required', 'string'],
            'system_prompt'    => ['nullable', 'string', 'max:5000'],
            'streaming_enabled' => ['boolean'],
            'gemini_api_key'   => ['nullable', 'string', 'max:500'],
            'openai_api_key'   => ['nullable', 'string', 'max:500'],
            'claude_api_key'   => ['nullable', 'string', 'max:500'],
        ]);

        // Validasi active_model milik active_provider
        $modelExists = AiModel::whereHas('provider', function ($q) use ($validated) {
            $q->where('slug', $validated['active_provider']);
        })->where('model_key', $validated['active_model'])->exists();

        if (! $modelExists) {
            return back()->withErrors(['active_model' => 'Model tidak valid untuk provider ini.']);
        }

        // Simpan settings
        Setting::set('active_provider', $validated['active_provider']);
        Setting::set('active_model', $validated['active_model']);
        Setting::set('streaming_enabled', $validated['streaming_enabled'] ?? true);

        if (! empty($validated['system_prompt'])) {
            Setting::set('system_prompt', $validated['system_prompt']);
        }

        // API keys — hanya update jika diisi (kosong = tidak ubah)
        if (! empty($validated['gemini_api_key'])) {
            Setting::set('gemini_api_key', $validated['gemini_api_key'], type: 'encrypted');
        }
        if (! empty($validated['openai_api_key'])) {
            Setting::set('openai_api_key', $validated['openai_api_key'], type: 'encrypted');
        }
        if (! empty($validated['claude_api_key'])) {
            Setting::set('claude_api_key', $validated['claude_api_key'], type: 'encrypted');
        }

        return back()->with('success', 'Settings berhasil disimpan.');
    }
}
