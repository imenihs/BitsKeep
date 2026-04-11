<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isEditor() ?? false; }

    public function rules(): array
    {
        $id = $this->route('category')?->id;
        return [
            'name'       => ['required', 'string', 'max:100', Rule::unique('categories', 'name')->ignore($id)],
            'color'      => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
