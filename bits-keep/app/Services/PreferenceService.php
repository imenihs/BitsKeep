<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserPreference;

class PreferenceService
{
    /**
     * ユーザー設定値を取得する。キーが存在しない場合はデフォルト値を返す。
     */
    public function get(User $user, string $key, mixed $default = null): mixed
    {
        $pref = UserPreference::where('user_id', $user->id)
            ->where('key', $key)
            ->first();

        return $pref ? $pref->value : $default;
    }

    /**
     * ユーザー設定値を保存する（upsert）。
     */
    public function set(User $user, string $key, mixed $value): void
    {
        UserPreference::updateOrCreate(
            ['user_id' => $user->id, 'key' => $key],
            ['value'   => $value]
        );
    }

    /**
     * ユーザー設定値を削除する。
     */
    public function delete(User $user, string $key): void
    {
        UserPreference::where('user_id', $user->id)
            ->where('key', $key)
            ->delete();
    }
}
