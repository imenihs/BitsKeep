<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserPreference;

class PreferenceService
{
    /**
     * 目的: ユーザー設定のgetを担う。
     * 機能: ドメイン入力を正規化し、外部API、DB、計算処理のいずれかへ橋渡しする。
     * 入力: $user, $key, $default。
     * 出力: mixedで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: DB、外部API、ファイル、ログのいずれかを操作する場合がある。
     */
    public function get(User $user, string $key, mixed $default = null): mixed
    {
        $pref = UserPreference::where('user_id', $user->id)
            ->where('key', $key)
            ->first();

        return $pref ? $pref->value : $default;
    }

    /**
     * 目的: ユーザー設定のsetを担う。
     * 機能: ドメイン入力を正規化し、外部API、DB、計算処理のいずれかへ橋渡しする。
     * 入力: $user, $key, $value。
     * 出力: なし。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: DB、外部API、ファイル、ログのいずれかを操作する場合がある。
     */
    public function set(User $user, string $key, mixed $value): void
    {
        UserPreference::updateOrCreate(
            ['user_id' => $user->id, 'key' => $key],
            ['value'   => $value]
        );
    }

    /**
     * 目的: ユーザー設定のdeleteを担う。
     * 機能: ドメイン入力を正規化し、外部API、DB、計算処理のいずれかへ橋渡しする。
     * 入力: $user, $key。
     * 出力: なし。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: DB、外部API、ファイル、ログのいずれかを操作する場合がある。
     */
    public function delete(User $user, string $key): void
    {
        UserPreference::where('user_id', $user->id)
            ->where('key', $key)
            ->delete();
    }
}
