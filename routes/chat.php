<?php

use App\Http\Controllers\Chat\ChatMessageController;
use App\Http\Controllers\Chat\ChatSessionController;
use App\Http\Controllers\Settings\AIProviderController;
use Illuminate\Support\Facades\Route;

// ── Chat Routes ───────────────────────────────────────────────────────────────
Route::middleware(['auth', 'verified'])->group(function () {

    // ── Sessions ──────────────────────────────────────────────────────────
    Route::prefix('chat')->name('chat.')->group(function () {

        // Halaman utama chat (sidebar + empty state)
        Route::get('/', [ChatSessionController::class, 'index'])
            ->name('index');

        // Buat session baru
        Route::post('/sessions', [ChatSessionController::class, 'store'])
            ->name('sessions.store');

        // Tampilkan session + messages
        Route::get('/sessions/{session}', [ChatSessionController::class, 'show'])
            ->name('show');

        // Rename judul session
        Route::put('/sessions/{session}', [ChatSessionController::class, 'update'])
            ->name('sessions.update');

        // Hapus session
        Route::delete('/sessions/{session}', [ChatSessionController::class, 'destroy'])
            ->name('sessions.destroy');

        // ── Messages ──────────────────────────────────────────────────────
        // Kirim pesan (non-streaming / fallback)
        Route::post('/sessions/{session}/messages', [ChatMessageController::class, 'store'])
            ->name('messages.store');

        // Kirim pesan via SSE streaming
        Route::post('/sessions/{session}/messages/stream', [ChatMessageController::class, 'stream'])
            ->name('messages.stream');
    });

    // ── AI Settings ───────────────────────────────────────────────────────
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/ai', [AIProviderController::class, 'index'])
            ->name('ai.index');

        Route::post('/ai', [AIProviderController::class, 'update'])
            ->name('ai.update');
    });
});

// ── Single User Mode — block registrasi jika sudah ada user ──────────────────
// Pasang middleware ini di FortifyServiceProvider atau di sini tergantung setup Fortify
// Contoh override route register bawaan Fortify:
Route::middleware(['web', \App\Http\Middleware\SingleUserMode::class])
    ->group(function () {
        // Route register sudah dihandle Fortify, middleware ini akan intercept sebelumnya
        // Tidak perlu define ulang route-nya — cukup daftarkan middleware di bootstrap/app.php
    });
