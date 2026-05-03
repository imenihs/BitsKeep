<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\SpecTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SpecTemplateController extends Controller
{
    /**
     * 目的: 入力テンプレートの一覧を検索条件付きで返す。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function index(Request $request): JsonResponse
    {
        $query = SpecTemplate::query()->with(['specGroup', 'items.specType.units', 'items.specType.aliases']);

        if ($request->boolean('include_archived')) {
            $query->withTrashed();
        }

        if ($groupId = $request->integer('spec_group_id')) {
            $query->where('spec_group_id', $groupId);
        }

        return ApiResponse::success($query->orderBy('sort_order')->orderBy('name')->get());
    }
    /**
     * 目的: 入力テンプレートの検証済み入力から新規作成する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function store(Request $request): JsonResponse
    {
        if (!$request->user()?->isAdmin()) {
            return ApiResponse::forbidden();
        }

        $data = $this->validatedTemplate($request);

        return DB::transaction(function () use ($data) {
            $template = SpecTemplate::create($data['attributes']);
            $this->syncItems($template, $data['items']);

            return ApiResponse::created($this->loadForEditor($template));
        });
    }
    /**
     * 目的: 入力テンプレートの詳細を返す。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $specTemplate。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function show(SpecTemplate $specTemplate): JsonResponse
    {
        return ApiResponse::success($this->loadForEditor($specTemplate));
    }
    /**
     * 目的: 入力テンプレートの検証済み入力で更新する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request, $specTemplate。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function update(Request $request, SpecTemplate $specTemplate): JsonResponse
    {
        if (!$request->user()?->isAdmin()) {
            return ApiResponse::forbidden();
        }

        $data = $this->validatedTemplate($request, $specTemplate);

        return DB::transaction(function () use ($data, $specTemplate) {
            $specTemplate->update($data['attributes']);
            $this->syncItems($specTemplate, $data['items']);

            return ApiResponse::success($this->loadForEditor($specTemplate));
        });
    }
    /**
     * 目的: 入力テンプレートの削除またはアーカイブする。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request, $specTemplate。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function destroy(Request $request, SpecTemplate $specTemplate): JsonResponse
    {
        if (!$request->user()?->isAdmin()) {
            return ApiResponse::forbidden();
        }

        $specTemplate->delete();

        return ApiResponse::noContent();
    }
    /**
     * 目的: 入力テンプレートのアーカイブ済みデータを復元する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request, $specTemplate。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function restore(Request $request, int $specTemplate): JsonResponse
    {
        if (!$request->user()?->isAdmin()) {
            return ApiResponse::forbidden();
        }

        $model = SpecTemplate::withTrashed()->findOrFail($specTemplate);
        $model->restore();

        return ApiResponse::success($this->loadForEditor($model));
    }
    /**
     * 目的: 入力テンプレートのforcedestroyを処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request, $specTemplate。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function forceDestroy(Request $request, int $specTemplate): JsonResponse
    {
        if (!$request->user()?->isAdmin()) {
            return ApiResponse::forbidden();
        }

        $model = SpecTemplate::withTrashed()->findOrFail($specTemplate);
        if (!$model->deleted_at) {
            return ApiResponse::error('完全削除の前にアーカイブしてください', [], 422);
        }
        $model->items()->delete();
        $model->forceDelete();

        return ApiResponse::noContent();
    }
    /**
     * 目的: 入力テンプレートの適用previewを処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $specTemplate。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function applyPreview(SpecTemplate $specTemplate): JsonResponse
    {
        $specTemplate->load(['items.specType.units', 'items.specType.aliases']);

        $rows = $specTemplate->items->map(function ($item) {
            return [
                'spec_type_id' => $item->spec_type_id,
                'spec_type' => $item->specType,
                'value_profile' => $item->default_profile ?: 'typ',
                'unit' => $item->default_unit ?: ($item->specType?->units?->first()?->unit ?? ''),
                'is_required' => (bool) $item->is_required,
                'note' => $item->note,
            ];
        })->values();

        return ApiResponse::success([
            'template' => $specTemplate,
            'items' => $rows,
        ]);
    }

    /**
     * 目的: 入力テンプレートのvalidatedテンプレートを処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request, $template。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     * @return array{attributes: array<string, mixed>, items: array<int, array<string, mixed>>}
     */
    private function validatedTemplate(Request $request, ?SpecTemplate $template = null): array
    {
        $validated = $request->validate([
            'spec_group_id' => ['nullable', 'integer', 'exists:spec_groups,id'],
            'name' => ['required', 'string', 'max:100', Rule::unique('spec_templates', 'name')->ignore($template?->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'items' => ['nullable', 'array'],
            'items.*.spec_type_id' => ['required', 'integer', 'exists:spec_types,id'],
            'items.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'items.*.default_profile' => ['nullable', 'in:typ,range,max_only,min_only,triple'],
            'items.*.default_unit' => ['nullable', 'string', 'max:40'],
            'items.*.is_required' => ['nullable', 'boolean'],
            'items.*.note' => ['nullable', 'string', 'max:255'],
        ]);

        return [
            'attributes' => [
                'spec_group_id' => $validated['spec_group_id'] ?? null,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'sort_order' => $validated['sort_order'] ?? 0,
            ],
            'items' => array_values($validated['items'] ?? []),
        ];
    }

    /**
     * 目的: 入力テンプレートの同期itemsを処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $template, $items。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(SpecTemplate $template, array $items): void
    {
        $template->items()->delete();

        $seen = [];
        foreach ($items as $index => $item) {
            $specTypeId = (int) $item['spec_type_id'];
            if (isset($seen[$specTypeId])) {
                continue;
            }
            $seen[$specTypeId] = true;

            $template->items()->create([
                'spec_type_id' => $specTypeId,
                'sort_order' => (int) ($item['sort_order'] ?? (($index + 1) * 10)),
                'default_profile' => $item['default_profile'] ?? null,
                'default_unit' => $item['default_unit'] ?? null,
                'is_required' => (bool) ($item['is_required'] ?? false),
                'note' => $item['note'] ?? null,
            ]);
        }
    }
    /**
     * 目的: 入力テンプレートの読込foreditorを処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $template。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    private function loadForEditor(SpecTemplate $template): SpecTemplate
    {
        return $template->load(['specGroup', 'items.specType.units', 'items.specType.aliases']);
    }
}
