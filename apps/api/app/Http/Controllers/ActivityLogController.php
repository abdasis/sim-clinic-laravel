<?php

namespace App\Http\Controllers;

use App\Http\Concerns\InteractsWithDataTable;
use App\Http\Resources\ActivityResource;
use App\Models\Activity;
use App\Models\User;
use App\Scopes\TenantScope;
use App\Support\ActivityLabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Log aktivitas klinik — baca saja.
 *
 * Barisnya ditulis Action lewat LogAuditAction, tidak pernah dari sini, jadi
 * controller ini tidak lewat Service sama sekali (pengecualian baca pada
 * lapisan Controller -> Service -> Action).
 */
class ActivityLogController extends Controller
{
    use InteractsWithDataTable;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Activity::class);

        $params = $this->dataTableParams($request);

        $query = $this->scoped()
            ->with(['causer', 'subject'])
            ->betweenDates($params['filters']['from'] ?? null, $params['filters']['to'] ?? null);

        // Penyaring faceted mengirim pilihan ganda sebagai "a,b" — satu
        // pilihan pun lewat jalur yang sama supaya tidak ada dua bentuk.
        $columns = ['module' => 'log_name', 'event' => 'event', 'causer_id' => 'causer_id'];

        foreach ($columns as $filter => $column) {
            $values = $this->filterValues($params['filters'][$filter] ?? null);

            if ($values !== []) {
                $query->whereIn($column, $values);
            }
        }

        if ($params['search']) {
            $term = '%'.$params['search'].'%';

            $query->where(fn (Builder $inner) => $inner
                ->where('description', 'like', $term)
                ->orWhere('event', 'like', $term)
                ->orWhereHas('causer', fn (Builder $user) => $user->where('name', 'like', $term)));
        }

        // Log dibaca dari yang terbaru; urutan lain hanya kalau diminta.
        if ($params['sort']) {
            $query->orderBy($params['sort'], $params['direction']);
        } else {
            $query->orderByDesc('created_at')->orderByDesc('id');
        }

        $page = $query->paginate($params['per_page'], ['*'], 'page', $params['page']);

        return response()->json([
            'data' => ActivityResource::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function show(int $activity): JsonResponse
    {
        $this->authorize('viewAny', Activity::class);

        // Sengaja tidak memakai route model binding: binding melewati
        // penyaring tenant, sehingga id milik klinik lain akan terbaca.
        $record = $this->scoped()->with(['causer', 'subject'])->findOrFail($activity);

        return response()->json([
            'data' => new ActivityResource($record),
            'meta' => [],
        ]);
    }

    /**
     * Isi penyaring: hanya modul, aksi, dan pelaku yang benar-benar pernah
     * muncul di log klinik ini — daftar statis akan penuh pilihan kosong.
     */
    public function filters(): JsonResponse
    {
        $this->authorize('viewAny', Activity::class);

        $modules = $this->scoped()
            ->whereNotNull('log_name')
            ->distinct()
            ->orderBy('log_name')
            ->pluck('log_name');

        $events = $this->scoped()
            ->whereNotNull('event')
            ->distinct()
            ->orderBy('event')
            ->pluck('event');

        // Id-nya sudah berasal dari baris log klinik ini, jadi aman membaca
        // user tanpa TenantScope — tanpa itu pelaku lintas klinik (admin
        // platform yang membuat kliniknya) hilang dari daftar penyaring.
        $causerIds = $this->scoped()->whereNotNull('causer_id')->distinct()->pluck('causer_id');

        $causers = User::query()
            ->withoutGlobalScope(TenantScope::class)
            ->whereIn('id', $causerIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'data' => [
                'modules' => $modules->map(fn (string $module) => [
                    'value' => $module,
                    'label' => ActivityLabel::module($module),
                ])->values(),
                'events' => $events->map(fn (string $event) => [
                    'value' => $event,
                    'label' => ActivityLabel::event($event),
                    'module' => str_contains($event, '.') ? strtok($event, '.') : null,
                ])->values(),
                'causers' => $causers->map(fn (User $user) => [
                    'value' => (string) $user->id,
                    'label' => $user->name,
                ])->values(),
            ],
            'meta' => [],
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function filterValues(?string $raw): array
    {
        if ($raw === null || $raw === '' || $raw === 'all') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    /**
     * @return Builder<Activity>
     */
    private function scoped(): Builder
    {
        return Activity::query()->forTenant(app('tenant')->id);
    }
}
