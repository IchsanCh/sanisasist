<?php

namespace App\Jobs;

use App\AI\Services\ConversationSummaryService;
use App\Models\ChatSession;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * SummarizeConversationJob
 *
 * Queue job yang men-trigger AI summarization secara async.
 * Di-dispatch oleh ChatService setiap kali message count % 5 === 0.
 *
 * Berjalan di background (database queue driver) sehingga
 * tidak memperlambat response chat ke user.
 *
 * Jalankan queue worker:
 *   php artisan queue:work
 */
class SummarizeConversationJob implements ShouldQueue
{
    use Queueable;
    use InteractsWithQueue;
    use SerializesModels;

    /**
     * Maksimum percobaan jika job gagal.
     */
    public int $tries = 3;

    /**
     * Timeout per attempt dalam detik.
     */
    public int $timeout = 60;

    public function __construct(
        private readonly int $sessionId,
    ) {
    }

    public function handle(ConversationSummaryService $summaryService): void
    {
        $session = ChatSession::find($this->sessionId);

        if (! $session) {
            // Session sudah dihapus — tidak perlu retry
            $this->delete();
            return;
        }

        $summaryService->summarize($session);
    }

    /**
     * Jika job gagal setelah semua retry habis.
     */
    public function failed(\Throwable $exception): void
    {
        \Illuminate\Support\Facades\Log::error('SummarizeConversationJob permanently failed', [
            'session_id' => $this->sessionId,
            'error'      => $exception->getMessage(),
        ]);
    }
}
