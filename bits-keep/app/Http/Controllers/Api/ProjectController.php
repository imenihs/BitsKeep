<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Component;
use App\Models\Project;
use App\Models\ProjectSyncRun;
use App\Services\AppSettingService;
use App\Services\NotionSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 案件管理 API（SCR-005）
 * 独自案件CRUD + 使用部品登録 + コスト積算
 * Notion連携は別タスク（NotionSyncController）で実装予定
 */
class ProjectController extends Controller
{
    /**
     * 目的: 案件の一覧を検索条件付きで返す。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function index(Request $request)
    {
        $query = Project::with(['creator:id,name'])
            ->withCount('components')
            ->orderBy('business_code')
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('source_type')) {
            $query->where('source_type', $request->source_type);
        }

        if ($request->filled('business_code')) {
            $query->where('business_code', $request->business_code);
        }

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where( fn($sub) => $sub
                ->where('name',           'ilike', "%{$q}%")
                ->orWhere('description',  'ilike', "%{$q}%")
                ->orWhere('business_name','ilike', "%{$q}%")
                ->orWhere('external_code','ilike', "%{$q}%")
            );
        }

        $projects = $query->paginate($request->input('per_page', 20));
        return ApiResponse::success($projects);
    }

    /**
     * 目的: 案件のoptionsを処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function options(Request $request)
    {
        $query = Project::where('status', 'active')
            ->orderBy('business_code')
            ->orderBy('name');

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where( fn($sub) => $sub
                ->where('name',          'ilike', "%{$q}%")
                ->orWhere('business_name', 'ilike', "%{$q}%")
                ->orWhere('external_code', 'ilike', "%{$q}%")
            );
        }

        if ($request->filled('business_code')) {
            $query->where('business_code', $request->business_code);
        }

        $projects = $query->limit((int) $request->input('limit', 20))
            ->get(['id', 'name', 'color', 'status', 'business_code', 'business_name',
                   'source_type', 'external_code', 'is_editable']);
        return ApiResponse::success($projects);
    }

    /**
     * 目的: 案件のbusinessesを処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: なし。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function businesses()
    {
        $businesses = Project::whereNotNull('business_code')
            ->select('business_code', 'business_name')
            ->distinct()
            ->orderBy('business_code')
            ->get();
        return ApiResponse::success($businesses);
    }

    /**
     * 目的: 案件の同期statusを処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: なし。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function syncStatus()
    {
        return ApiResponse::success(app(AppSettingService::class)->getNotionConfig());
    }

    /**
     * 目的: 案件の同期notionを処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function syncNotion(Request $request)
    {
        if (! $request->user()->isEditor()) {
            return ApiResponse::forbidden();
        }

        $service = new NotionSyncService();

        if (! $service->isConfigured()) {
            $config = app(AppSettingService::class)->getNotionConfig();
            return ApiResponse::error(
                'Notion同期の設定が不足しています。連携設定から Notion API トークンを設定してください。ルートページURLは任意です。',
                [
                    'missing' => $config['missing'],
                ],
                503
            );
        }

        $run = $service->discoverAndSync($request->user()->id);
        return ApiResponse::success($run);
    }

    /**
     * 目的: 案件の同期runsを処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: なし。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function syncRuns()
    {
        $runs = ProjectSyncRun::with('triggeredBy:id,name')
            ->orderByDesc('started_at')
            ->limit(20)
            ->get();
        return ApiResponse::success($runs);
    }

    /**
     * 目的: 案件の検証済み入力から新規作成する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
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
            'business_code' => ['nullable', 'string', 'max:20'],
            'business_name' => ['nullable', 'string', 'max:255'],
        ]);

        $validated['created_by'] = $request->user()->id;
        if (! empty($validated['business_code']) && empty($validated['business_name'])) {
            $validated['business_name'] = Project::where('business_code', $validated['business_code'])
                ->whereNotNull('business_name')
                ->value('business_name');
        }
        // 独自案件は常に local・編集可・source_key はUUID
        $validated['source_type']  = 'local';
        $validated['is_editable']  = true;
        $validated['source_key']   = (string) \Illuminate\Support\Str::uuid();
        $project = Project::create($validated);

        return ApiResponse::created($project->load('creator:id,name'));
    }

    /**
     * 目的: 案件の詳細を返す。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $project。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
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

    /**
     * 目的: 案件の検証済み入力で更新する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request, $project。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function update(Request $request, Project $project)
    {
        if (! $request->user()->isEditor()) {
            return ApiResponse::forbidden();
        }
        // Notion由来案件は編集不可
        if (! $project->is_editable) {
            return response()->json(['message' => 'Notion由来の案件はBitsKeep側では編集できません。Notionで編集してください。'], 422);
        }

        $validated = $request->validate([
            'name'        => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status'      => ['nullable', 'in:active,archived'],
            'color'       => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'business_code' => ['nullable', 'string', 'max:20'],
            'business_name' => ['nullable', 'string', 'max:255'],
        ]);

        if (! empty($validated['business_code']) && empty($validated['business_name'])) {
            $validated['business_name'] = Project::where('business_code', $validated['business_code'])
                ->whereNotNull('business_name')
                ->value('business_name');
        }

        $project->update($validated);
        return ApiResponse::success($project->load('creator:id,name'));
    }

    /**
     * 目的: 案件の削除またはアーカイブする。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request, $project。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function destroy(Request $request, Project $project)
    {
        if (! $request->user()->isEditor()) {
            return ApiResponse::forbidden();
        }
        // Notion由来案件は削除不可
        if (! $project->is_editable) {
            return response()->json(['message' => 'Notion由来の案件はBitsKeep側では削除できません。'], 422);
        }

        $project->delete();
        return ApiResponse::noContent();
    }

    // ── 使用部品管理 ─────────────────────────────────────

    /**
     * 目的: 案件の一覧部品を処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $project。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function listComponents(Project $project)
    {
        $components = $project->components()
            ->with(['categories', 'packages', 'componentSuppliers.priceBreaks'])
            ->get()
            ->map( fn($c) => array_merge($c->toArray(), [
                'required_qty'   => $c->pivot->required_qty,
                'cheapest_price' => $c->componentSuppliers->flatMap->priceBreaks->min('unit_price'),
            ]));

        return ApiResponse::success($components);
    }

    /**
     * 目的: 案件のadd部品を処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request, $project。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
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

    /**
     * 目的: 案件のupdate部品を処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request, $project, $component。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
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

    /**
     * 目的: 案件のremove部品を処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request, $project, $component。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function removeComponent(Request $request, Project $project, Component $component)
    {
        if (! $request->user()->isEditor()) {
            return ApiResponse::forbidden();
        }

        $project->components()->detach($component->id);
        return ApiResponse::noContent();
    }

    // ── コスト積算 ───────────────────────────────────────

    /**
     * 目的: 案件のcostを処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $project。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function cost(Project $project)
    {
        $project->load(['components.componentSuppliers.priceBreaks']);
        return ApiResponse::success($this->calcCost($project));
    }
    /**
     * 目的: 案件のcalccostを処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $project。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
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
