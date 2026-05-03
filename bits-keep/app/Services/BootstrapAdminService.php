<?php

namespace App\Services;

use App\Models\User;

class BootstrapAdminService
{
    /**
     * 目的: Bootstrap Adminのensureforuserを担う。
     * 機能: ドメイン入力を正規化し、外部API、DB、計算処理のいずれかへ橋渡しする。
     * 入力: $user。
     * 出力: boolで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: DB、外部API、ファイル、ログのいずれかを操作する場合がある。
     */
    public function ensureForUser(?User $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if (! config('app.bootstrap_admin_enabled', true)) {
            return false;
        }

        $bootstrapEmail = strtolower(trim((string) config('app.bootstrap_admin_email')));
        if ($bootstrapEmail === '') {
            return false;
        }

        if (strtolower($user->email) !== $bootstrapEmail) {
            return false;
        }

        if ($user->role === 'admin') {
            return false;
        }

        $user->forceFill(['role' => 'admin'])->save();

        return true;
    }
}
