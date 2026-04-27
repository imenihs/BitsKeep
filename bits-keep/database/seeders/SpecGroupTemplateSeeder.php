<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\SpecGroup;
use App\Models\SpecTemplate;
use App\Models\SpecTemplateItem;
use App\Models\SpecType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SpecGroupTemplateSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $specTypes = SpecType::query()->get()->keyBy('name');
            $categories = Category::query()->get()->keyBy('name');
            $groups = $this->seedGroups($categories);
            $this->seedGroupMembers($groups, $specTypes);
            $this->seedTemplates($groups, $specTypes);
        });
    }

    /**
     * @param  \Illuminate\Support\Collection<string, Category>  $categories
     * @return array<string, SpecGroup>
     */
    private function seedGroups($categories): array
    {
        $rows = [
            ['name' => '共通', 'description' => '多くの部品分類で共通して使う基本スペック', 'sort_order' => 10, 'categories' => []],
            ['name' => 'BJT', 'description' => 'バイポーラトランジスタの代表スペック', 'sort_order' => 20, 'categories' => ['トランジスタ']],
            ['name' => 'MOSFET', 'description' => 'MOSFETの代表スペック', 'sort_order' => 30, 'categories' => ['MOSFET']],
            ['name' => 'ダイオード/LED', 'description' => 'ダイオード、LED、光半導体の代表スペック', 'sort_order' => 40, 'categories' => ['ダイオード', 'LED']],
            ['name' => '電源IC/レギュレータ', 'description' => 'DCDC、LDO、三端子レギュレータの代表スペック', 'sort_order' => 50, 'categories' => ['電源IC', 'レギュレータ']],
            ['name' => 'OPアンプ/コンパレータ', 'description' => 'OPアンプ、コンパレータの代表スペック', 'sort_order' => 60, 'categories' => ['アナログIC', 'オペアンプ']],
            ['name' => 'ロジックIC', 'description' => 'ロジックIC、レベル変換、タイミング系ICの代表スペック', 'sort_order' => 70, 'categories' => ['ロジックIC', 'タイマIC']],
            ['name' => 'マイコン', 'description' => 'MCU、周辺IC、開発ボードの代表スペック', 'sort_order' => 80, 'categories' => ['マイコン', '開発ボード']],
            ['name' => 'センサ', 'description' => '環境センサ、物理量センサの代表スペック', 'sort_order' => 90, 'categories' => ['センサ']],
            ['name' => '発振子', 'description' => '水晶発振子、セラロック、オシレータの代表スペック', 'sort_order' => 100, 'categories' => ['発振子']],
        ];

        $groups = [];
        foreach ($rows as $row) {
            $categoryNames = $row['categories'];
            unset($row['categories']);

            $group = $this->updateOrCreateWithRestore(SpecGroup::class, ['name' => $row['name']], $row);
            $groups[$group->name] = $group;

            $sync = [];
            foreach ($categoryNames as $index => $categoryName) {
                $category = $categories->get($categoryName);
                if (!$category) {
                    continue;
                }
                $sync[$category->id] = [
                    'sort_order' => ($index + 1) * 10,
                    'is_primary' => $index === 0,
                ];
            }
            if ($sync !== []) {
                $group->categories()->syncWithoutDetaching($sync);
            }
        }

        return $groups;
    }

    /**
     * @param  array<string, SpecGroup>  $groups
     * @param  \Illuminate\Support\Collection<string, SpecType>  $specTypes
     */
    private function seedGroupMembers(array $groups, $specTypes): void
    {
        $rows = [
            '共通' => ['電源電圧', '動作温度', '保存温度', '端子数', '端子ピッチ', '全損失'],
            'BJT' => ['コレクタ-エミッタ間電圧', 'コレクタ-ベース間電圧', 'エミッタ-ベース間電圧', 'コレクタ電流', '直流電流増幅率', 'コレクタ-エミッタ飽和電圧', 'トランジション周波数'],
            'MOSFET' => ['ドレイン-ソース間電圧', 'ドレイン電流', 'ゲート-ソース間電圧', 'オン抵抗', 'ゲートしきい値電圧', 'ゲート電荷', '全損失'],
            'ダイオード/LED' => ['ピーク耐圧', '平均順電流', '順方向電圧', '順方向電流', '逆回復時間', '端子間容量', '発光波長', '光度', '指向角'],
            '電源IC/レギュレータ' => ['入力電圧', '出力電圧', '出力電流', 'ドロップアウト電圧', '消費電流', 'リップル除去比', '基準電圧'],
            'OPアンプ/コンパレータ' => ['回路数', '電源電圧', '入力オフセット電圧', '入力バイアス電流', '利得帯域幅積', 'スルーレート', 'オープンループゲイン'],
            'ロジックIC' => ['電源電圧', '入力数', '出力数', '伝播遅延時間', '最大発振周波数', '出力電流'],
            'マイコン' => ['電源電圧', 'フラッシュ容量', 'RAM容量', 'GPIO数', 'ADCチャンネル数', 'クロック周波数'],
            'センサ' => ['電源電圧', '消費電流', '測定温度', '測定湿度', '測定気圧', '分解能'],
            '発振子' => ['クロック周波数', '負荷容量', '周波数許容差', '温度周波数特性', '動作温度'],
        ];

        foreach ($rows as $groupName => $specNames) {
            $group = $groups[$groupName] ?? null;
            if (!$group) {
                continue;
            }

            $sync = [];
            foreach ($specNames as $index => $specName) {
                $specType = $specTypes->get($specName);
                if (!$specType) {
                    continue;
                }
                $sync[$specType->id] = [
                    'sort_order' => ($index + 1) * 10,
                    'is_required' => $index < 2,
                    'is_recommended' => true,
                    'default_profile' => 'typ',
                    'default_unit' => $specType->base_unit,
                    'note' => null,
                ];
            }
            $group->specTypes()->syncWithoutDetaching($sync);
        }
    }

    /**
     * @param  array<string, SpecGroup>  $groups
     * @param  \Illuminate\Support\Collection<string, SpecType>  $specTypes
     */
    private function seedTemplates(array $groups, $specTypes): void
    {
        $rows = [
            ['group' => 'BJT', 'name' => 'BJT基本', 'items' => ['コレクタ-エミッタ間電圧', 'コレクタ電流', '直流電流増幅率', 'トランジション周波数', '全損失']],
            ['group' => 'MOSFET', 'name' => 'MOSFET基本', 'items' => ['ドレイン-ソース間電圧', 'ドレイン電流', 'オン抵抗', 'ゲートしきい値電圧', 'ゲート電荷', '全損失']],
            ['group' => 'ダイオード/LED', 'name' => 'ダイオード基本', 'items' => ['ピーク耐圧', '平均順電流', '順方向電圧', '逆回復時間']],
            ['group' => 'ダイオード/LED', 'name' => 'LED基本', 'items' => ['順方向電圧', '順方向電流', '発光波長', '光度', '指向角']],
            ['group' => '電源IC/レギュレータ', 'name' => 'LDO基本', 'items' => ['入力電圧', '出力電圧', '出力電流', 'ドロップアウト電圧', '消費電流']],
            ['group' => 'OPアンプ/コンパレータ', 'name' => 'OPアンプ基本', 'items' => ['回路数', '電源電圧', '入力オフセット電圧', '入力バイアス電流', '利得帯域幅積', 'スルーレート']],
            ['group' => 'ロジックIC', 'name' => 'ロジックIC基本', 'items' => ['電源電圧', '入力数', '出力数', '伝播遅延時間', '出力電流']],
            ['group' => 'マイコン', 'name' => 'マイコン基本', 'items' => ['電源電圧', 'フラッシュ容量', 'RAM容量', 'GPIO数', 'ADCチャンネル数', 'クロック周波数']],
            ['group' => 'センサ', 'name' => 'センサ基本', 'items' => ['電源電圧', '消費電流', '測定温度', '分解能']],
            ['group' => '発振子', 'name' => '発振子基本', 'items' => ['クロック周波数', '負荷容量', '周波数許容差', '温度周波数特性']],
        ];

        foreach ($rows as $templateIndex => $row) {
            $group = $groups[$row['group']] ?? null;
            if (!$group) {
                continue;
            }

            $template = $this->updateOrCreateWithRestore(SpecTemplate::class, ['name' => $row['name']], [
                'spec_group_id' => $group->id,
                'name' => $row['name'],
                'description' => "{$row['name']}の初期スペック行",
                'sort_order' => ($templateIndex + 1) * 10,
            ]);

            foreach ($row['items'] as $index => $specName) {
                $specType = $specTypes->get($specName);
                if (!$specType) {
                    continue;
                }
                SpecTemplateItem::query()->updateOrCreate(
                    ['spec_template_id' => $template->id, 'spec_type_id' => $specType->id],
                    [
                        'sort_order' => ($index + 1) * 10,
                        'default_profile' => 'typ',
                        'default_unit' => $specType->base_unit,
                        'is_required' => $index < 2,
                        'note' => null,
                    ]
                );
            }
        }
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
