<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePackageGroupRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isEditor() ?? false; }

    public function rules(): array
    {
        $id = $this->route('package_group')?->id;

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('package_groups', 'name')->ignore($id)],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
