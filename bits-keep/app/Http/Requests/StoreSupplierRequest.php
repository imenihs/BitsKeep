<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isAdmin() ?? false; }

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
