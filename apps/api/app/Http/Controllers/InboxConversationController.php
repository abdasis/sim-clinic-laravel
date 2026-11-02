<?php

namespace App\Http\Controllers;

use App\Enums\ConversationChannel;
use App\Http\Concerns\InteractsWithDataTable;
use App\Http\Requests\SendInboxContactRequest;
use App\Http\Requests\SendInboxMediaRequest;
use App\Http\Requests\SendInboxMessageRequest;
use App\Http\Requests\SendInboxReactionRequest;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\InboxService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Kotak masuk WhatsApp klinik: daftar utas, isi percakapan, balasan manual,
 * dan penanda sudah dibaca. Daftar dan detail dibaca langsung dari model;
 * hanya aksi yang mengubah data yang lewat Service.
 */
class InboxConversationController extends Controller
{
    use InteractsWithDataTable;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Conversation::class);

        $params = $this->dataTableParams($request);

        $query = Conversation::query()->with('latestMessage');

        $channel = ConversationChannel::tryFrom((string) ($params['filters']['channel'] ?? ''));

        if ($channel !== null) {
            $query->where('channel', $channel);
        }

        if ($params['search'] !== null) {
            $search = '%'.$params['search'].'%';

            $query->where(function (Builder $query) use ($search): void {
                $query->where('contact_name', 'like', $search)
                    ->orWhere('contact_phone', 'like', $search);
            });
        }

        if (! $this->applyAllowedSort($query, $params, ['last_message_at', 'unread_count', 'contact_name', 'created_at'])) {
            $query->orderByDesc('last_message_at');
        }

        $page = $query->paginate($params['per_page'], ['*'], 'page', $params['page']);

        return response()->json([
            'data' => ConversationResource::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $perPage = max(1, min(100, (int) $request->integer('per_page', 30)));
        $pageNumber = max(1, (int) $request->integer('page', 1));

        $messages = $conversation->messages()
            ->with('replyTo')
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $pageNumber);

        // Disisipkan sebagai relasi supaya bentuknya sama dengan saat Resource
        // memuat relasi, tanpa mengubah urutan halaman yang sudah dibentuk.
        $conversation->setRelation('messages', $messages->getCollection());

        return response()->json([
            'data' => new ConversationResource($conversation),
            'meta' => [
                'current_page' => $messages->currentPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
                'last_page' => $messages->lastPage(),
            ],
        ]);
    }

    public function storeMessage(
        SendInboxMessageRequest $request,
        Conversation $conversation,
        InboxService $inbox,
    ): JsonResponse {
        $this->authorize('update', $conversation);

        $message = $inbox->send(
            $conversation,
            $request->validated('body'),
            $request->validated('reply_to_message_id'),
        );

        return response()->json([
            'data' => new MessageResource($message),
            'meta' => ['message' => __('inbox.sent')],
        ], 201);
    }

    public function storeReaction(
        SendInboxReactionRequest $request,
        Conversation $conversation,
        Message $message,
        InboxService $inbox,
    ): JsonResponse {
        $this->authorize('update', $conversation);

        $message = $inbox->react($conversation, $message, $request->validated('emoji'));

        return response()->json([
            'data' => new MessageResource($message),
            'meta' => [],
        ]);
    }

    public function storeMedia(
        SendInboxMediaRequest $request,
        Conversation $conversation,
        InboxService $inbox,
    ): JsonResponse {
        $this->authorize('update', $conversation);

        $message = $inbox->sendMedia(
            $conversation,
            $request->validated('type'),
            $request->file('file'),
            $request->validated('caption'),
        );

        return response()->json([
            'data' => new MessageResource($message),
            'meta' => [],
        ], 201);
    }

    public function storeContact(
        SendInboxContactRequest $request,
        Conversation $conversation,
        InboxService $inbox,
    ): JsonResponse {
        $this->authorize('update', $conversation);

        $message = $inbox->sendContact(
            $conversation,
            $request->validated('name'),
            $request->validated('phone'),
        );

        return response()->json([
            'data' => new MessageResource($message),
            'meta' => [],
        ], 201);
    }

    public function markRead(Conversation $conversation, InboxService $inbox): JsonResponse
    {
        $this->authorize('update', $conversation);

        $conversation = $inbox->markRead($conversation);

        return response()->json([
            'data' => new ConversationResource($conversation),
            'meta' => ['message' => __('inbox.read')],
        ]);
    }
}
