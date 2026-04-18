<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Component;
use App\Models\ComponentSupplier;
use App\Models\InventoryBlock;
use App\Models\Location;
use App\Models\Package;
use App\Models\PackageGroup;
use App\Models\SpecType;
use App\Models\Supplier;
use App\Models\SupplierPriceBreak;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class BitsKeepDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $admin = User::query()->updateOrCreate(
                ['email' => 'imenihs@gmail.com'],
                [
                    'name' => 'BitsKeep 管理者',
                    'password' => Hash::make('lA4sdBnnJuV2qBwJ'),
                    'role' => 'admin',
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );

            $categories = collect([
                ['name' => '抵抗', 'color' => '#ef4444', 'sort_order' => 10],
                ['name' => 'コンデンサ', 'color' => '#3b82f6', 'sort_order' => 20],
                ['name' => '電源IC', 'color' => '#10b981', 'sort_order' => 30],
                ['name' => 'アナログIC', 'color' => '#f59e0b', 'sort_order' => 40],
                ['name' => 'マイコン', 'color' => '#8b5cf6', 'sort_order' => 50],
                ['name' => 'トランジスタ', 'color' => '#06b6d4', 'sort_order' => 60],
                ['name' => 'コネクタ', 'color' => '#84cc16', 'sort_order' => 70],
            ])->mapWithKeys(fn (array $data) => [
                $data['name'] => Category::query()->updateOrCreate(['name' => $data['name']], $data),
            ]);

            $packageGroups = collect([
                ['name' => 'チップ受動', 'description' => '0603/0805 等', 'sort_order' => 10],
                ['name' => 'SOT系', 'description' => 'SOT-23/SOT-89 等', 'sort_order' => 20],
                ['name' => 'SOIC系', 'description' => 'SOIC/TSSOP 等', 'sort_order' => 30],
                ['name' => 'QFN系', 'description' => 'QFN/BGA 等', 'sort_order' => 40],
                ['name' => 'DIP系', 'description' => 'DIP/SIP 等', 'sort_order' => 50],
                ['name' => 'コネクタ系', 'description' => 'ピンヘッダ等', 'sort_order' => 60],
            ])->mapWithKeys(fn (array $data) => [
                $data['name'] => PackageGroup::query()->updateOrCreate(['name' => $data['name']], $data),
            ]);

            $packages = collect([
                ['group' => 'チップ受動', 'name' => '0603', 'description' => '1.6 x 0.8 mm', 'size_x' => 1.6, 'size_y' => 0.8, 'size_z' => 0.55, 'sort_order' => 10],
                ['group' => 'チップ受動', 'name' => '0805', 'description' => '2.0 x 1.25 mm', 'size_x' => 2.0, 'size_y' => 1.25, 'size_z' => 0.6, 'sort_order' => 20],
                ['group' => 'SOT系', 'name' => 'SOT-23', 'description' => '3 pin small signal', 'size_x' => 2.9, 'size_y' => 1.3, 'size_z' => 1.1, 'sort_order' => 10],
                ['group' => 'SOT系', 'name' => 'SOT-89', 'description' => 'Power transistor package', 'size_x' => 4.5, 'size_y' => 2.5, 'size_z' => 1.5, 'sort_order' => 20],
                ['group' => 'SOIC系', 'name' => 'SOIC-8', 'description' => '8 pin SOIC', 'size_x' => 4.9, 'size_y' => 3.9, 'size_z' => 1.75, 'sort_order' => 10],
                ['group' => 'SOIC系', 'name' => 'SOIC-16', 'description' => '16 pin SOIC', 'size_x' => 9.9, 'size_y' => 3.9, 'size_z' => 1.75, 'sort_order' => 20],
                ['group' => 'QFN系', 'name' => 'QFN-32', 'description' => '32 pin QFN', 'size_x' => 5.0, 'size_y' => 5.0, 'size_z' => 0.9, 'sort_order' => 10],
                ['group' => 'DIP系', 'name' => 'DIP-8', 'description' => '8 pin DIP', 'size_x' => 9.27, 'size_y' => 6.35, 'size_z' => 3.3, 'sort_order' => 10],
                ['group' => 'コネクタ系', 'name' => 'PinHeader-1x04-2.54', 'description' => '2.54mm 1x04 header', 'size_x' => 10.16, 'size_y' => 2.54, 'size_z' => 8.5, 'sort_order' => 10],
            ])->mapWithKeys(function (array $data) use ($packageGroups) {
                $group = $packageGroups[$data['group']];
                $payload = $data;
                unset($payload['group']);
                $payload['package_group_id'] = $group->id;

                return [
                    $payload['name'] => Package::query()->updateOrCreate(['name' => $payload['name']], $payload),
                ];
            });

            $specTypes = collect([
                ['name' => '抵抗値', 'base_unit' => 'Ω', 'description' => '抵抗器の公称抵抗値', 'sort_order' => 10],
                ['name' => '容量', 'base_unit' => 'F', 'description' => 'コンデンサの容量', 'sort_order' => 20],
                ['name' => '耐圧', 'base_unit' => 'V', 'description' => '定格電圧', 'sort_order' => 30],
                ['name' => '出力電圧', 'base_unit' => 'V', 'description' => 'レギュレータの出力電圧', 'sort_order' => 40],
                ['name' => '電流', 'base_unit' => 'A', 'description' => '出力可能電流', 'sort_order' => 50],
                ['name' => 'フラッシュ容量', 'base_unit' => 'B', 'description' => '内蔵フラッシュ', 'sort_order' => 60],
                ['name' => 'チャンネル数', 'base_unit' => 'ch', 'description' => '入出力チャンネル数', 'sort_order' => 70],
            ])->mapWithKeys(fn (array $data) => [
                $data['name'] => SpecType::query()->updateOrCreate(['name' => $data['name']], $data),
            ]);

            $suppliers = collect([
                ['name' => 'DigiKey', 'url' => 'https://www.digikey.jp/', 'color' => '#2563eb', 'lead_days' => 3, 'free_shipping_threshold' => 5500, 'note' => '海外商社'],
                ['name' => 'Mouser', 'url' => 'https://www.mouser.jp/', 'color' => '#059669', 'lead_days' => 4, 'free_shipping_threshold' => 5500, 'note' => '海外商社'],
                ['name' => '秋月電子通商', 'url' => 'https://akizukidenshi.com/', 'color' => '#dc2626', 'lead_days' => 2, 'free_shipping_threshold' => 4000, 'note' => '国内即納'],
            ])->mapWithKeys(fn (array $data) => [
                $data['name'] => Supplier::query()->updateOrCreate(['name' => $data['name']], $data),
            ]);

            $locations = collect([
                ['code' => 'A-1', 'name' => '受動部品棚1', 'group' => 'A棚', 'sort_order' => 10],
                ['code' => 'A-2', 'name' => '受動部品棚2', 'group' => 'A棚', 'sort_order' => 20],
                ['code' => 'B-1', 'name' => 'IC棚1', 'group' => 'B棚', 'sort_order' => 30],
                ['code' => 'B-2', 'name' => 'IC棚2', 'group' => 'B棚', 'sort_order' => 40],
                ['code' => 'C-1', 'name' => 'コネクタ棚', 'group' => 'C棚', 'sort_order' => 50],
            ])->mapWithKeys(fn (array $data) => [
                $data['code'] => Location::query()->updateOrCreate(['code' => $data['code']], $data),
            ]);

            $components = [
                [
                    'part_number' => 'BK-DEMO-RES-10K-0603',
                    'manufacturer' => 'Yageo',
                    'common_name' => '10k抵抗',
                    'description' => '汎用チップ抵抗 10kΩ 1%',
                    'procurement_status' => 'active',
                    'threshold_new' => 200,
                    'threshold_used' => 0,
                    'package' => '0603',
                    'categories' => ['抵抗'],
                    'specs' => [
                        ['type' => '抵抗値', 'value' => '10k', 'unit' => 'Ω', 'value_numeric' => 10000],
                    ],
                    'suppliers' => [
                        ['name' => 'DigiKey', 'pn' => '311-10.0KHRCT-ND', 'url' => 'https://example.com/dk/10k0603', 'purchase_unit' => 'リール', 'unit_price' => 0.8, 'preferred' => true, 'breaks' => [[10, 0.7], [100, 0.5]]],
                        ['name' => '秋月電子通商', 'pn' => 'R-10K-0603', 'url' => 'https://example.com/ak/10k0603', 'purchase_unit' => 'バラ', 'unit_price' => 1.2, 'preferred' => false, 'breaks' => []],
                    ],
                    'inventory' => [
                        ['location' => 'A-1', 'stock_type' => 'loose', 'condition' => 'new', 'quantity' => 120, 'lot' => 'R0603-10K-A'],
                        ['location' => 'A-2', 'stock_type' => 'reel', 'condition' => 'used', 'quantity' => 20, 'lot' => 'R0603-10K-R'],
                    ],
                ],
                [
                    'part_number' => 'BK-DEMO-RES-1K-0805',
                    'manufacturer' => 'KOA',
                    'common_name' => '1k抵抗',
                    'description' => '汎用チップ抵抗 1kΩ 5%',
                    'procurement_status' => 'active',
                    'threshold_new' => 100,
                    'threshold_used' => 20,
                    'package' => '0805',
                    'categories' => ['抵抗'],
                    'specs' => [
                        ['type' => '抵抗値', 'value' => '1k', 'unit' => 'Ω', 'value_numeric' => 1000],
                    ],
                    'suppliers' => [
                        ['name' => '秋月電子通商', 'pn' => 'R-1K-0805', 'url' => 'https://example.com/ak/1k0805', 'purchase_unit' => 'バラ', 'unit_price' => 1.0, 'preferred' => true, 'breaks' => [[50, 0.9]]],
                    ],
                    'inventory' => [
                        ['location' => 'A-2', 'stock_type' => 'loose', 'condition' => 'new', 'quantity' => 85, 'lot' => 'R0805-1K-A'],
                        ['location' => 'A-2', 'stock_type' => 'loose', 'condition' => 'used', 'quantity' => 18, 'lot' => 'R0805-1K-U'],
                    ],
                ],
                [
                    'part_number' => 'BK-DEMO-CAP-100N-0603',
                    'manufacturer' => 'Murata',
                    'common_name' => '0.1uF積セラ',
                    'description' => 'デカップリング用 0.1uF 25V',
                    'procurement_status' => 'active',
                    'threshold_new' => 300,
                    'threshold_used' => 0,
                    'package' => '0603',
                    'categories' => ['コンデンサ'],
                    'specs' => [
                        ['type' => '容量', 'value' => '0.1u', 'unit' => 'F', 'value_numeric' => 0.0000001],
                        ['type' => '耐圧', 'value' => '25', 'unit' => 'V', 'value_numeric' => 25],
                    ],
                    'suppliers' => [
                        ['name' => 'Mouser', 'pn' => '81-GRM188R71E104KA01D', 'url' => 'https://example.com/ms/100n0603', 'purchase_unit' => 'リール', 'unit_price' => 1.4, 'preferred' => true, 'breaks' => [[100, 1.0], [1000, 0.6]]],
                    ],
                    'inventory' => [
                        ['location' => 'A-1', 'stock_type' => 'reel', 'condition' => 'new', 'quantity' => 480, 'lot' => 'C0603-100N-R'],
                    ],
                ],
                [
                    'part_number' => 'BK-DEMO-LDO-3V3-SOT23',
                    'manufacturer' => 'Microchip',
                    'common_name' => '3.3V LDO',
                    'description' => '500mA LDO レギュレータ',
                    'procurement_status' => 'active',
                    'threshold_new' => 30,
                    'threshold_used' => 0,
                    'package' => 'SOT-23',
                    'categories' => ['電源IC'],
                    'specs' => [
                        ['type' => '出力電圧', 'value' => '3.3', 'unit' => 'V', 'value_numeric' => 3.3],
                        ['type' => '電流', 'value' => '0.5', 'unit' => 'A', 'value_numeric' => 0.5],
                    ],
                    'suppliers' => [
                        ['name' => 'DigiKey', 'pn' => 'MCP1700T-3302E/TTCT-ND', 'url' => 'https://example.com/dk/ldo33', 'purchase_unit' => 'テープ', 'unit_price' => 32.5, 'preferred' => true, 'breaks' => [[10, 28.0], [100, 24.0]]],
                    ],
                    'inventory' => [
                        ['location' => 'B-1', 'stock_type' => 'tape', 'condition' => 'new', 'quantity' => 12, 'lot' => 'LDO33-T1'],
                    ],
                ],
                [
                    'part_number' => 'BK-DEMO-OPAMP-SOIC8',
                    'manufacturer' => 'Texas Instruments',
                    'common_name' => '汎用OPAMP',
                    'description' => '低電圧動作用デュアルオペアンプ',
                    'procurement_status' => 'active',
                    'threshold_new' => 20,
                    'threshold_used' => 5,
                    'package' => 'SOIC-8',
                    'categories' => ['アナログIC'],
                    'specs' => [
                        ['type' => 'チャンネル数', 'value' => '2', 'unit' => 'ch', 'value_numeric' => 2],
                    ],
                    'suppliers' => [
                        ['name' => 'Mouser', 'pn' => '595-LM358DR', 'url' => 'https://example.com/ms/lm358', 'purchase_unit' => 'リール', 'unit_price' => 18.0, 'preferred' => true, 'breaks' => [[25, 15.5], [100, 12.0]]],
                        ['name' => '秋月電子通商', 'pn' => 'I-08765', 'url' => 'https://example.com/ak/lm358', 'purchase_unit' => 'バラ', 'unit_price' => 35.0, 'preferred' => false, 'breaks' => []],
                    ],
                    'inventory' => [
                        ['location' => 'B-1', 'stock_type' => 'tray', 'condition' => 'new', 'quantity' => 16, 'lot' => 'LM358-N1'],
                        ['location' => 'B-1', 'stock_type' => 'tray', 'condition' => 'used', 'quantity' => 3, 'lot' => 'LM358-U1'],
                    ],
                ],
                [
                    'part_number' => 'BK-DEMO-MCU-QFN32',
                    'manufacturer' => 'STMicroelectronics',
                    'common_name' => 'STM32G0',
                    'description' => '32bit MCU 64KB Flash',
                    'procurement_status' => 'nrnd',
                    'threshold_new' => 15,
                    'threshold_used' => 0,
                    'package' => 'QFN-32',
                    'categories' => ['マイコン'],
                    'specs' => [
                        ['type' => 'フラッシュ容量', 'value' => '64KB', 'unit' => 'B', 'value_numeric' => 65536],
                    ],
                    'suppliers' => [
                        ['name' => 'DigiKey', 'pn' => '497-STM32G031K8U6TR-ND', 'url' => 'https://example.com/dk/stm32g0', 'purchase_unit' => 'テープ', 'unit_price' => 95.0, 'preferred' => true, 'breaks' => [[10, 88.0], [50, 81.0]]],
                    ],
                    'inventory' => [
                        ['location' => 'B-2', 'stock_type' => 'tray', 'condition' => 'new', 'quantity' => 9, 'lot' => 'STM32G0-T1'],
                    ],
                ],
                [
                    'part_number' => 'BK-DEMO-USBUART-SOIC16',
                    'manufacturer' => 'FTDI',
                    'common_name' => 'USB-UART',
                    'description' => 'USBシリアル変換IC',
                    'procurement_status' => 'last_time',
                    'threshold_new' => 8,
                    'threshold_used' => 0,
                    'package' => 'SOIC-16',
                    'categories' => ['アナログIC'],
                    'specs' => [],
                    'suppliers' => [
                        ['name' => 'DigiKey', 'pn' => '768-FT232RL-REEL-ND', 'url' => 'https://example.com/dk/ft232', 'purchase_unit' => 'リール', 'unit_price' => 420.0, 'preferred' => true, 'breaks' => [[10, 400.0]]],
                    ],
                    'inventory' => [
                        ['location' => 'B-2', 'stock_type' => 'tray', 'condition' => 'new', 'quantity' => 4, 'lot' => 'FT232RL-T1'],
                    ],
                ],
                [
                    'part_number' => 'BK-DEMO-NPN-SOT23',
                    'manufacturer' => 'ROHM',
                    'common_name' => 'NPNトランジスタ',
                    'description' => '小信号 NPN トランジスタ',
                    'procurement_status' => 'active',
                    'threshold_new' => 100,
                    'threshold_used' => 0,
                    'package' => 'SOT-23',
                    'categories' => ['トランジスタ'],
                    'specs' => [],
                    'suppliers' => [
                        ['name' => '秋月電子通商', 'pn' => 'TR-SOT23-NPN', 'url' => 'https://example.com/ak/npn', 'purchase_unit' => 'バラ', 'unit_price' => 4.0, 'preferred' => true, 'breaks' => [[50, 3.5], [100, 3.0]]],
                    ],
                    'inventory' => [
                        ['location' => 'B-1', 'stock_type' => 'tape', 'condition' => 'new', 'quantity' => 60, 'lot' => 'NPN-SOT23-T1'],
                    ],
                ],
                [
                    'part_number' => 'BK-DEMO-HEADER-1X04',
                    'manufacturer' => 'Samtec',
                    'common_name' => 'ピンヘッダ1x4',
                    'description' => '2.54mm ピンヘッダ 4pin',
                    'procurement_status' => 'active',
                    'threshold_new' => 20,
                    'threshold_used' => 5,
                    'package' => 'PinHeader-1x04-2.54',
                    'categories' => ['コネクタ'],
                    'specs' => [],
                    'suppliers' => [
                        ['name' => '秋月電子通商', 'pn' => 'CN-1X4-254', 'url' => 'https://example.com/ak/header4', 'purchase_unit' => '箱', 'unit_price' => 12.0, 'preferred' => true, 'breaks' => [[20, 10.0]]],
                    ],
                    'inventory' => [
                        ['location' => 'C-1', 'stock_type' => 'box', 'condition' => 'new', 'quantity' => 14, 'lot' => 'HDR4-B1'],
                        ['location' => 'C-1', 'stock_type' => 'box', 'condition' => 'used', 'quantity' => 2, 'lot' => 'HDR4-U1'],
                    ],
                ],
                [
                    'part_number' => 'BK-DEMO-EEPROM-DIP8',
                    'manufacturer' => 'Microchip',
                    'common_name' => 'EEPROM 24LC256',
                    'description' => 'I2C EEPROM 256Kbit',
                    'procurement_status' => 'eol',
                    'threshold_new' => 10,
                    'threshold_used' => 0,
                    'package' => 'DIP-8',
                    'categories' => ['アナログIC'],
                    'specs' => [],
                    'suppliers' => [
                        ['name' => 'Mouser', 'pn' => '579-24LC256-I/P', 'url' => 'https://example.com/ms/24lc256', 'purchase_unit' => 'トレー', 'unit_price' => 58.0, 'preferred' => true, 'breaks' => [[10, 55.0]]],
                    ],
                    'inventory' => [
                        ['location' => 'B-2', 'stock_type' => 'tray', 'condition' => 'new', 'quantity' => 7, 'lot' => '24LC256-N1'],
                    ],
                ],
            ];

            foreach ($components as $index => $data) {
                $component = Component::query()->updateOrCreate(
                    ['part_number' => $data['part_number']],
                    [
                        'manufacturer' => $data['manufacturer'],
                        'common_name' => $data['common_name'],
                        'description' => $data['description'],
                        'procurement_status' => $data['procurement_status'],
                        'threshold_new' => $data['threshold_new'],
                        'threshold_used' => $data['threshold_used'],
                        'primary_location_id' => $locations[$data['inventory'][0]['location']]->id ?? null,
                        'package_id' => $packages[$data['package']]->id,
                        'created_by' => $admin->id,
                        'updated_by' => $admin->id,
                    ]
                );

                $component->categories()->sync(
                    collect($data['categories'])->map(fn (string $name) => $categories[$name]->id)->all()
                );

                $component->specs()->delete();
                foreach ($data['specs'] as $spec) {
                    $component->specs()->create([
                        'spec_type_id' => $specTypes[$spec['type']]->id,
                        'value' => $spec['value'],
                        'unit' => $spec['unit'],
                        'value_numeric' => $spec['value_numeric'],
                    ]);
                }

                $component->componentSuppliers()->each(function (ComponentSupplier $componentSupplier) {
                    $componentSupplier->priceBreaks()->delete();
                });
                $component->componentSuppliers()->delete();

                foreach ($data['suppliers'] as $supplierData) {
                    $componentSupplier = $component->componentSuppliers()->create([
                        'supplier_id' => $suppliers[$supplierData['name']]->id,
                        'supplier_part_number' => $supplierData['pn'],
                        'product_url' => $supplierData['url'],
                        'purchase_unit' => $supplierData['purchase_unit'],
                        'unit_price' => $supplierData['unit_price'],
                        'price_updated_at' => now()->toDateString(),
                        'is_preferred' => $supplierData['preferred'],
                    ]);

                    foreach ($supplierData['breaks'] as [$minQty, $unitPrice]) {
                        $componentSupplier->priceBreaks()->create([
                            'min_qty' => $minQty,
                            'unit_price' => $unitPrice,
                        ]);
                    }
                }

                $component->transactions()->delete();
                $component->inventoryBlocks()->delete();

                $quantityNew = 0;
                $quantityUsed = 0;
                foreach ($data['inventory'] as $blockIndex => $blockData) {
                    $block = $component->inventoryBlocks()->create([
                        'location_id' => $locations[$blockData['location']]->id,
                        'stock_type' => $blockData['stock_type'],
                        'condition' => $blockData['condition'],
                        'quantity' => $blockData['quantity'],
                        'lot_number' => $blockData['lot'],
                        'reel_code' => $blockData['stock_type'] === 'reel' ? $blockData['lot'].'-RC' : null,
                        'note' => 'デモ在庫',
                    ]);

                    $createdAt = Carbon::now()->subDays(10 - $index)->subMinutes($blockIndex);
                    $component->transactions()->create([
                        'inventory_block_id' => $block->id,
                        'user_id' => $admin->id,
                        'type' => 'in',
                        'quantity' => $blockData['quantity'],
                        'quantity_before' => 0,
                        'quantity_after' => $blockData['quantity'],
                        'project_id' => null,
                        'note' => 'デモ初期入庫',
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]);

                    if ($blockData['condition'] === 'new') {
                        $quantityNew += $blockData['quantity'];
                    } else {
                        $quantityUsed += $blockData['quantity'];
                    }
                }

                $component->update([
                    'quantity_new' => $quantityNew,
                    'quantity_used' => $quantityUsed,
                ]);
            }
        });
    }
}
