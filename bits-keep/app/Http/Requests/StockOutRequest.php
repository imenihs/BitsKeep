<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockOutRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isEditor() ?? false; }

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
