<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\SpecGroup;
use App\Models\SpecType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpecSuggestionController extends Controller
{
    /**
     * 目的: Spec Suggestionの一覧を検索条件付きで返す。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function index(Request $request): JsonResponse
    {
        $categoryIds = collect([
            ...(array) $request->input('category_ids', []),
            ...(array) $request->input('spec_group_ids', []),
        ])
            ->map( fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
        $q = trim((string) $request->query('q', ''));

        $groupsQuery = SpecGroup::query()
            ->where('name', '!=', '共通')
            ->whereIn('id', $categoryIds)
            ->with([
                'specTypes' => fn ($query) => $query->with(['units', 'aliases']),
                'templates' => fn ($query) => $query->with(['items.specType.units', 'items.specType.aliases']),
            ])
            ->orderBy('sort_order')
            ->orderBy('name');

        $groups = $groupsQuery->get();
        $groups->each(function (SpecGroup $group) {
            $group->is_suggested = true;
        });

        $recommendedGroups = $groups;
        $templates = $groups
            ->flatMap(function (SpecGroup $group) {
                return $group->templates->each(function ($template) {
                    $template->is_suggested = true;
                });
            })
            ->values();
        $recommendedTemplateIds = $templates
            ->filter( fn ($template) => (bool) $template->is_suggested)
            ->pluck('id')
            ->values();
        $specTypeIds = $recommendedGroups
            ->flatMap( fn (SpecGroup $group) => $group->specTypes->pluck('id'))
            ->unique()
            ->values();

        $specTypesQuery = SpecType::query()
            ->with(['units', 'aliases'])
            ->whereIn('id', $specTypeIds);
        if ($q !== '') {
            $like = "%{$q}%";
            $specTypesQuery->where(function ($query) use ($like) {
                $query->where('name', 'like', $like)
                    ->orWhere('name_ja', 'like', $like)
                    ->orWhere('name_en', 'like', $like)
                    ->orWhere('symbol', 'like', $like)
                    ->orWhereHas('aliases', fn ($aliasQuery) => $aliasQuery->where('alias', 'like', $like));
            });
        }

        return ApiResponse::success([
            'groups' => $groups,
            'spec_types' => $specTypesQuery->orderBy('sort_order')->orderBy('name')->get(),
            'templates' => $templates,
            'recommended_group_ids' => $recommendedGroups->pluck('id')->values(),
            'recommended_template_ids' => $recommendedTemplateIds,
        ]);
    }
}
