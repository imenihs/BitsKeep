<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSpecTypeRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isAdmin() ?? false; }

    public function rules(): array
    {
        $id = $this->route('spec_type')?->id;
        return [
            'name'        => ['required', 'string', 'max:100', Rule::unique('spec_types', 'name')->ignore($id)],
            'name_ja'     => ['nullable', 'string', 'max:120'],
            'name_en'     => ['nullable', 'string', 'max:160'],
            'symbol'           => ['nullable', 'string', 'max:80'],
            'suggest_prefixes' => ['nullable', 'array'],
            'suggest_prefixes.*' => ['nullable', 'string', 'max:4'],
            'display_prefixes' => ['nullable', 'array'],
            'display_prefixes.*' => ['nullable', 'string', 'max:4'],
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
