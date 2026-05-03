<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\AnalysisSession;
use Illuminate\Http\Request;

class AnalysisSessionController extends Controller
{
    /**
     * 目的: 解析保存の一覧を検索条件付きで返す。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'tool_id' => ['nullable', 'string', 'max:255'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'component_id' => ['nullable', 'integer', 'exists:components,id'],
            'bom_line_key' => ['nullable', 'string', 'max:255'],
        ]);

        $sessions = AnalysisSession::query()
            ->with(['project:id,name', 'component:id,part_number', 'creator:id,name', 'updater:id,name'])
            ->when(isset($validated['tool_id']), fn ($query) => $query->where('tool_id', $validated['tool_id']))
            ->when(isset($validated['project_id']), fn ($query) => $query->where('project_id', $validated['project_id']))
            ->when(isset($validated['component_id']), fn ($query) => $query->where('component_id', $validated['component_id']))
            ->when(isset($validated['bom_line_key']), fn ($query) => $query->where('bom_line_key', $validated['bom_line_key']))
            ->latest()
            ->get();

        return ApiResponse::success($sessions);
    }

    /**
     * 目的: 解析保存の検証済み入力から新規作成する。
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

        $validated = $request->validate($this->rules());
        $validated['created_by'] = $request->user()->id;
        $validated['updated_by'] = $request->user()->id;

        $session = AnalysisSession::create($validated);

        return ApiResponse::created($this->loadSession($session));
    }

    /**
     * 目的: 解析保存の詳細を返す。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $analysisSession。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function show(AnalysisSession $analysisSession)
    {
        return ApiResponse::success($this->loadSession($analysisSession));
    }

    /**
     * 目的: 解析保存の検証済み入力で更新する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request, $analysisSession。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function update(Request $request, AnalysisSession $analysisSession)
    {
        if (! $request->user()->isEditor()) {
            return ApiResponse::forbidden();
        }

        $validated = $request->validate($this->rules(true));
        $validated['updated_by'] = $request->user()->id;

        $analysisSession->update($validated);

        return ApiResponse::success($this->loadSession($analysisSession));
    }

    /**
     * 目的: 解析保存の削除またはアーカイブする。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request, $analysisSession。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function destroy(Request $request, AnalysisSession $analysisSession)
    {
        if (! $request->user()->isEditor()) {
            return ApiResponse::forbidden();
        }

        $analysisSession->delete();

        return ApiResponse::noContent();
    }

    /**
     * 目的: 解析保存の入力検証ルールを定義する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $partial。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: なし。
     * @return array<string, array<int, string>>
     */
    private function rules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'tool_id' => [$required, 'string', 'max:255'],
            'title' => [$required, 'string', 'max:255'],
            'verdict' => ['nullable', 'string', 'max:255'],
            'summary' => ['nullable', 'string'],
            'input_payload' => ['nullable', 'array'],
            'result_payload' => ['nullable', 'array'],
            'candidate_links' => ['nullable', 'array'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'component_id' => ['nullable', 'integer', 'exists:components,id'],
            'bom_line_key' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * 目的: 解析保存の読込保存解析を処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $session。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    private function loadSession(AnalysisSession $session): AnalysisSession
    {
        return $session->load(['project:id,name', 'component:id,part_number', 'creator:id,name', 'updater:id,name']);
    }
}
