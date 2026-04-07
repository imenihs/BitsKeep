<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePackageRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isEditor() ?? false; }

    public function rules(): array
    {
        $id = $this->route('package')?->id;
        return [
            'name'        => ['required', 'string', 'max:100', 'unique:packages,name,' . $id],
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
