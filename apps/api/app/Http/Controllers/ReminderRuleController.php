<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReminderRuleRequest;
use App\Models\Broadcast;
use App\Models\ReminderRule;
use Illuminate\Http\JsonResponse;

/**
 * Master pengingat perawatan: layanan X diingatkan setelah N hari. Scheduler
 * harian yang memakai aturan ini untuk menyusun broadcast pengingat.
 */
class ReminderRuleController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Broadcast::class);

        return response()->json([
            'data' => ReminderRule::query()->with(['service:id,name', 'template:id,name'])
                ->orderBy('id')->get()
                ->map(fn (ReminderRule $rule) => [
                    'id' => $rule->id,
                    'service_id' => $rule->service_id,
                    'service_name' => $rule->service?->name,
                    'days_after' => $rule->days_after,
                    'message_template_id' => $rule->message_template_id,
                    'template_name' => $rule->template?->name,
                    'is_active' => $rule->is_active,
                ]),
            'meta' => [],
        ]);
    }

    public function store(ReminderRuleRequest $request): JsonResponse
    {
        $this->authorize('create', Broadcast::class);

        $validated = $request->validated();

        $rule = ReminderRule::create($validated);

        return response()->json([
            'data' => ['id' => $rule->id],
            'meta' => ['message' => __('broadcast.reminder_saved')],
        ], 201);
    }

    public function update(ReminderRuleRequest $request, ReminderRule $reminderRule): JsonResponse
    {
        $this->authorize('update', Broadcast::class);

        $validated = $request->validated();

        $reminderRule->update($validated);

        return response()->json([
            'data' => ['id' => $reminderRule->id],
            'meta' => ['message' => __('broadcast.reminder_saved')],
        ]);
    }

    public function destroy(ReminderRule $reminderRule): JsonResponse
    {
        $this->authorize('delete', Broadcast::class);

        $reminderRule->delete();

        return response()->json([
            'data' => null,
            'meta' => ['message' => __('broadcast.reminder_deleted')],
        ]);
    }
}
