<?php

namespace Database\Seeders;

use App\Models\SpecGroup;
use App\Models\SpecTemplate;
use App\Models\SpecTemplateItem;
use App\Models\SpecType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SpecGroupTemplateSeeder extends Seeder
{
    /**
     * 目的: Spec Group Templateの初期データを登録する。
     * 機能: 既定マスタを冪等に登録し、既存データへ必要な補完を行う。
     * 入力: なし。
     * 出力: なし。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: DBへマスタデータを書き込む。
     */
    public function run(): void
    {
        DB::transaction(function () {
            $specTypes = SpecType::query()->get()->keyBy('name');
            $groups = $this->seedGroups();
            $this->seedGroupMembers($groups, $specTypes);
            $this->seedCommonSpecTypes();
            $this->seedTemplates($groups, $specTypes);
            $this->removeLegacyCommonGroup();
        });
    }

    /**
     * 目的: Spec Group Templateの初期データを登録する。
     * 機能: 既定マスタを冪等に登録し、既存データへ必要な補完を行う。
     * 入力: なし。
     * 出力: arrayで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: DBへマスタデータを書き込む。
     * @return array<string, SpecGroup>
     */
    private function seedGroups(): array
    {
        $rows = [
            ['name' => 'BJT', 'description' => 'バイポーラトランジスタの代表スペック', 'sort_order' => 20],
            ['name' => 'MOSFET', 'description' => 'MOSFETの代表スペック', 'sort_order' => 30],
            ['name' => 'ダイオード/LED', 'description' => 'ダイオード、LED、光半導体の代表スペック', 'sort_order' => 40],
            ['name' => '電源IC/レギュレータ', 'description' => 'DCDC、LDO、三端子レギュレータの代表スペック', 'sort_order' => 50],
            ['name' => 'OPアンプ/コンパレータ', 'description' => 'OPアンプ、コンパレータの代表スペック', 'sort_order' => 60],
            ['name' => 'ロジックIC', 'description' => 'ロジックIC、レベル変換、タイミング系ICの代表スペック', 'sort_order' => 70],
            ['name' => 'マイコン', 'description' => 'MCU、周辺IC、開発ボードの代表スペック', 'sort_order' => 80],
            ['name' => 'センサ', 'description' => '環境センサ、物理量センサの代表スペック', 'sort_order' => 90],
            ['name' => '発振子', 'description' => '水晶発振子、セラロック、オシレータの代表スペック', 'sort_order' => 100],
        ];

        $groups = [];
        foreach ($rows as $row) {
            $group = $this->updateOrCreateWithRestore(SpecGroup::class, ['name' => $row['name']], $row);
            $groups[$group->name] = $group;
        }

        return $groups;
    }

    /**
     * 目的: Spec Group Templateの初期データを登録する。
     * 機能: 既定マスタを冪等に登録し、既存データへ必要な補完を行う。
     * 入力: $groups, $specTypes。
     * 出力: なし。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: DBへマスタデータを書き込む。
     * @param  array<string, SpecGroup>  $groups
     * @param  \Illuminate\Support\Collection<string, SpecType>  $specTypes
     */
    private function seedGroupMembers(array $groups, $specTypes): void
    {
        $rows = [
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
                if ($specType->spec_scope !== SpecType::SCOPE_COMMON && !$specType->owner_spec_group_id) {
                    $specType->forceFill([
                        'spec_scope' => SpecType::SCOPE_GROUP_LOCAL,
                        'owner_spec_group_id' => $group->id,
                    ])->save();
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
     * 目的: Spec Group Templateの初期データを登録する。
     * 機能: 既定マスタを冪等に登録し、既存データへ必要な補完を行う。
     * 入力: なし。
     * 出力: なし。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: DBへマスタデータを書き込む。
     */
    private function seedCommonSpecTypes(): void
    {
        SpecType::query()
            ->whereIn('name', ['動作温度', '保存温度', '端子数', '端子ピッチ'])
            ->update([
                'spec_scope' => SpecType::SCOPE_COMMON,
                'owner_spec_group_id' => null,
            ]);
    }

    /**
     * 目的: Spec Group Templateの初期データを登録する。
     * 機能: 既定マスタを冪等に登録し、既存データへ必要な補完を行う。
     * 入力: $groups, $specTypes。
     * 出力: なし。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: DBへマスタデータを書き込む。
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
     * 目的: Spec Group Templateの初期データを登録する。
     * 機能: 既定マスタを冪等に登録し、既存データへ必要な補完を行う。
     * 入力: $modelClass, $lookup, $values。
     * 出力: Modelで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: DBへマスタデータを書き込む。
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
    /**
     * 目的: Spec Group Templateの初期データを登録する。
     * 機能: 既定マスタを冪等に登録し、既存データへ必要な補完を行う。
     * 入力: なし。
     * 出力: なし。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: DBへマスタデータを書き込む。
     */
    private function removeLegacyCommonGroup(): void
    {
        $commonGroupIds = DB::table('spec_groups')
            ->where('name', '共通')
            ->pluck('id');

        foreach ($commonGroupIds as $groupId) {
            DB::table('spec_templates')->where('spec_group_id', $groupId)->update(['spec_group_id' => null]);
            DB::table('spec_group_spec_type')->where('spec_group_id', $groupId)->delete();
            DB::table('spec_groups')->where('id', $groupId)->delete();
        }
    }
}
