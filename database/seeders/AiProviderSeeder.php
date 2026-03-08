<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AiProviderSeeder extends Seeder
{
    /**
     * Seed default AI providers, their models, and application settings.
     *
     * Run with: php artisan db:seed --class=AiProviderSeeder
     */
    public function run(): void
    {
        $this->seedProviders();
        $this->seedSettings();
        $this->seedTools();
    }

    private function seedProviders(): void
    {
        $providers = [
            [
                'name'     => 'Google Gemini',
                'slug'     => 'gemini',
                'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
                'active'   => true,
                'models' => [
                    [
                        'name'               => 'Gemini 2.5 Flash',
                        'model_key'          => 'gemini-2.5-flash',      // ← default, free tier
                        'supports_image'     => true,
                        'supports_file'      => true,
                        'supports_tools'     => true,
                        'supports_streaming' => true,
                        'context_window'     => 1048576,
                        'sort_order'         => 1,
                    ],
                    [
                        'name'               => 'Gemini 2.5 Flash-Lite',
                        'model_key'          => 'gemini-2.5-flash-lite',  // ← paling murah & cepat
                        'supports_image'     => true,
                        'supports_file'      => true,
                        'supports_tools'     => true,
                        'supports_streaming' => true,
                        'context_window'     => 1048576,
                        'sort_order'         => 2,
                    ],
                    [
                        'name'               => 'Gemini 3 Flash Preview',
                        'model_key'          => 'gemini-3-flash-preview',         // ← paling capable, limit ketat
                        'supports_image'     => true,
                        'supports_file'      => true,
                        'supports_tools'     => true,
                        'supports_streaming' => true,
                        'context_window'     => 1048576,
                        'sort_order'         => 3,
                    ],
                ],
            ],
            [
                'name'     => 'OpenAI',
                'slug'     => 'openai',
                'base_url' => 'https://api.openai.com/v1',
                'active'   => true,
                'models'   => [
                    [
                        'name'              => 'GPT-4o',
                        'model_key'         => 'gpt-4o',
                        'supports_image'    => true,
                        'supports_file'     => false,
                        'supports_tools'    => true,
                        'supports_streaming' => true,
                        'context_window'    => 128000,
                        'sort_order'        => 1,
                    ],
                    [
                        'name'              => 'GPT-4o Mini',
                        'model_key'         => 'gpt-4o-mini',
                        'supports_image'    => true,
                        'supports_file'     => false,
                        'supports_tools'    => true,
                        'supports_streaming' => true,
                        'context_window'    => 128000,
                        'sort_order'        => 2,
                    ],
                    [
                        'name'              => 'o1',
                        'model_key'         => 'o1',
                        'supports_image'    => true,
                        'supports_file'     => false,
                        'supports_tools'    => false,
                        'supports_streaming' => false,
                        'context_window'    => 200000,
                        'sort_order'        => 3,
                    ],
                ],
            ],
            [
                'name'     => 'Anthropic Claude',
                'slug'     => 'claude',
                'base_url' => 'https://api.anthropic.com/v1',
                'active'   => true,
                'models'   => [
                    [
                        'name'              => 'Claude Sonnet 4.5',
                        'model_key'         => 'claude-sonnet-4-5',
                        'supports_image'    => true,
                        'supports_file'     => true,
                        'supports_tools'    => true,
                        'supports_streaming' => true,
                        'context_window'    => 200000,
                        'sort_order'        => 1,
                    ],
                    [
                        'name'              => 'Claude Haiku 4.5',
                        'model_key'         => 'claude-haiku-4-5-20251001',
                        'supports_image'    => true,
                        'supports_file'     => true,
                        'supports_tools'    => true,
                        'supports_streaming' => true,
                        'context_window'    => 200000,
                        'sort_order'        => 2,
                    ],
                ],
            ],
        ];

        foreach ($providers as $providerData) {
            $models = $providerData['models'];
            unset($providerData['models']);

            $provider = DB::table('ai_providers')->updateOrInsert(
                ['slug' => $providerData['slug']],
                array_merge($providerData, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );

            // Get the provider ID for model insertion
            $providerId = DB::table('ai_providers')
                            ->where('slug', $providerData['slug'])
                            ->value('id');

            foreach ($models as $model) {
                DB::table('ai_models')->updateOrInsert(
                    [
                        'provider_id' => $providerId,
                        'model_key'   => $model['model_key'],
                    ],
                    array_merge($model, [
                        'provider_id' => $providerId,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ])
                );
            }
        }

        $this->command->info('✓ AI Providers and Models seeded.');
    }

    private function seedSettings(): void
    {
        // Get the default Gemini Flash model ID
        $defaultProviderId = DB::table('ai_providers')->where('slug', 'gemini')->value('id');
        $defaultModelKey   = 'gemini-2.5-flash';

        $settings = [
            // ── Active provider & model ───────────────────────────────────
            [
                'key'   => 'active_provider',
                'value' => 'gemini',
                'type'  => 'string',
            ],
            [
                'key'   => 'active_model',
                'value' => $defaultModelKey,
                'type'  => 'string',
            ],
            [
                'key'   => 'system_prompt',
                'value' => 'You are AEVA (Adaptive Empathic Virtual Assistant), a helpful and intelligent AI assistant. You provide clear, accurate, and thoughtful responses. When writing code, always use proper formatting with code blocks. When you don\'t know something, say so honestly.',
                'type'  => 'string',
            ],
            // ── API Keys (empty by default — user fills these in Settings) ─
            [
                'key'   => 'gemini_api_key',
                'value' => null,
                'type'  => 'encrypted',
            ],
            [
                'key'   => 'openai_api_key',
                'value' => null,
                'type'  => 'encrypted',
            ],
            [
                'key'   => 'claude_api_key',
                'value' => null,
                'type'  => 'encrypted',
            ],

            // ── Application behavior ───────────────────────────────────────
            [
                'key'   => 'single_user_mode',
                'value' => 'true',
                'type'  => 'boolean',
            ],
            [
                'key'   => 'streaming_enabled',
                'value' => 'true',
                'type'  => 'boolean',
            ],
            [
                'key'   => 'max_context_messages',
                'value' => '5',
                'type'  => 'integer',
            ],
            [
                'key'   => 'summary_trigger_count',
                'value' => '5',
                'type'  => 'integer',
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->updateOrInsert(
                [
                    'user_id' => null,
                    'key'     => $setting['key'],
                ],
                array_merge($setting, [
                    'user_id'    => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        $this->command->info('✓ Default settings seeded.');
    }

    private function seedTools(): void
    {
        $tools = [
            [
                'name'        => 'Database Query',
                'slug'        => 'database_query',
                'description' => 'Allows the AI to query the application database to retrieve information when the user asks about internal data. Only SELECT queries are permitted.',
                'enabled'     => true,
            ],
        ];

        foreach ($tools as $tool) {
            DB::table('tools')->updateOrInsert(
                ['slug' => $tool['slug']],
                array_merge($tool, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        $this->command->info('✓ Tools seeded.');
    }
}
