<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLocationRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isAdmin() ?? false; }

    public function rules(): array
    {
        $id = $this->route('location')?->id;
        return [
            'code'        => ['required', 'string', 'max:50', Rule::unique('locations', 'code')->ignore($id)],
            'name'        => ['nullable', 'string', 'max:100'],
            'group'       => ['nullable', 'string', 'max:100'],
            'parent_id'   => ['nullable', 'integer', 'exists:locations,id'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
        ];
    }
}
