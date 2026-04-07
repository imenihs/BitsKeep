<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Component;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 案件管理 API（SCR-005）
 * 独自案件CRUD + 使用部品登録 + コスト積算
 * Notion連携は別タスク（NotionSyncController）で実装予定
 */
class ProjectController extends Controller
{
    // GET /api/projects
    public function index(Request $request)
    {
        $query = Project::with(['creator:id,name'])
            ->withCount('components')
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(fn($sub) => $sub
                ->where('name', 'ilike', "%{$q}%")
                ->orWhere('description', 'ilike', "%{$q}%")
            );
        }

        $projects = $query->paginate($request->input('per_page', 20));
        return ApiResponse::success($projects);
    }

    // GET /api/projects/options  （案件選択コンボボックス用）
    public function options()
    {
        $projects = Project::where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'color', 'status']);
        return ApiResponse::success($projects);
    }

    // POST /api/projects
    public function store(Request $request)
    {
        if (! $request->user()->isEditor()) {
            return ApiResponse::forbidden();
        }

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status'      => ['nullable', 'in:active,archived'],
            'color'       => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $validated['created_by'] = $request->user()->id;
        $project = Project::create($validated);

        return ApiResponse::created($project->load('creator:id,name'));
    }

    // GET /api/projects/{project}
    public function show(Project $project)
    {
        $project->load([
            'creator:id,name',
            'components' => fn($q) => $q->with(['categories', 'packages', 'componentSuppliers.priceBreaks']),
        ]);

        // コスト積算
        $costSummary = $this->calcCost($project);

        return ApiResponse::success([
            'project'      => $project,
            'cost_summary' => $costSummary,
        ]);
    }

    // PUT /api/projects/{project}
    public function update(Request $request, Project $project)
    {
        if (! $request->user()->isEditor()) {
            return ApiResponse::forbidden();
        }

        $validated = $request->validate([
            'name'        => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status'      => ['nullable', 'in:active,archived'],
            'color'       => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $project->update($validated);
        return ApiResponse::success($project->load('creator:id,name'));
    }

    // DELETE /api/projects/{project}
    public function destroy(Request $request, Project $project)
    {
        if (! $request->user()->isEditor()) {
            return ApiResponse::forbidden();
        }

        $project->delete();
        return ApiResponse::noContent();
    }

    // ── 使用部品管理 ─────────────────────────────────────

    // GET /api/projects/{project}/components
    public function listComponents(Project $project)
    {
        $components = $project->components()
            ->with(['categories', 'packages', 'componentSuppliers.priceBreaks'])
            ->get()
            ->map(fn($c) => array_merge($c->toArray(), [
                'required_qty'   => $c->pivot->required_qty,
                'cheapest_price' => $c->componentSuppliers->flatMap->priceBreaks->min('unit_price'),
            ]));

        return ApiResponse::success($components);
    }

    // POST /api/projects/{project}/components
    public function addComponent(Request $request, Project $project)
    {
        if (! $request->user()->isEditor()) {
            return ApiResponse::forbidden();
        }

        $validated = $request->validate([
            'component_id' => ['required', 'exists:components,id'],
            'required_qty' => ['required', 'integer', 'min:1'],
        ]);

        $project->components()->syncWithoutDetaching([
            $validated['component_id'] => ['required_qty' => $validated['required_qty']],
        ]);

        return ApiResponse::success(['message' => '部品を追加しました']);
    }

    // PATCH /api/projects/{project}/components/{component}
    public function updateComponent(Request $request, Project $project, Component $component)
    {
        if (! $request->user()->isEditor()) {
            return ApiResponse::forbidden();
        }

        $validated = $request->validate([
            'required_qty' => ['required', 'integer', 'min:1'],
        ]);

        $project->components()->updateExistingPivot($component->id, $validated);
        return ApiResponse::success(['message' => '使用数を更新しました']);
    }

    // DELETE /api/projects/{project}/components/{component}
    public function removeComponent(Request $request, Project $project, Component $component)
    {
        if (! $request->user()->isEditor()) {
            return ApiResponse::forbidden();
        }

        $project->components()->detach($component->id);
        return ApiResponse::noContent();
    }

    // ── コスト積算 ───────────────────────────────────────

    // GET /api/projects/{project}/cost
    public function cost(Project $project)
    {
        $project->load(['components.componentSuppliers.priceBreaks']);
        return ApiResponse::success($this->calcCost($project));
    }

    private function calcCost(Project $project): array
    {
        $items = $project->components->map(function ($comp) {
            $requiredQty   = $comp->pivot->required_qty ?? 1;
            $cheapestPrice = $comp->componentSuppliers->flatMap->priceBreaks->min('unit_price');
            $subtotal      = $cheapestPrice !== null ? $cheapestPrice * $requiredQty : null;

            return [
                'component_id'   => $comp->id,
                'part_number'    => $comp->part_number,
                'common_name'    => $comp->common_name,
                'required_qty'   => $requiredQty,
                'unit_price'     => $cheapestPrice,
                'subtotal'       => $subtotal,
                'has_stock'      => $comp->quantity_new >= $requiredQty,
            ];
        });

        $total        = $items->sum('subtotal');
        $unknownCount = $items->whereNull('unit_price')->count();

        return [
            'items'         => $items->values(),
            'total'         => $total,
            'unknown_count' => $unknownCount,
            'warning'       => $unknownCount > 0 ? "単価未設定の部品が{$unknownCount}件あります" : null,
        ];
    }
}
