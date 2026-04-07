<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSpecTypeRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isAdmin() ?? false; }

    public function rules(): array
    {
        $id = $this->route('spec_type')?->id;
        return [
            'name'        => ['required', 'string', 'max:100', 'unique:spec_types,name,' . $id],
            'base_unit'   => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
            // 単位候補の配列（新規・編集時に一括送信）
            'units'               => ['nullable', 'array'],
            'units.*.unit'        => ['required_with:units', 'string', 'max:20'],
            'units.*.factor'      => ['required_with:units', 'numeric', 'min:0'],
            'units.*.sort_order'  => ['nullable', 'integer', 'min:0'],
        ];
    }
}
