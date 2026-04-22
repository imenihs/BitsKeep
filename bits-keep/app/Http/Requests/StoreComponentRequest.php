<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreComponentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isEditor() ?? false;
    }

    public function rules(): array
    {
        return [
            // 基本情報（part_numberのみ必須、段階的入力を想定）
            'part_number' => ['required', 'string', 'max:200'],
            'manufacturer' => ['nullable', 'string', 'max:200'],
            'common_name' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'procurement_status' => ['nullable', 'in:active,eol,last_time,nrnd'],
            'threshold_new' => ['nullable', 'integer', 'min:0'],
            'threshold_used' => ['nullable', 'integer', 'min:0'],
            'primary_location_id' => ['nullable', 'integer', 'exists:locations,id'],
            // ファイル
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'datasheet' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
            'datasheets' => ['nullable', 'array'],
            'datasheets.*' => ['file', 'mimes:pdf', 'max:20480'],
            'datasheet_labels' => ['nullable', 'array'],
            'datasheet_labels.*' => ['nullable', 'string', 'max:120'],
            'temp_datasheet_tokens' => ['nullable', 'array'],
            'temp_datasheet_tokens.*' => ['string', 'max:100'],
            'temp_datasheet_labels' => ['nullable', 'array'],
            'temp_datasheet_labels.*' => ['nullable', 'string', 'max:120'],
            'existing_datasheets' => ['nullable', 'array'],
            'existing_datasheets.*.id' => ['nullable', 'integer'],
            'existing_datasheets.*.display_name' => ['nullable', 'string', 'max:120'],
            'duplicate_from_component_id' => ['nullable', 'integer', 'exists:components,id'],
            // 分類・パッケージ（ID配列）
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'package_group_id' => ['nullable', 'integer', 'exists:package_groups,id'],
            'package_id' => ['nullable', 'integer', 'exists:packages,id'],
            // スペック（配列）
            'specs' => ['nullable', 'array'],
            'specs.*.spec_type_id' => ['required_with:specs', 'integer', 'exists:spec_types,id'],
            'specs.*.value_profile' => ['nullable', 'in:typ,range,max_only,min_only,triple'],
            'specs.*.value_mode' => ['nullable', 'in:single,range'],
            'specs.*.value' => ['nullable', 'string', 'max:100'],
            'specs.*.value_typ' => ['nullable', 'string', 'max:100'],
            'specs.*.value_min' => ['nullable', 'string', 'max:100'],
            'specs.*.value_max' => ['nullable', 'string', 'max:100'],
            'specs.*.unit' => ['nullable', 'string', 'max:40'],
            'specs.*.value_numeric' => ['nullable', 'numeric'],
            // カスタムフィールド
            'attributes' => ['nullable', 'array'],
            'attributes.*.key' => ['required_with:attributes', 'string', 'max:100'],
            'attributes.*.value' => ['required_with:attributes', 'string', 'max:500'],
            // Altium 連携
            'altium.sch_library_id' => ['nullable', 'integer', 'exists:altium_libraries,id'],
            'altium.sch_symbol' => ['nullable', 'string', 'max:255'],
            'altium.pcb_library_id' => ['nullable', 'integer', 'exists:altium_libraries,id'],
            'altium.pcb_footprint' => ['nullable', 'string', 'max:255'],
            // 仕入先
            'suppliers' => ['nullable', 'array'],
            'suppliers.*.supplier_id' => ['required_with:suppliers', 'integer', 'exists:suppliers,id'],
            'suppliers.*.supplier_part_number' => ['nullable', 'string', 'max:200'],
            'suppliers.*.product_url' => ['nullable', 'url', 'max:500'],
            'suppliers.*.purchase_unit' => ['nullable', 'in:loose,tape,tray,reel,box'],
            'suppliers.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'suppliers.*.is_preferred' => ['nullable', 'boolean'],
            'suppliers.*.price_breaks' => ['nullable', 'array'],
            'suppliers.*.price_breaks.*.min_qty' => ['required_with:suppliers.*.price_breaks', 'integer', 'min:1'],
            'suppliers.*.price_breaks.*.unit_price' => ['required_with:suppliers.*.price_breaks', 'numeric', 'min:0'],
        ];
    }
}
