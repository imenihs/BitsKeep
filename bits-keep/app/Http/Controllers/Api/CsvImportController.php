<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Component;
use App\Models\Package;
use App\Models\SpecGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * CSVインポート API（SCR-013）
 * Step1: アップロード → プレビュー（バリデーション + マッピング）
 * Step2: 確定 → 一括登録
 *
 * CSVフォーマット（ヘッダ行必須）:
 *   part_number, common_name, description, procurement_status,
 *   quantity_new, quantity_used, threshold_new,
 *   category_names(カンマ区切り), package_name
 */
class CsvImportController extends Controller
{
    /**
     * 目的: CSV取込の保存前プレビューを生成する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function preview(Request $request)
    {
        if (! $request->user()->isEditor()) {
            return ApiResponse::forbidden();
        }

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'], // 5MB上限
        ]);

        $path = $request->file('file')->getRealPath();
        [$headers, $rows, $errors] = $this->parseCsv($path);

        return ApiResponse::success([
            'headers' => $headers,
            'rows'    => $rows,
            'errors'  => $errors,
            'total'   => count($rows),
        ]);
    }

    /**
     * 目的: CSV取込のcommitを処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $request。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: DB、ファイルストレージ、外部サービス、HTTPレスポンスのいずれかを操作する場合がある。
     */
    public function commit(Request $request)
    {
        if (! $request->user()->isEditor()) {
            return ApiResponse::forbidden();
        }

        $request->validate([
            'rows'   => ['required', 'array', 'min:1'],
            'rows.*' => ['array'],
        ]);

        $results = DB::transaction(function () use ($request) {
            $created = 0;
            $skipped = [];

            foreach ($request->rows as $i => $row) {
                // 型番重複チェック（既存は上書きしない）
                if (Component::where('part_number', $row['part_number'])->exists()) {
                    $skipped[] = ['row' => $i + 1, 'part_number' => $row['part_number'], 'reason' => '型番が既に存在します'];
                    continue;
                }

                $comp = Component::create([
                    'part_number'        => $row['part_number'],
                    'common_name'        => $row['common_name'] ?? null,
                    'description'        => $row['description'] ?? null,
                    'procurement_status' => $row['procurement_status'] ?? 'active',
                    'quantity_new'       => intval($row['quantity_new'] ?? 0),
                    'quantity_used'      => intval($row['quantity_used'] ?? 0),
                    'threshold_new'      => intval($row['threshold_new'] ?? 0),
                ]);

                // 部品分類紐づけ
                if (! empty($row['category_names'])) {
                    $names = array_map('trim', explode(',', $row['category_names']));
                    $ids = SpecGroup::whereIn('name', $names)->pluck('id');
                    $comp->categories()->sync($ids);
                }

                // パッケージ紐づけ
                if (! empty($row['package_name'])) {
                    $pkg = Package::where('name', trim($row['package_name']))->first();
                    if ($pkg) {
                        $comp->package_id = $pkg->id;
                        $comp->save();
                    }
                }

                $created++;
            }

            return ['created' => $created, 'skipped' => $skipped];
        });

        return ApiResponse::success($results, "{$results['created']} 件インポートしました");
    }

    /**
     * 目的: CSV取込の解析csvを処理する。
     * 機能: HTTP入力を検証し、Eloquent操作またはサービス処理を行い、JSONレスポンスへ包む。
     * 入力: $path。
     * 出力: HTTP JSONレスポンス、ファイルレスポンス、またはnoContentレスポンス。
     * 動作条件: 認証済みユーザー、権限、バリデーション済み入力を前提にする。
     * 副作用: なし。
     */
    private function parseCsv(string $path): array
    {
        // BOM除去
        $content = file_get_contents($path);
        $content = ltrim($content, "\xEF\xBB\xBF");
        $lines   = preg_split('/\r\n|\r|\n/', trim($content));

        if (count($lines) < 2) {
            return [[], [], [['row' => 0, 'message' => 'データ行がありません']]];
        }

        $headers = str_getcsv(array_shift($lines));
        // ヘッダーを正規化（トリム・小文字）
        $headers = array_map( fn($h) => strtolower(trim($h)), $headers);

        $requiredCols = ['part_number'];
        $missingCols  = array_diff($requiredCols, $headers);
        if ($missingCols) {
            return [$headers, [], [['row' => 0, 'message' => 'part_number 列が必要です']]];
        }

        $rows   = [];
        $errors = [];
        foreach ($lines as $lineNum => $line) {
            if (trim($line) === '') continue;
            $cols = str_getcsv($line);
            $row  = [];
            foreach ($headers as $i => $h) {
                $row[$h] = $cols[$i] ?? '';
            }

            // バリデーション
            $v = Validator::make($row, [
                'part_number'        => 'required|string',
                'procurement_status' => 'nullable|in:active,nrnd,eol,custom',
                'quantity_new'       => 'nullable|integer|min:0',
                'quantity_used'      => 'nullable|integer|min:0',
                'threshold_new'      => 'nullable|integer|min:0',
            ]);

            if ($v->fails()) {
                $errors[] = ['row' => $lineNum + 2, 'message' => implode(', ', $v->errors()->all())];
                continue;
            }

            $rows[] = $row;
        }

        return [$headers, $rows, $errors];
    }
}
