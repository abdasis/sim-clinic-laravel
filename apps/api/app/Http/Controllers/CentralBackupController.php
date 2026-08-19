<?php

namespace App\Http\Controllers;

use App\Services\BackupService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Arsip cadangan basis data untuk pengelola platform.
 *
 * Satu dump memuat data seluruh klinik, jadi ia tidak boleh pernah muncul di
 * bawah rute tenant — admin klinik mana pun akan memegang data klinik lain.
 */
class CentralBackupController extends Controller
{
    public function index(BackupService $backups): JsonResponse
    {
        $this->guard();

        return response()->json(['data' => $backups->list(), 'meta' => []]);
    }

    public function store(BackupService $backups): JsonResponse
    {
        $this->guard();

        $result = $backups->run(auth()->user());

        if ($result['error'] !== null) {
            return response()->json([
                'message' => __('backup.create_failed'),
                'errors' => ['backup' => [$result['error']]],
            ], 422);
        }

        return response()->json([
            'data' => [
                'file' => $result['file'],
                'size' => $result['size'],
                'created_at' => $result['created_at'],
            ],
            'meta' => ['message' => __('backup.created')],
        ], 201);
    }

    public function download(string $filename, BackupService $backups): StreamedResponse
    {
        $this->guard();

        return $backups->download($filename);
    }

    public function destroy(string $filename, BackupService $backups): JsonResponse
    {
        $this->guard();

        $backups->delete($filename, auth()->user());

        return response()->json(['data' => null, 'meta' => ['message' => __('backup.deleted')]]);
    }

    private function guard(): void
    {
        if (! auth()->user()?->isPlatformAdmin()) {
            abort(403, __('auth.unauthorized'));
        }
    }
}
