<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockInRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isEditor() ?? false; }

    public function rules(): array
    {
        return [
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'stock_type'  => ['required', 'in:reel,tape,tray,loose,box'],
            'condition'   => ['required', 'in:new,used'],
            'quantity'    => ['required', 'integer', 'min:1'],
            'lot_number'  => ['nullable', 'string', 'max:100'],
            'reel_code'   => ['nullable', 'string', 'max:100'],
            'note'        => ['nullable', 'string', 'max:500'],
        ];
    }
}
