<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierRequest extends FormRequest
{
    /**
     * 目的: このリクエストを実行できるか判定する。
     * 機能: 画面/API入力の許可条件とバリデーション仕様をLaravelへ渡す。
     * 入力: なし。
     * 出力: 認可可否の真偽値。
     * 動作条件: 対象フォーム/APIからLaravel FormRequestとして呼び出されること。
     * 副作用: なし。
     */
    public function authorize(): bool { return $this->user()?->isAdmin() ?? false; }
    /**
     * 目的: 入力検証ルールを定義する。
     * 機能: 画面/API入力の許可条件とバリデーション仕様をLaravelへ渡す。
     * 入力: なし。
     * 出力: Laravelバリデーションルール配列。
     * 動作条件: 対象フォーム/APIからLaravel FormRequestとして呼び出されること。
     * 副作用: なし。
     */
    public function rules(): array
    {
        $id = $this->route('supplier')?->id;
        return [
            'name'                   => ['required', 'string', 'max:100', Rule::unique('suppliers', 'name')->ignore($id)],
            'url'                    => ['nullable', 'url', 'max:500'],
            'color'                  => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'lead_days'              => ['nullable', 'integer', 'min:0'],
            'free_shipping_threshold'=> ['nullable', 'numeric', 'min:0'],
            'note'                   => ['nullable', 'string', 'max:500'],
        ];
    }
}
