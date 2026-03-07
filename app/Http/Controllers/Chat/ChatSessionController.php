<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Models\ChatSession;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChatSessionController extends Controller
{
    /**
     * Halaman utama chat — tampilkan semua sessions + session aktif.
     * Jika ada {session} di URL, load session itu. Kalau tidak, ambil yang terbaru.
     */
    public function index(Request $request): Response
    {
        $user     = $request->user();
        $sessions = ChatSession::where('user_id', $user->id)
            ->orderByDesc('updated_at')
            ->get(['id', 'title', 'created_at', 'updated_at']);

        return Inertia::render('chat/Index', [
            'sessions'          => $sessions,
            'activeSession'     => null,
            'messages'          => [],
        ]);
    }

    /**
     * Tampilkan satu session beserta semua messages-nya.
     */
    public function show(Request $request, ChatSession $session): Response
    {
        abort_if($session->user_id !== $request->user()->id, 403);

        $sessions = ChatSession::where('user_id', $request->user()->id)
            ->orderByDesc('updated_at')
            ->get(['id', 'title', 'created_at', 'updated_at']);

        $messages = $session->messages()
            ->conversational()
            ->orderBy('created_at')
            ->get(['id', 'role', 'content', 'tokens', 'model_used', 'created_at']);

        return Inertia::render('chat/Index', [
            'sessions'      => $sessions,
            'activeSession' => $session->only(['id', 'title', 'metadata']),
            'messages'      => $messages,
        ]);
    }

    /**
     * Buat session baru dan redirect ke sana.
     */
    public function store(Request $request)
    {
        $session = ChatSession::create([
            'user_id' => $request->user()->id,
            'title'   => 'New Chat',
        ]);

        return redirect()->route('chat.show', $session->id);
    }

    /**
     * Rename judul session.
     */
    public function update(Request $request, ChatSession $session)
    {
        abort_if($session->user_id !== $request->user()->id, 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $session->update(['title' => $validated['title']]);

        return back();
    }

    /**
     * Hapus session beserta semua messages-nya.
     * onDelete cascade sudah diset di migration.
     */
    public function destroy(Request $request, ChatSession $session)
    {
        abort_if($session->user_id !== $request->user()->id, 403);

        $session->delete();

        return redirect()->route('chat.index');
    }
}
