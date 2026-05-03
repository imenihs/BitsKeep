<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockOutRequest extends FormRequest
{
    /**
     * 目的: このリクエストを実行できるか判定する。
     * 機能: 画面/API入力の許可条件とバリデーション仕様をLaravelへ渡す。
     * 入力: なし。
     * 出力: 認可可否の真偽値。
     * 動作条件: 対象フォーム/APIからLaravel FormRequestとして呼び出されること。
     * 副作用: なし。
     */
    public function authorize(): bool { return $this->user()?->isEditor() ?? false; }
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
        return [
            'inventory_block_id' => ['required', 'integer', 'exists:inventory_blocks,id'],
            'quantity'           => ['required', 'integer', 'min:1'],
            'project_id'         => ['nullable', 'integer', 'exists:projects,id'],
            'note'               => ['nullable', 'string', 'max:500'],
        ];
    }
}
