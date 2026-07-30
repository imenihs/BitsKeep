<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\AppSettingService;
use App\Services\Datasheet\DatasheetAnalyzerRegistry;
use App\Services\NotionSyncService;
use Illuminate\Http\Request;

class IntegrationSettingsController extends Controller
{
    /**
     * 目的: 外部連携設定のshownotionを処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $settings, $notion。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function showNotion(AppSettingService $settings, NotionSyncService $notion)
    {
        $config = $settings->getNotionConfig();
        $config['health'] = $notion->diagnoseConnection();

        return ApiResponse::success($config);
    }

    /**
     * 目的: 外部連携設定のupdatenotionを処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request, $settings, $notion。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function updateNotion(Request $request, AppSettingService $settings, NotionSyncService $notion)
    {
        if (! $request->user()->isEditor()) {
            return ApiResponse::forbidden();
        }

        $validated = $request->validate([
            'api_token' => ['nullable', 'string', 'not_regex:/\s/'],
            'root_page_url' => ['nullable', 'string'],
            'clear_api_token' => ['nullable', 'boolean'],
            'clear_root_page_url' => ['nullable', 'boolean'],
        ]);

        try {
            $config = $settings->updateNotionConfig(
                $validated['api_token'] ?? null,
                $validated['root_page_url'] ?? null,
                (bool) ($validated['clear_api_token'] ?? false),
                (bool) ($validated['clear_root_page_url'] ?? false),
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::validationError([
                'root_page_url' => [$e->getMessage()],
            ]);
        }

        $config['health'] = $notion->diagnoseConnection();

        return ApiResponse::success($config, '連携設定を保存しました');
    }

    /**
     * 目的: 外部連携設定のshowgeminiを処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $settings。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function showGemini(AppSettingService $settings): \Illuminate\Http\JsonResponse
    {
        return ApiResponse::success($settings->getGeminiConfig());
    }

    /**
     * 目的: 外部連携設定のupdategeminiを処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request, $settings。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function updateGemini(Request $request, AppSettingService $settings): \Illuminate\Http\JsonResponse
    {
        if (! $request->user()->isEditor()) {
            return ApiResponse::forbidden();
        }

        $validated = $request->validate([
            'api_key' => ['nullable', 'string', 'not_regex:/\s/'],
            'clear_api_key' => ['nullable', 'boolean'],
        ]);

        $config = $settings->updateGeminiConfig(
            $validated['api_key'] ?? null,
            (bool) ($validated['clear_api_key'] ?? false),
        );

        return ApiResponse::success($config, 'Gemini APIキーを保存しました');
    }

    /**
     * 目的: データシート解析の実行方式と、その利用可否を返す。
     * 機能: 選択可能な解析方式と、現在有効な方式、利用できない場合の理由を並べる。
     * 入力: $registry は解析エンジンの一覧を持つレジストリ。
     * 出力: 有効な方式と選択肢を含むJSONレスポンス。
     * 動作条件: 認証済みユーザーを前提にする。
     * 副作用: 実行環境の確認としてファイル存在確認と設定値参照を行う。
     */
    public function showDatasheetEngine(DatasheetAnalyzerRegistry $registry): \Illuminate\Http\JsonResponse
    {
        $active = $registry->active();
        $availability = $active->checkAvailability();

        return ApiResponse::success([
            'active' => $active->key(),
            'active_label' => $active->label(),
            'available' => (bool) ($availability['available'] ?? false),
            'message' => $availability['message'] ?? null,
            'options' => $registry->options(),
        ]);
    }

    /**
     * 目的: データシート解析の実行方式を変更する。
     * 機能: 選択された方式を検証して保存する。
     * 入力: $request は engine を持つHTTPリクエスト、$registry は解析エンジンのレジストリ。
     * 出力: 変更後の状態を含むJSONレスポンス。
     * 動作条件: 編集権限を持つ利用者であること。
     * 副作用: DBの設定値を更新する。
     */
    public function updateDatasheetEngine(Request $request, DatasheetAnalyzerRegistry $registry): \Illuminate\Http\JsonResponse
    {
        if (! $request->user()->isEditor()) {
            return ApiResponse::forbidden();
        }

        $validated = $request->validate([
            'engine' => ['required', 'string', 'max:32'],
        ]);

        // 未登録の識別子は保存させない。保存できてしまうと解析時に既定へ落ちて挙動が読めなくなる
        try {
            $registry->setActive($validated['engine']);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::validationError(['engine' => [$e->getMessage()]]);
        }

        return ApiResponse::success(
            $this->showDatasheetEngine($registry)->getData(true)['data'] ?? [],
            'データシート解析の実行方式を保存しました'
        );
    }
}
