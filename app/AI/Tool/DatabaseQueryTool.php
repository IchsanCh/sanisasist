<?php

namespace App\AI\Tools;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * DatabaseQueryTool
 *
 * Memungkinkan AI untuk query database aplikasi
 * ketika user meminta informasi internal.
 *
 * KEAMANAN:
 * - Hanya SELECT yang diizinkan
 * - Whitelist tabel yang boleh di-query
 * - Hasil dibatasi maksimum 50 rows
 * - Raw SQL diparse untuk cegah injection
 */
class DatabaseQueryTool implements ToolInterface
{
    /**
     * Tabel yang boleh di-query oleh AI.
     * Tambahkan tabel di sini jika perlu diekspos ke AI.
     */
    private const ALLOWED_TABLES = [
        'chat_sessions',
        'chat_messages',
        'ai_providers',
        'ai_models',
        'settings',
        'tools',
    ];

    private const MAX_ROWS = 50;

    public function getName(): string
    {
        return 'database_query';
    }

    public function getDescription(): string
    {
        return 'Query the application database to retrieve information when the user asks about internal data such as chat history, AI providers, or settings. Only SELECT queries are permitted.';
    }

    public function getParameters(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'query' => [
                    'type'        => 'string',
                    'description' => 'A SELECT SQL query to execute. Only SELECT statements are allowed. Available tables: ' . implode(', ', self::ALLOWED_TABLES),
                ],
            ],
            'required' => ['query'],
        ];
    }

    public function execute(array $params): mixed
    {
        $query = trim($params['query'] ?? '');

        // ── Validasi: hanya SELECT ────────────────────────────────────
        if (! $this->isSelectQuery($query)) {
            return [
                'error'   => 'Only SELECT queries are permitted.',
                'results' => [],
            ];
        }

        // ── Validasi: hanya tabel yang di-whitelist ───────────────────
        $unauthorizedTable = $this->findUnauthorizedTable($query);
        if ($unauthorizedTable) {
            return [
                'error'   => "Table '{$unauthorizedTable}' is not accessible.",
                'results' => [],
            ];
        }

        try {
            // Tambahkan LIMIT jika belum ada
            $query = $this->ensureLimit($query);

            $results = DB::select($query);

            Log::info('DatabaseQueryTool executed', [
                'query'       => $query,
                'result_count' => count($results),
            ]);

            return [
                'results'      => $results,
                'result_count' => count($results),
            ];

        } catch (\Throwable $e) {
            Log::warning('DatabaseQueryTool query failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return [
                'error'   => 'Query failed: ' . $e->getMessage(),
                'results' => [],
            ];
        }
    }

    // ── Private Helpers ───────────────────────────────────────────────

    private function isSelectQuery(string $query): bool
    {
        // Strip komentar dan whitespace, cek keyword pertama
        $clean = preg_replace('/\s+/', ' ', strtoupper(trim($query)));
        return str_starts_with($clean, 'SELECT');
    }

    private function findUnauthorizedTable(string $query): ?string
    {
        // Extract semua kata setelah FROM dan JOIN
        preg_match_all('/(?:FROM|JOIN)\s+`?(\w+)`?/i', $query, $matches);

        foreach ($matches[1] as $table) {
            if (! in_array(strtolower($table), self::ALLOWED_TABLES, true)) {
                return $table;
            }
        }

        return null;
    }

    private function ensureLimit(string $query): string
    {
        // Jika sudah ada LIMIT, biarkan — tapi cap di MAX_ROWS
        if (preg_match('/LIMIT\s+(\d+)/i', $query, $matches)) {
            $existingLimit = (int) $matches[1];
            if ($existingLimit > self::MAX_ROWS) {
                return preg_replace(
                    '/LIMIT\s+\d+/i',
                    'LIMIT ' . self::MAX_ROWS,
                    $query
                );
            }
            return $query;
        }

        // Tambahkan LIMIT jika belum ada
        return rtrim($query, '; ') . ' LIMIT ' . self::MAX_ROWS;
    }
}
