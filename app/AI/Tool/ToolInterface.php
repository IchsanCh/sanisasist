<?php

namespace App\AI\Tools;

/**
 * ToolInterface
 *
 * Contract untuk semua AI tools (function calling).
 * Setiap tool harus implement interface ini.
 *
 * Concrete class di-resolve oleh MessageBuilder
 * berdasarkan slug dari tabel tools.
 */
interface ToolInterface
{
    /**
     * Nama tool — harus match dengan slug di tabel tools.
     */
    public function getName(): string;

    /**
     * Deskripsi singkat untuk AI (apa yang tool ini lakukan).
     */
    public function getDescription(): string;

    /**
     * JSON Schema parameters yang diterima tool ini.
     * Dikirim ke AI sebagai function signature.
     *
     * Format mengikuti JSON Schema draft-07:
     * [
     *   'type' => 'object',
     *   'properties' => [
     *     'param_name' => ['type' => 'string', 'description' => '...']
     *   ],
     *   'required' => ['param_name']
     * ]
     */
    public function getParameters(): array;

    /**
     * Eksekusi tool dengan parameter dari AI.
     * Return value akan dikirim kembali ke AI sebagai tool result.
     *
     * @param  array $params  Parameter yang dikirim AI (sudah di-decode dari JSON)
     * @return mixed          Hasil eksekusi — akan di-json_encode sebelum dikirim ke AI
     * @throws \Exception     Jika eksekusi gagal
     */
    public function execute(array $params): mixed;
}
