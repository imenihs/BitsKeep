<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Package;
use App\Models\PackageGroup;
use App\Models\SpecType;
use App\Models\SpecTypeAlias;
use App\Models\SpecUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AkizukiReferenceMasterSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->seedCategories();
            $packageGroups = $this->seedPackageGroups();
            $this->seedPackages($packageGroups);
            $this->seedSpecTypes();
            $this->call(SpecGroupTemplateSeeder::class);
        });
    }

    private function seedCategories(): void
    {
        $rows = [
            ['name' => '抵抗器', 'description' => '炭素皮膜、金属皮膜、チップ抵抗、集合抵抗など', 'color' => '#ef4444', 'sort_order' => 10],
            ['name' => 'コンデンサ', 'description' => '積層セラミック、電解、フィルム、スーパーキャパシタなど', 'color' => '#3b82f6', 'sort_order' => 20],
            ['name' => 'ダイオード', 'description' => '小信号、整流、ショットキー、ツェナー、ブリッジダイオード', 'color' => '#f97316', 'sort_order' => 30],
            ['name' => 'LED', 'description' => '砲弾型、チップLED、赤外線LED、フォト系発光素子', 'color' => '#facc15', 'sort_order' => 40],
            ['name' => 'トランジスタ', 'description' => 'BJT、デジタルトランジスタ、トランジスタアレイ', 'color' => '#06b6d4', 'sort_order' => 50],
            ['name' => 'MOSFET', 'description' => 'Nch/Pch MOSFET、パワーMOSFET、ロジックレベルFET', 'color' => '#14b8a6', 'sort_order' => 60],
            ['name' => '電源IC', 'description' => 'DCDC、チャージポンプ、電源監視、保護IC', 'color' => '#10b981', 'sort_order' => 70],
            ['name' => 'レギュレータ', 'description' => '三端子レギュレータ、LDO、可変レギュレータ', 'color' => '#22c55e', 'sort_order' => 80],
            ['name' => 'アナログIC', 'description' => 'オペアンプ、コンパレータ、ADC/DAC、アナログスイッチ', 'color' => '#f59e0b', 'sort_order' => 90],
            ['name' => 'オペアンプ', 'description' => '単電源、低消費電流、高速、低オフセットのOPアンプ', 'color' => '#fb923c', 'sort_order' => 100],
            ['name' => 'ロジックIC', 'description' => '74HC/4000系、シフトレジスタ、バッファ、レベル変換', 'color' => '#8b5cf6', 'sort_order' => 110],
            ['name' => 'タイマIC', 'description' => '555系、RTC、発振器、タイミングデバイス', 'color' => '#a855f7', 'sort_order' => 120],
            ['name' => 'マイコン', 'description' => 'MCU単体、周辺IC、メモリ、プログラマブルデバイス', 'color' => '#6366f1', 'sort_order' => 130],
            ['name' => 'センサ', 'description' => '温湿度、気圧、光、距離、電流、加速度などのセンサ', 'color' => '#0ea5e9', 'sort_order' => 140],
            ['name' => '発振子', 'description' => '水晶発振子、セラロック、オシレータ、時計用水晶', 'color' => '#64748b', 'sort_order' => 150],
            ['name' => 'コネクタ', 'description' => 'ピンヘッダ、ピンソケット、JST、端子台、USBコネクタ', 'color' => '#84cc16', 'sort_order' => 160],
            ['name' => '開発ボード', 'description' => 'マイコンボード、センサモジュール、DIP化/変換基板', 'color' => '#ec4899', 'sort_order' => 170],
        ];

        foreach ($rows as $row) {
            $this->updateOrCreateWithRestore(Category::class, ['name' => $row['name']], $row);
        }
    }

    /**
     * @return array<string, PackageGroup>
     */
    private function seedPackageGroups(): array
    {
        $rows = [
            ['name' => 'SMD_LCR', 'description' => '1005/1608/2012/3216などのチップ抵抗・コンデンサ・インダクタ', 'sort_order' => 10],
            ['name' => 'アキシャル_LCR', 'description' => 'リード抵抗、アキシャル電解以外の軸方向リード部品', 'sort_order' => 20],
            ['name' => 'ラジアル_LC', 'description' => 'ラジアルリードの電解コンデンサ、積層セラミック、インダクタ', 'sort_order' => 30],
            ['name' => 'ダイオード', 'description' => 'DO-35/DO-41/SMA/SMBなどのダイオード外形', 'sort_order' => 40],
            ['name' => 'LED', 'description' => '3mm/5mm砲弾型、チップLED、フォト系LED外形', 'sort_order' => 50],
            ['name' => 'TO', 'description' => 'TO-92/TO-220/TO-252などのリード・パワーパッケージ', 'sort_order' => 60],
            ['name' => 'SOT', 'description' => 'SOT-23/SOT-89/SOT-223などの小型半導体パッケージ', 'sort_order' => 70],
            ['name' => 'SOP', 'description' => 'SOIC/SOP/SSOP/TSSOPなどのガルウイングICパッケージ', 'sort_order' => 80],
            ['name' => 'DIP', 'description' => 'DIP/SIP/ZIPなどのスルーホールIC・モジュール', 'sort_order' => 90],
            ['name' => 'QFN', 'description' => 'QFN/DFN/LGA/BGAなどのリードレスICパッケージ', 'sort_order' => 100],
            ['name' => 'QFP', 'description' => 'QFP/LQFP/TQFPなどの多ピンICパッケージ', 'sort_order' => 110],
            ['name' => '水晶/発振子', 'description' => 'HC-49/S、3225、5032などの発振子・発振器外形', 'sort_order' => 120],
            ['name' => 'コネクタ', 'description' => 'ピンヘッダ、ピンソケット、JST、端子台、USB、同軸コネクタ', 'sort_order' => 130],
            ['name' => 'モジュール基板', 'description' => 'センサ、DCDC、マイコン、変換基板などの基板モジュール', 'sort_order' => 140],
            ['name' => '半固定_CR', 'description' => 'トリマ抵抗、半固定ボリューム、トリマコンデンサ', 'sort_order' => 150],
        ];

        $groups = [];
        foreach ($rows as $row) {
            $group = $this->updateOrCreateWithRestore(PackageGroup::class, ['name' => $row['name']], $row);
            $groups[$group->name] = $group;
        }

        return $groups;
    }

    /**
     * @param  array<string, PackageGroup>  $groups
     */
    private function seedPackages(array $groups): void
    {
        $rows = [
            ['group' => 'SMD_LCR', 'name' => '0603', 'description' => 'JIS 0603 / 0.6 x 0.3 mm chip part', 'size_x' => 0.6, 'size_y' => 0.3, 'size_z' => 0.3, 'sort_order' => 10],
            ['group' => 'SMD_LCR', 'name' => '1005', 'description' => 'JIS 1005 / 1.0 x 0.5 mm chip part', 'size_x' => 1.0, 'size_y' => 0.5, 'size_z' => 0.35, 'sort_order' => 20],
            ['group' => 'SMD_LCR', 'name' => '1608', 'description' => 'JIS 1608 / 1.6 x 0.8 mm chip part', 'size_x' => 1.6, 'size_y' => 0.8, 'size_z' => 0.55, 'sort_order' => 30],
            ['group' => 'SMD_LCR', 'name' => '2012', 'description' => 'JIS 2012 / 2.0 x 1.25 mm chip part', 'size_x' => 2.0, 'size_y' => 1.25, 'size_z' => 0.65, 'sort_order' => 40],
            ['group' => 'SMD_LCR', 'name' => '3216', 'description' => 'JIS 3216 / 3.2 x 1.6 mm chip part', 'size_x' => 3.2, 'size_y' => 1.6, 'size_z' => 0.9, 'sort_order' => 50],
            ['group' => 'アキシャル_LCR', 'name' => 'Axial-1/4W', 'description' => '1/4W carbon/metal film resistor class', 'size_x' => 6.5, 'size_y' => 2.3, 'size_z' => null, 'sort_order' => 10],
            ['group' => 'ラジアル_LC', 'name' => 'Radial-2.54', 'description' => '2.54mm pitch radial lead capacitor/inductor', 'size_x' => 5.0, 'size_y' => 2.5, 'size_z' => 6.0, 'sort_order' => 10],
            ['group' => 'ラジアル_LC', 'name' => 'Electrolytic-6.3x11', 'description' => '6.3mm dia x 11mm radial electrolytic capacitor', 'size_x' => 6.3, 'size_y' => 6.3, 'size_z' => 11.0, 'sort_order' => 20],
            ['group' => 'ダイオード', 'name' => 'DO-35', 'description' => 'Glass axial diode, 1N4148 class', 'size_x' => 4.0, 'size_y' => 1.8, 'size_z' => null, 'sort_order' => 10],
            ['group' => 'ダイオード', 'name' => 'DO-41', 'description' => 'Axial rectifier diode, 1N400x/1N5819 class', 'size_x' => 5.2, 'size_y' => 2.7, 'size_z' => null, 'sort_order' => 20],
            ['group' => 'ダイオード', 'name' => 'SOD-123', 'description' => 'Small outline diode SMD package', 'size_x' => 2.7, 'size_y' => 1.6, 'size_z' => 1.1, 'sort_order' => 30],
            ['group' => 'ダイオード', 'name' => 'SMA', 'description' => 'DO-214AC surface mount diode', 'size_x' => 4.5, 'size_y' => 2.6, 'size_z' => 2.2, 'sort_order' => 40],
            ['group' => 'ダイオード', 'name' => 'SMB', 'description' => 'DO-214AA surface mount diode', 'size_x' => 5.4, 'size_y' => 3.6, 'size_z' => 2.3, 'sort_order' => 50],
            ['group' => 'LED', 'name' => 'LED-3mm', 'description' => '3mm round through-hole LED', 'size_x' => 3.0, 'size_y' => 3.0, 'size_z' => 5.3, 'sort_order' => 10],
            ['group' => 'LED', 'name' => 'LED-5mm', 'description' => '5mm round through-hole LED', 'size_x' => 5.0, 'size_y' => 5.0, 'size_z' => 8.7, 'sort_order' => 20],
            ['group' => 'LED', 'name' => 'LED-1608', 'description' => '1.6 x 0.8 mm chip LED', 'size_x' => 1.6, 'size_y' => 0.8, 'size_z' => 0.6, 'sort_order' => 30],
            ['group' => 'TO', 'name' => 'TO-92', 'description' => '3-lead through-hole transistor/FET package; Akizuki often writes TO92', 'size_x' => 4.8, 'size_y' => 3.7, 'size_z' => 4.8, 'sort_order' => 10],
            ['group' => 'TO', 'name' => 'TO-126', 'description' => 'Medium power through-hole transistor package', 'size_x' => 8.0, 'size_y' => 3.2, 'size_z' => 11.0, 'sort_order' => 20],
            ['group' => 'TO', 'name' => 'TO-220', 'description' => 'Power transistor/regulator package', 'size_x' => 10.0, 'size_y' => 4.5, 'size_z' => 15.0, 'sort_order' => 30],
            ['group' => 'TO', 'name' => 'TO-252', 'description' => 'DPAK surface mount power package', 'size_x' => 6.6, 'size_y' => 6.1, 'size_z' => 2.4, 'sort_order' => 40],
            ['group' => 'TO', 'name' => 'TO-263', 'description' => 'D2PAK surface mount power package', 'size_x' => 10.2, 'size_y' => 9.2, 'size_z' => 4.5, 'sort_order' => 50],
            ['group' => 'SOT', 'name' => 'SOT-23', 'description' => '3-pin small signal transistor/FET package', 'size_x' => 2.9, 'size_y' => 1.3, 'size_z' => 1.1, 'sort_order' => 10],
            ['group' => 'SOT', 'name' => 'SOT-23-5', 'description' => '5-pin SOT-23 IC package', 'size_x' => 2.9, 'size_y' => 1.6, 'size_z' => 1.1, 'sort_order' => 20],
            ['group' => 'SOT', 'name' => 'SOT-23-6', 'description' => '6-pin SOT-23 IC package', 'size_x' => 2.9, 'size_y' => 1.6, 'size_z' => 1.1, 'sort_order' => 30],
            ['group' => 'SOT', 'name' => 'SOT-89', 'description' => '3-pin power transistor/regulator package', 'size_x' => 4.5, 'size_y' => 2.5, 'size_z' => 1.5, 'sort_order' => 40],
            ['group' => 'SOT', 'name' => 'SOT223-3L', 'description' => '3-pin SOT-223 regulator package used by Akizuki LM1117 class parts', 'size_x' => 6.5, 'size_y' => 3.5, 'size_z' => 1.8, 'sort_order' => 50],
            ['group' => 'SOP', 'name' => 'SOIC-8', 'description' => '8-pin SOIC/SOP narrow body', 'size_x' => 4.9, 'size_y' => 3.9, 'size_z' => 1.75, 'sort_order' => 10],
            ['group' => 'SOP', 'name' => 'SOIC-14', 'description' => '14-pin SOIC/SOP narrow body', 'size_x' => 8.7, 'size_y' => 3.9, 'size_z' => 1.75, 'sort_order' => 20],
            ['group' => 'SOP', 'name' => 'SOIC-16', 'description' => '16-pin SOIC/SOP narrow body', 'size_x' => 9.9, 'size_y' => 3.9, 'size_z' => 1.75, 'sort_order' => 30],
            ['group' => 'SOP', 'name' => 'TSSOP-14', 'description' => '14-pin thin shrink small outline package', 'size_x' => 5.0, 'size_y' => 4.4, 'size_z' => 1.2, 'sort_order' => 40],
            ['group' => 'SOP', 'name' => 'TSSOP-16', 'description' => '16-pin thin shrink small outline package', 'size_x' => 5.0, 'size_y' => 4.4, 'size_z' => 1.2, 'sort_order' => 50],
            ['group' => 'DIP', 'name' => 'DIP-8', 'description' => '8-pin 2.54mm pitch DIP; Akizuki often writes DIP8', 'size_x' => 9.3, 'size_y' => 6.4, 'size_z' => 3.3, 'sort_order' => 10],
            ['group' => 'DIP', 'name' => 'DIP-14', 'description' => '14-pin 2.54mm pitch DIP', 'size_x' => 19.3, 'size_y' => 6.4, 'size_z' => 3.3, 'sort_order' => 20],
            ['group' => 'DIP', 'name' => 'DIP-16', 'description' => '16-pin 2.54mm pitch DIP; 74HC595 class', 'size_x' => 19.3, 'size_y' => 6.4, 'size_z' => 3.3, 'sort_order' => 30],
            ['group' => 'DIP', 'name' => 'DIP-28', 'description' => '28-pin 2.54mm pitch DIP; ATmega class', 'size_x' => 34.8, 'size_y' => 7.6, 'size_z' => 3.8, 'sort_order' => 40],
            ['group' => 'DIP', 'name' => 'SIP-6', 'description' => '6-pin single inline module/header package; BME280 module class', 'size_x' => 14.0, 'size_y' => 10.0, 'size_z' => 10.0, 'sort_order' => 50],
            ['group' => 'QFN', 'name' => 'QFN-16', 'description' => '16-pad leadless package', 'size_x' => 3.0, 'size_y' => 3.0, 'size_z' => 0.9, 'sort_order' => 10],
            ['group' => 'QFN', 'name' => 'QFN-32', 'description' => '32-pad leadless package', 'size_x' => 5.0, 'size_y' => 5.0, 'size_z' => 0.9, 'sort_order' => 20],
            ['group' => 'QFN', 'name' => 'QFN-48', 'description' => '48-pad leadless package', 'size_x' => 7.0, 'size_y' => 7.0, 'size_z' => 0.9, 'sort_order' => 30],
            ['group' => 'QFN', 'name' => 'QFN-56', 'description' => '56-pad leadless MCU package; RP2040 class', 'size_x' => 7.0, 'size_y' => 7.0, 'size_z' => 0.9, 'sort_order' => 40],
            ['group' => 'QFP', 'name' => 'LQFP-32', 'description' => '32-pin low-profile quad flat package', 'size_x' => 7.0, 'size_y' => 7.0, 'size_z' => 1.6, 'sort_order' => 10],
            ['group' => 'QFP', 'name' => 'LQFP-44', 'description' => '44-pin low-profile quad flat package', 'size_x' => 10.0, 'size_y' => 10.0, 'size_z' => 1.6, 'sort_order' => 20],
            ['group' => 'QFP', 'name' => 'LQFP-48', 'description' => '48-pin low-profile quad flat package', 'size_x' => 7.0, 'size_y' => 7.0, 'size_z' => 1.6, 'sort_order' => 30],
            ['group' => 'QFP', 'name' => 'LQFP-64', 'description' => '64-pin low-profile quad flat package', 'size_x' => 10.0, 'size_y' => 10.0, 'size_z' => 1.6, 'sort_order' => 40],
            ['group' => '水晶/発振子', 'name' => 'HC-49/S', 'description' => 'Through-hole crystal, 10.7 x 4.3 x 3.5 mm class', 'size_x' => 10.7, 'size_y' => 4.3, 'size_z' => 3.5, 'sort_order' => 10],
            ['group' => '水晶/発振子', 'name' => 'Crystal-3225', 'description' => '3.2 x 2.5 mm SMD crystal package', 'size_x' => 3.2, 'size_y' => 2.5, 'size_z' => 0.7, 'sort_order' => 20],
            ['group' => '水晶/発振子', 'name' => 'Oscillator-5032', 'description' => '5.0 x 3.2 mm SMD oscillator package', 'size_x' => 5.0, 'size_y' => 3.2, 'size_z' => 1.3, 'sort_order' => 30],
            ['group' => 'コネクタ', 'name' => 'PinHeader-1x04-2.54', 'description' => '2.54mm pitch 1x04 pin header', 'size_x' => 10.16, 'size_y' => 2.54, 'size_z' => 8.5, 'sort_order' => 10],
            ['group' => 'コネクタ', 'name' => 'PinHeader-1x40-2.54', 'description' => '2.54mm pitch 1x40 breakaway pin header', 'size_x' => 101.6, 'size_y' => 2.54, 'size_z' => 11.5, 'sort_order' => 20],
            ['group' => 'コネクタ', 'name' => 'JST-PH-2P', 'description' => '2.0mm pitch 2-pin wire-to-board connector', 'size_x' => 6.0, 'size_y' => 4.5, 'size_z' => 5.8, 'sort_order' => 30],
            ['group' => 'コネクタ', 'name' => 'JST-XH-2P', 'description' => '2.5mm pitch 2-pin wire-to-board connector', 'size_x' => 7.5, 'size_y' => 6.0, 'size_z' => 7.0, 'sort_order' => 40],
            ['group' => 'コネクタ', 'name' => 'TerminalBlock-2P-5.08', 'description' => '5.08mm pitch 2-position screw terminal block', 'size_x' => 10.2, 'size_y' => 8.2, 'size_z' => 10.0, 'sort_order' => 50],
            ['group' => 'コネクタ', 'name' => 'USB-C-Receptacle', 'description' => 'USB Type-C receptacle connector, board edge/SMD class', 'size_x' => 9.0, 'size_y' => 7.4, 'size_z' => 3.3, 'sort_order' => 60],
            ['group' => 'モジュール基板', 'name' => 'Module-14x10', 'description' => 'Small sensor DIP module, BME280 class', 'size_x' => 14.0, 'size_y' => 10.0, 'size_z' => 10.0, 'sort_order' => 10],
            ['group' => 'モジュール基板', 'name' => 'Module-21x21', 'description' => 'Compact MCU/module board, PGA2040 class', 'size_x' => 21.0, 'size_y' => 21.0, 'size_z' => 3.0, 'sort_order' => 20],
            ['group' => 'モジュール基板', 'name' => 'Module-50x21', 'description' => 'Nano/Pico class development board footprint', 'size_x' => 50.0, 'size_y' => 21.0, 'size_z' => 5.0, 'sort_order' => 30],
        ];

        foreach ($rows as $row) {
            $groupName = $row['group'];
            unset($row['group']);
            $row['package_group_id'] = $groups[$groupName]->id;

            $this->updateOrCreateWithRestore(Package::class, ['name' => $row['name']], $row);
        }
    }

    private function seedSpecTypes(): void
    {
        $rows = [
            $this->spec('抵抗値', 'Resistance', 'R', 'Ω', '抵抗器、シャント、半固定抵抗の抵抗値', 10, ['M', 'k', '', 'm'], ['M', 'k', '', 'm'], ['抵抗', 'resistance', 'ohm', 'R']),
            $this->spec('容量', 'Capacitance', 'C', 'F', 'コンデンサ、端子間容量、ゲート容量など', 20, ['', 'm', 'u', 'n', 'p'], ['u', 'n', 'p'], ['静電容量', 'capacitance', 'C']),
            $this->spec('インダクタンス', 'Inductance', 'L', 'H', 'コイル、インダクタ、フェライト部品のインダクタンス', 30, ['', 'm', 'u', 'n'], ['m', 'u', 'n'], ['inductance', 'L']),
            $this->spec('許容差', 'Tolerance', 'Tol', '%', '抵抗、容量、周波数などの許容差', 40, [''], [''], ['tolerance', '精度', '公差', '許容差']),
            $this->spec('耐圧', 'Rated Voltage', 'Vrated', 'V', 'コンデンサ、抵抗、モジュールの定格電圧または耐電圧', 50, ['k', '', 'm'], ['k', '', 'm'], ['定格電圧', 'rated voltage', 'working voltage', 'WV']),
            $this->spec('電圧', 'Voltage', 'V', 'V', '汎用の電圧値。用途が明確なら入力/出力/電源電圧を優先', 60, ['k', '', 'm', 'u'], ['', 'm', 'u'], ['voltage', 'V']),
            $this->spec('入力電圧', 'Input Voltage', 'VIN', 'V', 'レギュレータ、DCDC、モジュールの入力電圧範囲', 70, ['k', '', 'm'], ['', 'm'], ['Vin', 'V_IN', 'Input Voltage', '入力電圧max', '入力電圧min']),
            $this->spec('出力電圧', 'Output Voltage', 'VOUT', 'V', 'レギュレータ、DCDC、基準電圧ICの出力電圧', 80, ['k', '', 'm'], ['', 'm'], ['Vout', 'V_OUT', 'Output Voltage']),
            $this->spec('電源電圧', 'Supply Voltage', 'VCC/VDD', 'V', 'IC、センサ、モジュールの動作電源電圧範囲', 90, ['k', '', 'm'], ['', 'm'], ['VCC', 'VDD', 'VDDIO', 'Supply Voltage', 'Operating Voltage', '動作電源電圧', '電源電圧min', '電源電圧max']),
            $this->spec('IO電圧', 'IO Voltage', 'VIO', 'V', 'マイコン、レベル変換、モジュールのI/O電圧', 100, ['k', '', 'm'], ['', 'm'], ['I/O Voltage', 'IO Voltage', 'V_IO', 'VIO', 'IO電圧min', 'IO電圧max']),
            $this->spec('電流', 'Current', 'I', 'A', '汎用の電流値。用途が明確なら入力/出力/順方向電流を優先', 110, ['', 'm', 'u', 'n'], ['', 'm', 'u'], ['current', 'I']),
            $this->spec('入力電流', 'Input Current', 'IIN', 'A', 'IC、モジュール、電源回路の入力電流', 120, ['', 'm', 'u', 'n'], ['', 'm', 'u'], ['Iin', 'Input Current']),
            $this->spec('出力電流', 'Output Current', 'IOUT', 'A', 'レギュレータ、DCDC、ドライバ、IC出力の最大電流', 130, ['', 'm', 'u', 'n'], ['', 'm', 'u'], ['Iout', 'Output Current', '出力電流max']),
            $this->spec('消費電流', 'Supply Current', 'ICC/Iq', 'A', 'IC、センサ、レギュレータの自己消費電流または静的電流', 140, ['', 'm', 'u', 'n'], ['m', 'u', 'n'], ['Iq', 'ICC', 'IDD', 'Supply Current', 'Quiescent Current', '静的消費電流', '動作電流']),
            $this->spec('全損失', 'Power Dissipation', 'Pd', 'W', 'パッケージや素子の最大許容損失', 150, ['', 'm', 'u'], ['', 'm'], ['許容損失', '許容損失max', 'Power Dissipation', 'P_D', 'Pd', 'PT']),
            $this->spec('動作温度', 'Operating Temperature', 'Topr', '℃', '部品の動作温度範囲', 160, [''], [''], ['Operating Temperature', '動作温度min', '動作温度max', 'Topr']),
            $this->spec('保存温度', 'Storage Temperature', 'Tstg', '℃', '非通電保管時の温度範囲', 170, [''], [''], ['Storage Temperature', 'Tstg']),
            $this->spec('ジャンクション温度', 'Junction Temperature', 'Tj', '℃', '半導体チップのジャンクション温度', 180, [''], [''], ['Junction Temperature', 'Tj']),
            $this->spec('端子数', 'Pin Count', 'pins', 'pin', 'パッケージ、コネクタ、モジュールの端子数', 190, [''], [''], ['ピン数', 'pins', 'pin count', '端子']),
            $this->spec('端子ピッチ', 'Pin Pitch', 'pitch', 'mm', 'DIP、コネクタ、モジュール端子のピッチ', 200, ['', 'm'], ['', 'm'], ['pitch', 'lead pitch', '端子ピッチ']),

            $this->spec('コレクタ-エミッタ間電圧', 'Collector-Emitter Voltage', 'VCEO', 'V', 'BJTのコレクタ-エミッタ間最大電圧', 300, ['k', '', 'm'], ['', 'm'], ['VCEO', 'VCeo', 'コレクターエミッター間電圧', 'Collector Emitter Voltage']),
            $this->spec('コレクタ-ベース間電圧', 'Collector-Base Voltage', 'VCBO', 'V', 'BJTのコレクタ-ベース間最大電圧', 310, ['k', '', 'm'], ['', 'm'], ['VCBO', 'コレクターベース間電圧', 'Collector Base Voltage']),
            $this->spec('エミッタ-ベース間電圧', 'Emitter-Base Voltage', 'VEBO', 'V', 'BJTのエミッタ-ベース間最大電圧', 320, ['k', '', 'm'], ['', 'm'], ['VEBO', 'エミッターベース間電圧', 'Emitter Base Voltage']),
            $this->spec('コレクタ電流', 'Collector Current', 'IC', 'A', 'BJTの連続コレクタ電流', 330, ['', 'm', 'u'], ['', 'm'], ['Ic', 'I_C', 'コレクター電流', 'Collector Current']),
            $this->spec('ベース電流', 'Base Current', 'Ibase', 'A', 'BJTのベース電流定格', 340, ['', 'm', 'u'], ['m', 'u'], ['Base Current']),
            $this->spec('直流電流増幅率', 'DC Current Gain', 'hFE', null, 'BJTの直流電流増幅率。min/typ/maxの範囲管理に使う', 350, [], [], ['hFE', 'hfe', 'DC current gain', '直流電流増幅率min', '直流電流増幅率max']),
            $this->spec('コレクタ-エミッタ飽和電圧', 'Collector-Emitter Saturation Voltage', 'VCE(sat)', 'V', 'BJTの飽和時コレクタ-エミッタ電圧', 360, ['', 'm'], ['m', ''], ['VCE(sat)', 'V_CE(sat)', 'コレクターエミッター飽和電圧']),
            $this->spec('ベース-エミッタ飽和電圧', 'Base-Emitter Saturation Voltage', 'VBE(sat)', 'V', 'BJTの飽和時ベース-エミッタ電圧', 370, ['', 'm'], ['m', ''], ['VBE(sat)', 'V_BE(sat)', 'ベースエミッター飽和電圧']),
            $this->spec('トランジション周波数', 'Transition Frequency', 'fT', 'Hz', 'BJT/FETの高周波特性。利得帯域幅積とは別管理', 380, ['G', 'M', 'k', ''], ['M', 'k', ''], ['fT', 'transition frequency', '遮断周波数', 'トランジション周波数']),
            $this->spec('雑音指数', 'Noise Figure', 'NF', 'dB', 'トランジスタ、RF/低雑音アンプの雑音指数', 390, [''], [''], ['noise figure', 'NF', '雑音指数']),

            $this->spec('ドレイン-ソース間電圧', 'Drain-Source Voltage', 'VDSS', 'V', 'MOSFETのドレイン-ソース間最大電圧', 400, ['k', '', 'm'], ['', 'm'], ['VDSS', 'VDS', 'V_DS', 'ドレインソース間電圧']),
            $this->spec('ドレイン電流', 'Drain Current', 'ID', 'A', 'MOSFETの連続ドレイン電流', 410, ['', 'm', 'u'], ['', 'm'], ['Id', 'I_D', 'Drain Current', 'ドレイン電流DC']),
            $this->spec('ピークドレイン電流', 'Peak Drain Current', 'IDM', 'A', 'MOSFETのピークドレイン電流', 420, ['', 'm', 'u'], ['', 'm'], ['IDM', 'I_DM', 'Peak Drain Current', 'ピークドレイン電流']),
            $this->spec('ゲート-ソース間電圧', 'Gate-Source Voltage', 'VGSS', 'V', 'MOSFETのゲート-ソース間最大電圧', 430, ['', 'm'], ['', 'm'], ['VGSS', 'VGS', 'V_GS', 'ゲートソース間電圧']),
            $this->spec('ゲート漏れ電流', 'Gate Leakage Current', 'IGSS', 'A', 'MOSFETのゲート漏れ電流', 440, ['', 'm', 'u', 'n'], ['u', 'n'], ['IGSS', 'Gate Leakage', 'ゲート漏れ電流']),
            $this->spec('オン抵抗', 'Drain-Source On Resistance', 'RDS(on)', 'Ω', 'MOSFETのオン抵抗', 450, ['', 'm'], ['', 'm'], ['RDS(on)', 'R_DS(on)', 'オン抵抗', 'Drain-Source On Resistance']),
            $this->spec('ゲートしきい値電圧', 'Gate Threshold Voltage', 'VGS(th)', 'V', 'MOSFETのゲートしきい値電圧', 460, ['', 'm'], ['', 'm'], ['VGS(th)', 'V_GS(th)', 'Gate Threshold Voltage', 'ゲートソースしきい値電圧']),
            $this->spec('ゲート電荷', 'Total Gate Charge', 'Qg', 'C', 'MOSFETのゲート電荷', 470, ['', 'm', 'u', 'n', 'p'], ['n', 'p'], ['Qg', 'Q_G', 'Total Gate Charge', 'ゲート電荷']),

            $this->spec('ピーク耐圧', 'Repetitive Peak Reverse Voltage', 'VRRM', 'V', 'ダイオードのピーク逆耐圧またはピーク耐圧', 500, ['k', '', 'm'], ['k', ''], ['VRRM', 'V_RRM', 'ピーク耐圧', 'Peak Reverse Voltage']),
            $this->spec('DC耐圧', 'DC Blocking Voltage', 'VR', 'V', 'ダイオードのDC耐圧、逆方向定格電圧', 510, ['k', '', 'm'], ['k', ''], ['VR', 'V_R', 'DC耐圧', 'Reverse Voltage']),
            $this->spec('平均順電流', 'Average Forward Current', 'IF(AV)', 'A', 'ダイオードの平均順方向電流', 520, ['', 'm'], ['', 'm'], ['IF(AV)', 'I_F(AV)', '平均順電流']),
            $this->spec('ピーク順電流', 'Peak Forward Current', 'IFSM', 'A', 'ダイオードのサージまたはピーク順方向電流', 530, ['', 'm'], ['', 'm'], ['IFSM', 'I_FSM', 'ピーク順電流']),
            $this->spec('順方向電圧', 'Forward Voltage', 'VF', 'V', 'ダイオード、LED、フォトカプラLED側の順方向電圧', 540, ['', 'm'], ['', 'm'], ['VF', 'V_F', '順電圧', 'Forward Voltage']),
            $this->spec('順方向電流', 'Forward Current', 'IF', 'A', 'ダイオード、LED、フォトカプラLED側の順方向電流', 550, ['', 'm', 'u'], ['', 'm'], ['IF', 'I_F', 'Forward Current']),
            $this->spec('逆回復時間', 'Reverse Recovery Time', 'trr', 's', 'スイッチングダイオード、整流ダイオードの逆回復時間', 560, ['', 'm', 'u', 'n', 'p'], ['n', 'p'], ['trr', 't_rr', 'Reverse Recovery Time', '逆回復時間']),
            $this->spec('端子間容量', 'Junction Capacitance', 'Cj', 'F', 'ダイオード、トランジスタ、FETの端子間容量', 570, ['', 'm', 'u', 'n', 'p'], ['n', 'p'], ['Cj', 'CJ', 'Cob', '端子間容量', 'Junction Capacitance']),
            $this->spec('ツェナー電圧', 'Zener Voltage', 'VZ', 'V', 'ツェナーダイオードのツェナー電圧', 580, ['k', '', 'm'], ['', 'm'], ['Vz', 'V_Z', 'Zener Voltage']),
            $this->spec('発光波長', 'Wavelength', 'λp', 'm', 'LED、フォトダイオード、光センサの中心/ピーク波長', 590, ['', 'm', 'u', 'n'], ['n'], ['wavelength', 'lambda', 'λ', '発光波長', 'ピーク波長']),
            $this->spec('光度', 'Luminous Intensity', 'Iv', 'cd', 'LEDの光度。mcd入力を想定', 600, ['', 'm', 'u'], ['m'], ['Iv', 'luminous intensity', '光度', 'mcd']),
            $this->spec('指向角', 'Viewing Angle', 'θ', 'deg', 'LEDやセンサの指向角、半値角', 610, [''], [''], ['Viewing Angle', '指向角', '半値角', 'view angle']),

            $this->spec('ドロップアウト電圧', 'Dropout Voltage', 'Vdrop', 'V', 'LDO、三端子レギュレータのドロップアウト電圧', 700, ['', 'm'], ['', 'm'], ['Dropout Voltage', 'Vdrop', 'V_DO', 'ドロップアウト電圧']),
            $this->spec('基準電圧', 'Reference Voltage', 'Vref', 'V', '可変レギュレータ、基準電圧IC、ADC/DACの基準電圧', 710, ['', 'm'], ['', 'm'], ['Vref', 'V_REF', 'Reference Voltage', '基準電圧']),
            $this->spec('リップル除去比', 'Ripple Rejection Ratio', 'PSRR', 'dB', 'レギュレータ、アンプのリップル除去比またはPSRR', 720, [''], [''], ['PSRR', 'Ripple Rejection', 'リップル除去比']),
            $this->spec('チャンネル数', 'Channel Count', 'ch', 'ch', 'アンプ、ADC、DCDC、インターフェイスのチャンネル数', 730, [''], [''], ['channels', 'channel count', '出力チャンネル数', 'チャンネル数']),
            $this->spec('回路数', 'Circuit Count', 'circuits', 'ch', 'オペアンプ、コンパレータ、タイマなどの回路数', 740, [''], [''], ['回路数', 'Circuit Count', 'Number of Circuits']),
            $this->spec('入力数', 'Input Count', 'inputs', 'ch', 'ロジックIC、シフトレジスタ、ゲートICの入力数', 750, [''], [''], ['input count', '入力数']),
            $this->spec('出力数', 'Output Count', 'outputs', 'ch', 'ロジックIC、ドライバ、シフトレジスタの出力数', 760, [''], [''], ['output count', '出力数']),
            $this->spec('入力オフセット電圧', 'Input Offset Voltage', 'Vos', 'V', 'オペアンプ、コンパレータの入力オフセット電圧', 770, ['', 'm', 'u'], ['m', 'u'], ['Input Offset Voltage', 'V_OS', '入力オフセット電圧']),
            $this->spec('入力バイアス電流', 'Input Bias Current', 'IBIAS', 'A', 'オペアンプ、コンパレータの入力バイアス電流', 780, ['', 'm', 'u', 'n', 'p'], ['n', 'p'], ['Input Bias Current', 'Ibias', '入力バイアス電流']),
            $this->spec('利得帯域幅積', 'Gain Bandwidth Product', 'GBW', 'Hz', 'オペアンプの利得帯域幅積', 790, ['G', 'M', 'k', ''], ['M', 'k', ''], ['GBW', 'GBP', 'Gain Bandwidth', 'Gain Bandwidth Product']),
            $this->spec('スルーレート', 'Slew Rate', 'SR', 'V/us', 'オペアンプのスルーレート', 800, [''], [''], ['Slew Rate', 'SR', 'スルーレート']),
            $this->spec('オープンループゲイン', 'Open Loop Gain', 'AOL', 'dB', 'オペアンプ、コンパレータのオープンループゲイン', 810, [''], [''], ['Open Loop Gain', 'AOL', 'A_OL', 'オープンループゲイン']),
            $this->spec('伝播遅延時間', 'Propagation Delay', 'tpd', 's', 'ロジックIC、コンパレータ、レベル変換ICの伝播遅延時間', 820, ['', 'm', 'u', 'n', 'p'], ['n', 'p'], ['Propagation Delay', 'tpd', 't_pd', '伝播遅延時間', '伝搬遅延時間']),
            $this->spec('最大発振周波数', 'Maximum Oscillation Frequency', 'fmax', 'Hz', 'タイマ、発振器、ロジックICの最大動作/発振周波数', 830, ['G', 'M', 'k', ''], ['M', 'k', ''], ['fmax', 'Maximum Frequency', '最大発振周波数', '最大クロック']),
            $this->spec('クロック周波数', 'Clock Frequency', 'fclk', 'Hz', 'マイコン、ロジック、発振子、モジュールのクロック周波数', 840, ['G', 'M', 'k', ''], ['M', 'k', ''], ['clock', 'clock frequency', 'fclk', 'クロック', '周波数']),

            $this->spec('フラッシュ容量', 'Flash Memory Size', 'Flash', 'B', 'マイコン、モジュールのフラッシュ/ROM容量', 900, ['G', 'M', 'k', ''], ['M', 'k'], ['flash', 'ROM', 'ROM容量', 'フラッシュ', 'Flash Memory']),
            $this->spec('RAM容量', 'RAM Size', 'RAM', 'B', 'マイコン、モジュールのRAM容量', 910, ['G', 'M', 'k', ''], ['M', 'k'], ['RAM', 'RAM容量', 'SRAM']),
            $this->spec('GPIO数', 'GPIO Count', 'GPIO', 'pin', 'マイコン、モジュールのGPIO数', 920, [''], [''], ['GPIO', 'GPIO Count', 'GPIO数']),
            $this->spec('ADCチャンネル数', 'ADC Channel Count', 'ADC', 'ch', 'マイコン、ADC、センサモジュールのADCチャンネル数', 930, [''], [''], ['ADC', 'ADコンバーター', 'ADC channels', 'ADCチャンネル数']),
            $this->spec('I2C数', 'I2C Count', 'I2C', 'ch', 'マイコン、モジュールのI2Cポート数', 940, [''], [''], ['I2C', 'I²C', 'IIC', 'I2C数']),
            $this->spec('SPI数', 'SPI Count', 'SPI', 'ch', 'マイコン、モジュールのSPIポート数', 950, [''], [''], ['SPI', 'SPI数']),
            $this->spec('UART数', 'UART Count', 'UART', 'ch', 'マイコン、モジュールのUART/USART数', 960, [''], [''], ['UART', 'USART', 'UART/USART', 'UART数']),
            $this->spec('コアビット数', 'Core Size', 'Core', 'bit', 'マイコンCPUコアのビット数', 970, [''], [''], ['core size', 'コアサイズ', '32bit', '64bit']),
            $this->spec('コア数', 'Core Count', 'cores', 'core', 'マイコン、SoCのCPUコア数', 980, [''], [''], ['core count', 'コア', 'コア数']),
            $this->spec('分解能', 'Resolution', 'res', 'bit', 'ADC/DAC、センサ、PWMなどの分解能', 990, [''], [''], ['resolution', 'bit depth', '分解能']),

            $this->spec('測定温度', 'Measured Temperature Range', 'Tmeas', '℃', '温度センサ、環境センサの測定温度範囲', 1000, [''], [''], ['測定温度min', '測定温度max', 'Temperature Measurement Range']),
            $this->spec('測定湿度', 'Measured Humidity Range', 'RH', '%', '湿度センサの測定湿度範囲', 1010, [''], [''], ['humidity', 'RH', '測定湿度min', '測定湿度max']),
            $this->spec('測定気圧', 'Measured Pressure Range', 'Pmeas', 'hPa', '気圧センサの測定気圧範囲', 1020, [''], [''], ['pressure', 'barometric pressure', '測定気圧min', '測定気圧max']),
            $this->spec('負荷容量', 'Load Capacitance', 'CL', 'F', '水晶発振子、セラロックの負荷容量', 1030, ['', 'm', 'u', 'n', 'p'], ['p'], ['CL', 'C_L', 'Load Capacitance', '負荷容量']),
            $this->spec('周波数許容差', 'Frequency Tolerance', 'tol', 'ppm', '水晶発振子、発振器の周波数許容差', 1040, [''], [''], ['frequency tolerance', 'ppm', '周波数許容差']),
            $this->spec('温度周波数特性', 'Frequency Stability', 'stability', 'ppm', '水晶発振子、発振器の温度周波数特性', 1050, [''], [''], ['frequency stability', '周波数温度特性', '温度周波数特性']),
        ];

        foreach ($rows as $row) {
            $units = $row['units'];
            $aliases = $row['aliases'];
            unset($row['units'], $row['aliases']);

            $specType = $this->updateOrCreateWithRestore(SpecType::class, ['name' => $row['name']], $row);
            $this->seedSpecUnits($specType, $units);
            $this->seedSpecAliases($specType, $aliases);
            $this->removeAmbiguousAliases($specType);
        }
    }

    /**
     * @param  array<int, string>  $suggestPrefixes
     * @param  array<int, string>  $displayPrefixes
     * @param  array<int, string>  $aliases
     * @return array<string, mixed>
     */
    private function spec(
        string $name,
        string $nameEn,
        string $symbol,
        ?string $baseUnit,
        string $description,
        int $sortOrder,
        array $suggestPrefixes,
        array $displayPrefixes,
        array $aliases
    ): array {
        return [
            'name' => $name,
            'name_ja' => $name,
            'name_en' => $nameEn,
            'symbol' => $symbol,
            'base_unit' => $baseUnit,
            'description' => $description,
            'sort_order' => $sortOrder,
            'suggest_prefixes' => $baseUnit ? $suggestPrefixes : null,
            'display_prefixes' => $baseUnit ? $displayPrefixes : null,
            'units' => $baseUnit ? [$baseUnit] : [],
            'aliases' => array_values(array_unique(array_filter([
                $name,
                $nameEn,
                $symbol,
                ...$aliases,
            ], fn ($alias) => trim((string) $alias) !== ''))),
        ];
    }

    /**
     * @param  array<int, string>  $units
     */
    private function seedSpecUnits(SpecType $specType, array $units): void
    {
        foreach (array_values(array_unique($units)) as $index => $unit) {
            SpecUnit::query()->updateOrCreate(
                ['spec_type_id' => $specType->id, 'unit' => $unit],
                ['factor' => '1', 'sort_order' => ($index + 1) * 10]
            );
        }
    }

    /**
     * @param  array<int, string>  $aliases
     */
    private function seedSpecAliases(SpecType $specType, array $aliases): void
    {
        foreach ($aliases as $index => $alias) {
            SpecTypeAlias::query()->updateOrCreate(
                ['spec_type_id' => $specType->id, 'alias' => $alias],
                [
                    'locale' => preg_match('/[ぁ-んァ-ン一-龠]/u', $alias) ? 'ja' : 'en',
                    'kind' => $alias === $specType->symbol ? 'symbol' : 'alias',
                    'sort_order' => ($index + 1) * 10,
                ]
            );
        }
    }

    private function removeAmbiguousAliases(SpecType $specType): void
    {
        $denyList = [
            'ベース電流' => ['IB', 'Ib', 'I_B'],
            '入力オフセット電圧' => ['VIO'],
            '入力バイアス電流' => ['IB', 'I_B'],
            '測定気圧' => ['P'],
        ];

        $aliases = $denyList[$specType->name] ?? [];
        if ($aliases === []) {
            return;
        }

        SpecTypeAlias::query()
            ->where('spec_type_id', $specType->id)
            ->whereIn('alias', $aliases)
            ->delete();
    }

    /**
     * @template TModel of Model
     * @param  class-string<TModel>  $modelClass
     * @return TModel
     */
    private function updateOrCreateWithRestore(string $modelClass, array $lookup, array $values): Model
    {
        $model = $modelClass::withTrashed()->updateOrCreate($lookup, $values);
        if (method_exists($model, 'trashed') && $model->trashed()) {
            $model->restore();
        }

        return $model;
    }
}
