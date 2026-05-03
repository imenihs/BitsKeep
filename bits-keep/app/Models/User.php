<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name', 'email', 'password',
        'role', 'is_active', 'invited_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * 目的: ユーザーのcastsを担う。
     * 機能: モデル属性、関連、スコープ、保存時補完をEloquentへ提供する。
     * 入力: なし。
     * 出力: arrayで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: 状態変更を伴う場合がある。
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'invited_at' => 'datetime',
        ];
    }

    /**
     * 目的: ユーザーのisadminを担う。
     * 機能: モデル属性、関連、スコープ、保存時補完をEloquentへ提供する。
     * 入力: なし。
     * 出力: boolで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: 状態変更を伴う場合がある。
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
    /**
     * 目的: ユーザーのiseditorを担う。
     * 機能: モデル属性、関連、スコープ、保存時補完をEloquentへ提供する。
     * 入力: なし。
     * 出力: boolで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: 状態変更を伴う場合がある。
     */
    public function isEditor(): bool
    {
        return in_array($this->role, ['admin', 'editor']);
    }
    /**
     * 目的: ユーザーのisviewerを担う。
     * 機能: モデル属性、関連、スコープ、保存時補完をEloquentへ提供する。
     * 入力: なし。
     * 出力: boolで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: 状態変更を伴う場合がある。
     */
    public function isViewer(): bool
    {
        return true;
    } // 全ロールが閲覧可
    /**
     * 目的: Userからauth ProvidersへのEloquentリレーションを返す。
     * 機能: 関連モデル取得用のクエリ定義をLaravelへ渡す。
     * 入力: なし。
     * 出力: HasMany リレーション。
     * 動作条件: 対象モデルインスタンスまたはEloquentクエリ上で呼び出すこと。
     * 副作用: なし。
     */
    public function authProviders(): HasMany
    {
        return $this->hasMany(UserAuthProvider::class);
    }
    /**
     * 目的: UserからpreferencesへのEloquentリレーションを返す。
     * 機能: 関連モデル取得用のクエリ定義をLaravelへ渡す。
     * 入力: なし。
     * 出力: HasMany リレーション。
     * 動作条件: 対象モデルインスタンスまたはEloquentクエリ上で呼び出すこと。
     * 副作用: なし。
     */
    public function preferences(): HasMany
    {
        return $this->hasMany(UserPreference::class);
    }
}
