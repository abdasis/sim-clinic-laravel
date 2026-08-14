<?php

namespace App\Http\Controllers;

use App\Http\Concerns\InteractsWithDataTable;
use App\Http\Requests\CompanyProfile\MediaUploadRequest;
use App\Http\Requests\CompanyProfile\ReorderRequest;
use App\Http\Requests\CompanyProfile\SettingsRequest;
use App\Http\Resources\CompanyProfile\CompanyProfileSettingResource;
use App\Models\CompanyProfileSetting;
use App\Services\CompanyProfileService;
use App\Support\CompanyContentRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * CMS company profile. Delapan entitas konten dilayani satu controller lewat
 * `CompanyContentRegistry` — perlakuannya identik, jadi menyalin delapan
 * controller yang sama hanya menambah tempat untuk salah.
 */
class CompanyContentController extends Controller
{
    use InteractsWithDataTable;

    public function index(Request $request, string $entity): JsonResponse
    {
        $config = CompanyContentRegistry::get($entity);

        $this->authorize('viewAny', $config['model']);

        $params = $this->dataTableParams($request);
        $query = $config['model']::query();

        if ($params['search']) {
            $this->applySearch($query, $config['searchable'], $params['search']);
        }

        $query->orderBy($params['sort'] ?? 'sort_order', $params['direction'])->orderBy('id');

        $page = $query->paginate($params['per_page'], ['*'], 'page', $params['page']);

        return response()->json([
            'data' => $config['resource']::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function show(string $entity, int $content): JsonResponse
    {
        $config = CompanyContentRegistry::get($entity);

        $this->authorize('view', $config['model']);

        $record = $config['model']::query()->findOrFail($content);

        return response()->json([
            'data' => new $config['resource']($record),
            'meta' => [],
        ]);
    }

    public function store(Request $request, string $entity, CompanyProfileService $service): JsonResponse
    {
        $config = CompanyContentRegistry::get($entity);

        $this->authorize('create', $config['model']);

        $data = $this->validated($request, $config['request']);
        $record = $service->create($config['model'], $entity, $data);

        return response()->json([
            'data' => new $config['resource']($record),
            'meta' => ['message' => __('company_profile.created')],
        ], 201);
    }

    public function update(Request $request, string $entity, int $content, CompanyProfileService $service): JsonResponse
    {
        $config = CompanyContentRegistry::get($entity);

        $this->authorize('update', $config['model']);

        $record = $config['model']::query()->findOrFail($content);
        $data = $this->validated($request, $config['request']);

        $service->update($record, $entity, $data);

        return response()->json([
            'data' => new $config['resource']($record->fresh()),
            'meta' => ['message' => __('company_profile.updated')],
        ]);
    }

    public function destroy(string $entity, int $content, CompanyProfileService $service): JsonResponse
    {
        $config = CompanyContentRegistry::get($entity);

        $this->authorize('delete', $config['model']);

        $record = $config['model']::query()->findOrFail($content);

        $service->delete($record, $entity);

        return response()->json(['data' => null, 'meta' => ['message' => __('company_profile.deleted')]]);
    }

    public function toggle(string $entity, int $content, CompanyProfileService $service): JsonResponse
    {
        $config = CompanyContentRegistry::get($entity);

        $this->authorize('update', $config['model']);

        $record = $config['model']::query()->findOrFail($content);

        $service->toggleActive($record, $entity);

        return response()->json([
            'data' => new $config['resource']($record->fresh()),
            'meta' => ['message' => __('company_profile.updated')],
        ]);
    }

    public function reorder(ReorderRequest $request, string $entity, CompanyProfileService $service): JsonResponse
    {
        $config = CompanyContentRegistry::get($entity);

        $this->authorize('update', $config['model']);

        $service->reorder($config['model'], $entity, $request->validated('ids'));

        return response()->json(['data' => null, 'meta' => ['message' => __('company_profile.reordered')]]);
    }

    public function settings(): JsonResponse
    {
        $this->authorize('viewAny', CompanyProfileSetting::class);

        $settings = CompanyProfileSetting::query()->where('tenant_id', app('tenant')->id)->first();

        return response()->json([
            'data' => $settings ? new CompanyProfileSettingResource($settings) : null,
            'meta' => [],
        ]);
    }

    public function updateSettings(SettingsRequest $request, CompanyProfileService $service): JsonResponse
    {
        $this->authorize('update', CompanyProfileSetting::class);

        $settings = $service->updateSettings(app('tenant'), $request->validated());

        return response()->json([
            'data' => new CompanyProfileSettingResource($settings),
            'meta' => ['message' => __('company_profile.settings_updated')],
        ]);
    }

    public function togglePublish(CompanyProfileService $service): JsonResponse
    {
        $this->authorize('update', CompanyProfileSetting::class);

        $settings = $service->togglePublish(app('tenant'));

        return response()->json([
            'data' => new CompanyProfileSettingResource($settings),
            'meta' => [
                'message' => $settings->is_published
                    ? __('company_profile.published')
                    : __('company_profile.unpublished'),
            ],
        ]);
    }

    public function upload(MediaUploadRequest $request, CompanyProfileService $service): JsonResponse
    {
        $this->authorize('update', CompanyProfileSetting::class);

        $path = $service->uploadMedia(
            app('tenant'),
            $request->validated('entity'),
            $request->file('file'),
        );

        return response()->json([
            'data' => ['path' => $path, 'url' => Storage::disk('public')->url($path)],
            'meta' => ['message' => __('company_profile.media_uploaded')],
        ], 201);
    }

    /**
     * FormRequest per entitas dipilih saat runtime, jadi tidak bisa lewat
     * type-hint. `validateResolved` menjalankan otorisasi dan aturannya
     * persis seperti kalau di-inject biasa.
     *
     * @param  class-string<FormRequest>  $requestClass
     * @return array<string, mixed>
     */
    private function validated(Request $request, string $requestClass): array
    {
        $formRequest = $requestClass::createFrom($request);
        $formRequest->setContainer(app())->setRedirector(app('redirect'));
        $formRequest->validateResolved();

        return $formRequest->validated();
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<int, string>  $columns
     */
    private function applySearch(Builder $query, array $columns, string $keyword): void
    {
        $query->where(function (Builder $inner) use ($columns, $keyword): void {
            foreach ($columns as $column) {
                // Kolom peta bahasa dicari sebagai teks JSON mentah — cukup
                // untuk daftar CMS, dan tetap jalan di SQLite maupun pgsql.
                $inner->orWhere($column, 'like', '%'.$keyword.'%');
            }
        });
    }
}
