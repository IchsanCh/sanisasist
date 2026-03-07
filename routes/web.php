<?php

use Laravel\Fortify\Features;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('chat.index')
        : inertia('welcome', [
            'canRegister' => Features::enabled(Features::registration()),
        ]);
})->name('home');

// ── Chat ──────────────────────────────────────────────────────────────────────
require __DIR__ . '/chat.php';

// ── Settings ──────────────────────────────────────────────────────────────────
require __DIR__ . '/settings.php';
