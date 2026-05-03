<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePackageRequest extends FormRequest
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
        $id = $this->route('package')?->id;
        return [
            'package_group_id' => ['required', 'integer', 'exists:package_groups,id'],
            'name'        => ['required', 'string', 'max:100', Rule::unique('packages', 'name')->ignore($id)],
            'description' => ['nullable', 'string', 'max:500'],
            'size_x'      => ['nullable', 'numeric', 'min:0'],
            'size_y'      => ['nullable', 'numeric', 'min:0'],
            'size_z'      => ['nullable', 'numeric', 'min:0'],
            'image'       => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'pdf'         => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
        ];
    }
}
