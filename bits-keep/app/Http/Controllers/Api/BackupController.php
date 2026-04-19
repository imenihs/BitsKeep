<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * DBバックアップ・リストア API（管理者専用）
 * GET  /api/backup/download  → pg_dump を .sql.gz でダウンロード
 * POST /api/backup/restore   → アップロードされた .sql or .sql.gz を psql でリストア
 */
class BackupController extends Controller
{
    // DBの接続情報を取得
    private function dbConfig(): array
    {
        return [
            'host'     => config('database.connections.pgsql.host'),
            'port'     => config('database.connections.pgsql.port', 5432),
            'database' => config('database.connections.pgsql.database'),
            'username' => config('database.connections.pgsql.username'),
            'password' => config('database.connections.pgsql.password'),
        ];
    }

    // GET /api/backup/download
    public function download(Request $request): StreamedResponse|JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return ApiResponse::forbidden();
        }

        $db       = $this->dbConfig();
        $filename = 'bitskeep_' . now()->format('Ymd_His') . '.sql.gz';

        // PGPASSWORD を環境変数でパスを渡す（引数に平文パスワードを出さない）
        $env  = 'PGPASSWORD=' . escapeshellarg($db['password']);
        $cmd  = "$env pg_dump"
            . ' -h ' . escapeshellarg($db['host'])
            . ' -p ' . escapeshellarg($db['port'])
            . ' -U ' . escapeshellarg($db['username'])
            . ' '    . escapeshellarg($db['database'])
            . ' | gzip';

        return response()->stream(function () use ($cmd) {
            $handle = popen($cmd, 'r');
            if ($handle === false) {
                abort(500, 'pg_dump の起動に失敗しました');
            }
            while (! feof($handle)) {
                echo fread($handle, 8192);
                flush();
            }
            pclose($handle);
        }, 200, [
            'Content-Type'        => 'application/gzip',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'X-Accel-Buffering'   => 'no',
        ]);
    }

    // POST /api/backup/restore
    public function restore(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return ApiResponse::forbidden();
        }

        $request->validate([
            'file' => ['required', 'file', 'mimes:sql,gz,plain', 'max:102400'], // 100MB
        ]);

        $file    = $request->file('file');
        $tmpPath = $file->getRealPath();
        $origName = $file->getClientOriginalName();
        $isGzip  = str_ends_with($origName, '.gz');

        $db  = $this->dbConfig();
        $env = 'PGPASSWORD=' . escapeshellarg($db['password']);

        // gzip の場合は展開してから流し込む
        $input = $isGzip
            ? "zcat " . escapeshellarg($tmpPath) . " | "
            : "cat "  . escapeshellarg($tmpPath) . " | ";

        $cmd = $input . "$env psql"
            . ' -h ' . escapeshellarg($db['host'])
            . ' -p ' . escapeshellarg($db['port'])
            . ' -U ' . escapeshellarg($db['username'])
            . ' '    . escapeshellarg($db['database'])
            . ' 2>&1';

        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            return ApiResponse::error('リストアに失敗しました: ' . implode("\n", array_slice($output, 0, 5)), [], 422);
        }

        return ApiResponse::success(null, 'リストアが完了しました');
    }
}
