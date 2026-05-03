<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * AuditObserver
 * 監視対象モデルの create/update/delete を audit_logs に自動記録する。
 * 各モデルで observe(AuditObserver::class) して使用する。
 */
class AuditObserver
{
    // 無視するカラム（タイムスタンプ等は差分に含めない）
    protected array $ignoredKeys = ['updated_at', 'created_at', 'deleted_at'];
    /**
     * 目的: Audit Observerのcreatedを担う。
     * 機能: 呼び出し元の入力を検証・整形し、担当するアプリ処理へ渡す。
     * 入力: $model。
     * 出力: なし。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: 状態変更を伴う場合がある。
     */
    public function created(Model $model): void
    {
        $this->log($model, 'create', null, $model->getAttributes());
    }
    /**
     * 目的: Audit Observerのupdatedを担う。
     * 機能: 呼び出し元の入力を検証・整形し、担当するアプリ処理へ渡す。
     * 入力: $model。
     * 出力: なし。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: 状態変更を伴う場合がある。
     */
    public function updated(Model $model): void
    {
        $dirty = $model->getDirty();
        // 無視カラムを除外
        $dirty = array_diff_key($dirty, array_flip($this->ignoredKeys));
        if (empty($dirty)) return;

        $before = array_intersect_key($model->getOriginal(), $dirty);
        $after  = array_intersect_key($model->getAttributes(), $dirty);
        $this->log($model, 'update', $before, $after);
    }
    /**
     * 目的: Audit Observerのdeletedを担う。
     * 機能: 呼び出し元の入力を検証・整形し、担当するアプリ処理へ渡す。
     * 入力: $model。
     * 出力: なし。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: 状態変更を伴う場合がある。
     */
    public function deleted(Model $model): void
    {
        // SoftDeleteの場合は deleted_at が立つだけなので 'delete' アクションとして記録
        $this->log($model, 'delete', $model->getOriginal(), null);
    }
    /**
     * 目的: Audit Observerのlogを担う。
     * 機能: 呼び出し元の入力を検証・整形し、担当するアプリ処理へ渡す。
     * 入力: $model, $action, $before, $after。
     * 出力: なし。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: 状態変更を伴う場合がある。
     */
    protected function log(Model $model, string $action, ?array $before, ?array $after): void
    {
        AuditLog::create([
            'user_id'       => Auth::id(),
            'action'        => $action,
            'resource_type' => class_basename($model),
            'resource_id'   => $model->getKey(),
            'diff'          => $before || $after ? ['before' => $before, 'after' => $after] : null,
            'ip_address'    => Request::ip(),
            'user_agent'    => Request::userAgent(),
            'created_at'    => now(),
        ]);
    }
}
