<?php

namespace App\Http\Requests;

use App\Models\SpecType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSpecTypeRequest extends FormRequest
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
        return [
            'name'        => ['required', 'string', 'max:100'],
            'name_ja'     => ['nullable', 'string', 'max:120'],
            'name_en'     => ['nullable', 'string', 'max:160'],
            'symbol'           => ['nullable', 'string', 'max:80'],
            'suggest_prefixes' => ['nullable', 'array'],
            'suggest_prefixes.*' => ['nullable', 'string', 'max:4'],
            'display_prefixes' => ['nullable', 'array'],
            'display_prefixes.*' => ['nullable', 'string', 'max:4'],
            'spec_scope' => ['nullable', 'string', Rule::in(['common', 'group_local'])],
            'owner_spec_group_id' => ['nullable', 'integer', 'exists:spec_groups,id'],
            'spec_kind' => ['nullable', 'string', Rule::in([SpecType::KIND_NORMAL, SpecType::KIND_TOLERANCE])],
            'tolerance_settings' => ['nullable', 'array'],
            'tolerance_settings.default_mode' => ['nullable', 'string', 'max:40'],
            'tolerance_settings.default_unit' => ['nullable', 'string', 'max:40'],
            'tolerance_settings.allowed_units' => ['nullable', 'array'],
            'tolerance_settings.allowed_units.*' => ['nullable', 'string', 'max:40'],
            'tolerance_settings.grade_options' => ['nullable', 'array'],
            'tolerance_settings.grade_options.*.label' => ['nullable', 'string', 'max:40'],
            'tolerance_settings.grade_options.*.rank' => ['nullable', 'string', 'max:40'],
            'tolerance_settings.grade_options.*.value' => ['nullable'],
            'tolerance_settings.grade_options.*.plus' => ['nullable'],
            'tolerance_settings.grade_options.*.minus' => ['nullable'],
            'tolerance_settings.grade_options.*.unit' => ['nullable', 'string', 'max:40'],
            'tolerance_settings.grade_options.*.text' => ['nullable', 'string', 'max:160'],
            'base_unit'   => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
            // 単位候補の配列（新規・編集時に一括送信）
            'unit'               => ['nullable', 'string', 'max:20'],
            'aliases'            => ['nullable', 'array'],
            'aliases.*.alias'    => ['nullable', 'string', 'max:160'],
            'aliases.*.locale'   => ['nullable', 'string', 'max:16'],
            'aliases.*.kind'     => ['nullable', 'string', 'max:32'],
        ];
    }
}
