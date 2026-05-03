<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\AltiumLibrary;
use App\Models\Component;
use App\Models\ComponentAltiumLink;
use Illuminate\Http\Request;

/**
 * Altium連携 API（SCR-014）
 * ライブラリ管理 + 部品リンク管理
 */
class AltiumLinkController extends Controller
{
    // ── ライブラリ管理 ────────────────────────────────────

    /**
     * 目的: Altium連携のlibrariesを処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: なし。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function libraries()
    {
        $libs = AltiumLibrary::orderBy('type')->orderBy('name')->get();
        return ApiResponse::success($libs);
    }

    /**
     * 目的: Altium連携のstorelibraryを処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function storeLibrary(Request $request)
    {
        if (! $request->user()->isEditor()) {
            return ApiResponse::forbidden();
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:SchLib,PcbLib'],
            'path' => ['required', 'string', 'max:500'],
            'note' => ['nullable', 'string'],
        ]);

        $lib = AltiumLibrary::create($validated);
        return ApiResponse::created($lib);
    }

    /**
     * 目的: Altium連携のupdatelibraryを処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request, $library。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function updateLibrary(Request $request, AltiumLibrary $library)
    {
        if (! $request->user()->isEditor()) {
            return ApiResponse::forbidden();
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:SchLib,PcbLib'],
            'path' => ['required', 'string', 'max:500'],
            'note' => ['nullable', 'string'],
        ]);

        $library->update($validated);
        return ApiResponse::success($library);
    }

    /**
     * 目的: Altium連携のdestroylibraryを処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request, $library。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function destroyLibrary(Request $request, AltiumLibrary $library)
    {
        if (! $request->user()->isEditor()) {
            return ApiResponse::forbidden();
        }

        // 紐づきリンクのフィールドをNULLにしてから削除（migration で nullOnDelete 指定済み）
        $library->delete();
        return ApiResponse::noContent();
    }

    // ── 部品リンク ─────────────────────────────────────────

    /**
     * 目的: Altium連携の詳細を返す。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $component。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function show(Component $component)
    {
        $link = $component->altiumLink?->load(['schLibrary', 'pcbLibrary']);
        return ApiResponse::success($link);
    }

    /**
     * 目的: Altium連携のupsertを処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request, $component。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function upsert(Request $request, Component $component)
    {
        if (! $request->user()->isEditor()) {
            return ApiResponse::forbidden();
        }

        $validated = $request->validate([
            'sch_library_id' => ['nullable', 'exists:altium_libraries,id'],
            'sch_symbol'     => ['nullable', 'string', 'max:255'],
            'pcb_library_id' => ['nullable', 'exists:altium_libraries,id'],
            'pcb_footprint'  => ['nullable', 'string', 'max:255'],
        ]);

        $link = ComponentAltiumLink::updateOrCreate(
            ['component_id' => $component->id],
            $validated
        );

        return ApiResponse::success($link->load(['schLibrary', 'pcbLibrary']));
    }

    /**
     * 目的: Altium連携の削除またはアーカイブする。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request, $component。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function destroy(Request $request, Component $component)
    {
        if (! $request->user()->isEditor()) {
            return ApiResponse::forbidden();
        }

        $component->altiumLink?->delete();
        return ApiResponse::noContent();
    }
}
