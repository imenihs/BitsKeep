<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * 目的: このリクエストを実行できるか判定する。
     * 機能: 画面/API入力の許可条件とバリデーション仕様をLaravelへ渡す。
     * 入力: なし。
     * 出力: 認可可否の真偽値。
     * 動作条件: 対象フォーム/APIからLaravel FormRequestとして呼び出されること。
     * 副作用: なし。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 目的: 入力検証ルールを定義する。
     * 機能: 画面/API入力の許可条件とバリデーション仕様をLaravelへ渡す。
     * 入力: なし。
     * 出力: Laravelバリデーションルール配列。
     * 動作条件: 対象フォーム/APIからLaravel FormRequestとして呼び出されること。
     * 副作用: なし。
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * 目的: Loginのauthenticateを定義する。
     * 機能: 画面/API入力の許可条件とバリデーション仕様をLaravelへ渡す。
     * 入力: なし。
     * 出力: なし。
     * 動作条件: 対象フォーム/APIからLaravel FormRequestとして呼び出されること。
     * 副作用: 状態変更を伴う場合がある。
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * 目的: Loginのensureisnotratelimitedを定義する。
     * 機能: 画面/API入力の許可条件とバリデーション仕様をLaravelへ渡す。
     * 入力: なし。
     * 出力: なし。
     * 動作条件: 対象フォーム/APIからLaravel FormRequestとして呼び出されること。
     * 副作用: 状態変更を伴う場合がある。
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * 目的: Loginのthrottlekeyを定義する。
     * 機能: 画面/API入力の許可条件とバリデーション仕様をLaravelへ渡す。
     * 入力: なし。
     * 出力: stringで表される値。
     * 動作条件: 対象フォーム/APIからLaravel FormRequestとして呼び出されること。
     * 副作用: 状態変更を伴う場合がある。
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
