<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * セクション別 PATCH リクエスト
 * section パラメータで対象セクションを指定: basic / specs / suppliers
 */
class UpdateComponentSectionRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isEditor() ?? false; }

    public function rules(): array
    {
        return match($this->route('section')) {
            'basic' => [
                'part_number'        => ['sometimes', 'string', 'max:200'],
                'manufacturer'       => ['nullable', 'string', 'max:200'],
                'common_name'        => ['nullable', 'string', 'max:200'],
                'description'        => ['nullable', 'string'],
                'procurement_status' => ['nullable', 'in:active,eol,last_time,nrnd'],
                'threshold_new'      => ['nullable', 'integer', 'min:0'],
                'threshold_used'     => ['nullable', 'integer', 'min:0'],
                'primary_location_id'=> ['nullable', 'integer', 'exists:locations,id'],
                'category_ids'       => ['nullable', 'array'],
                'category_ids.*'     => ['integer', 'exists:categories,id'],
                'package_ids'        => ['nullable', 'array'],
                'package_ids.*'      => ['integer', 'exists:packages,id'],
                'image'              => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
                'datasheet'          => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
                'datasheets'         => ['nullable', 'array'],
                'datasheets.*'       => ['file', 'mimes:pdf', 'max:20480'],
            ],
            'specs' => [
                'specs'                  => ['required', 'array'],
                'specs.*.id'             => ['nullable', 'integer', 'exists:component_specs,id'],
                'specs.*.spec_type_id'   => ['required', 'integer', 'exists:spec_types,id'],
                'specs.*.value'          => ['nullable', 'string', 'max:100'],
                'specs.*.unit'           => ['nullable', 'string', 'max:20'],
                'specs.*.value_numeric'  => ['nullable', 'numeric'],
            ],
            'attributes' => [
                'attributes'          => ['required', 'array'],
                'attributes.*.key'   => ['required', 'string', 'max:100'],
                'attributes.*.value' => ['nullable', 'string', 'max:500'],
            ],
            'suppliers' => [
                'suppliers'                          => ['required', 'array'],
                'suppliers.*.supplier_id'            => ['required', 'integer', 'exists:suppliers,id'],
                'suppliers.*.supplier_part_number'   => ['nullable', 'string', 'max:200'],
                'suppliers.*.product_url'            => ['nullable', 'url', 'max:500'],
                'suppliers.*.unit_price'             => ['nullable', 'numeric', 'min:0'],
                'suppliers.*.is_preferred'           => ['nullable', 'boolean'],
                'suppliers.*.price_breaks'           => ['nullable', 'array'],
                'suppliers.*.price_breaks.*.min_qty' => ['required_with:suppliers.*.price_breaks', 'integer', 'min:1'],
                'suppliers.*.price_breaks.*.unit_price' => ['required_with:suppliers.*.price_breaks', 'numeric', 'min:0'],
            ],
            default => [],
        };
    }
}
