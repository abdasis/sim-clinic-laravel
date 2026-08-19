<?php

namespace App\Http\Controllers;

use App\Http\Requests\MessageTemplateRequest;
use App\Models\Broadcast;
use App\Models\MessageTemplate;
use Illuminate\Http\JsonResponse;

/**
 * Templat pesan WhatsApp yang bisa dipakai ulang oleh broadcast dan aturan
 * pengingat. CRUD tipis — tanpa Service karena tiap operasi satu baris dan
 * tidak menyentuh entitas lain.
 */
class MessageTemplateController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Broadcast::class);

        return response()->json([
            'data' => MessageTemplate::query()->orderBy('name')
                ->get(['id', 'name', 'body']),
            'meta' => [],
        ]);
    }

    public function store(MessageTemplateRequest $request): JsonResponse
    {
        $this->authorize('create', Broadcast::class);

        $validated = $request->validated();

        $template = MessageTemplate::create($validated);

        return response()->json([
            'data' => $template->only(['id', 'name', 'body']),
            'meta' => ['message' => __('broadcast.template_saved')],
        ], 201);
    }

    public function update(MessageTemplateRequest $request, MessageTemplate $messageTemplate): JsonResponse
    {
        $this->authorize('update', Broadcast::class);

        $validated = $request->validated();

        $messageTemplate->update($validated);

        return response()->json([
            'data' => $messageTemplate->only(['id', 'name', 'body']),
            'meta' => ['message' => __('broadcast.template_saved')],
        ]);
    }

    public function destroy(MessageTemplate $messageTemplate): JsonResponse
    {
        $this->authorize('delete', Broadcast::class);

        $messageTemplate->delete();

        return response()->json([
            'data' => null,
            'meta' => ['message' => __('broadcast.template_deleted')],
        ]);
    }
}
