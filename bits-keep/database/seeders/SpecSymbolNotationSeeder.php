<?php

namespace Database\Seeders;

use App\Models\SpecType;
use App\Models\SpecTypeAlias;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SpecSymbolNotationSeeder extends Seeder
{
    /**
     * @var array<string, array{symbol: string, previous: array<int, string>}>
     */
    public const SYMBOLS = [
        '耐圧' => ['symbol' => 'V_rated', 'previous' => ['Vrated']],
        '入力電圧' => ['symbol' => 'V_IN', 'previous' => ['VIN']],
        '出力電圧' => ['symbol' => 'V_OUT', 'previous' => ['VOUT']],
        'IO電圧' => ['symbol' => 'V_IO', 'previous' => ['VIO']],
        '入力電流' => ['symbol' => 'I_IN', 'previous' => ['IIN']],
        '出力電流' => ['symbol' => 'I_OUT', 'previous' => ['IOUT']],
        '消費電流' => ['symbol' => 'I_CC-/I_q', 'previous' => ['ICC/Iq']],
        '全損失' => ['symbol' => 'P_d', 'previous' => ['Pd', '-P_d']],
        '動作温度' => ['symbol' => 'T_opr', 'previous' => ['Topr', '-T_opr']],
        '保存温度' => ['symbol' => 'T_stg', 'previous' => ['Tstg', '-T_stg']],
        'ジャンクション温度' => ['symbol' => 'T_j', 'previous' => ['Tj']],
        'コレクタ-エミッタ間電圧' => ['symbol' => 'V_CEO', 'previous' => ['VCEO', '-V_CEO']],
        'コレクタ-ベース間電圧' => ['symbol' => 'V_CBO', 'previous' => ['VCBO', '-V_CBO']],
        'エミッタ-ベース間電圧' => ['symbol' => 'V_EBO', 'previous' => ['VEBO', '-V_EBO']],
        'コレクタ電流' => ['symbol' => 'I_C', 'previous' => ['IC', '-I_C']],
        'ベース電流' => ['symbol' => 'I_B', 'previous' => ['Ibase', 'IB']],
        '直流電流増幅率' => ['symbol' => 'h_FE', 'previous' => ['hFE', '-h_FE']],
        'コレクタ-エミッタ飽和電圧' => ['symbol' => 'V_CE-(sat)', 'previous' => ['VCE(sat)', '-V_CE(sat)', 'V_CE(sat)']],
        'ベース-エミッタ飽和電圧' => ['symbol' => 'V_BE-(sat)', 'previous' => ['VBE(sat)', 'V_BE(sat)']],
        'トランジション周波数' => ['symbol' => 'f_T', 'previous' => ['fT', '-f_T']],
        'ドレイン-ソース間電圧' => ['symbol' => 'V_DSS', 'previous' => ['VDSS']],
        'ドレイン電流' => ['symbol' => 'I_D', 'previous' => ['ID']],
        'ピークドレイン電流' => ['symbol' => 'I_DM', 'previous' => ['IDM']],
        'ゲート-ソース間電圧' => ['symbol' => 'V_GSS', 'previous' => ['VGSS']],
        'ゲート漏れ電流' => ['symbol' => 'I_GSS', 'previous' => ['IGSS']],
        'オン抵抗' => ['symbol' => 'R_DS-(on)', 'previous' => ['RDS(on)', 'R_DS(on)']],
        'ゲートしきい値電圧' => ['symbol' => 'V_GS-(th)', 'previous' => ['VGS(th)', 'V_GS(th)']],
        'ゲート電荷' => ['symbol' => 'Q_g', 'previous' => ['Qg']],
        'ピーク耐圧' => ['symbol' => 'V_RRM', 'previous' => ['VRRM']],
        'DC耐圧' => ['symbol' => 'V_R', 'previous' => ['VR']],
        '平均順電流' => ['symbol' => 'I_F-(AV)', 'previous' => ['IF(AV)', 'I_F(AV)']],
        'ピーク順電流' => ['symbol' => 'I_FSM', 'previous' => ['IFSM']],
        '順方向電圧' => ['symbol' => 'V_F', 'previous' => ['VF']],
        '順方向電流' => ['symbol' => 'I_F', 'previous' => ['IF']],
        '逆回復時間' => ['symbol' => 't_rr', 'previous' => ['trr']],
        '端子間容量' => ['symbol' => 'C_j', 'previous' => ['Cj']],
        'ツェナー電圧' => ['symbol' => 'V_Z', 'previous' => ['VZ']],
        '発光波長' => ['symbol' => 'λ_p', 'previous' => ['λp']],
        '光度' => ['symbol' => 'I_v', 'previous' => ['Iv']],
        'ドロップアウト電圧' => ['symbol' => 'V_drop', 'previous' => ['Vdrop']],
        '基準電圧' => ['symbol' => 'V_ref', 'previous' => ['Vref']],
        '入力オフセット電圧' => ['symbol' => 'V_OS', 'previous' => ['Vos']],
        '入力バイアス電流' => ['symbol' => 'I_BIAS', 'previous' => ['IBIAS']],
        'オープンループゲイン' => ['symbol' => 'A_OL', 'previous' => ['AOL']],
        '伝播遅延時間' => ['symbol' => 't_pd', 'previous' => ['tpd']],
        '最大発振周波数' => ['symbol' => 'f_max', 'previous' => ['fmax']],
        'クロック周波数' => ['symbol' => 'f_clk', 'previous' => ['fclk']],
        '測定温度' => ['symbol' => 'T_meas', 'previous' => ['Tmeas']],
        '測定気圧' => ['symbol' => 'P_meas', 'previous' => ['Pmeas']],
        '負荷容量' => ['symbol' => 'C_L', 'previous' => ['CL']],
        '抵抗値許容差' => ['symbol' => 'R_tol', 'previous' => ['Rtol']],
        '容量許容差' => ['symbol' => 'C_tol', 'previous' => ['Ctol']],
        '容量温度特性' => ['symbol' => 'T_C', 'previous' => ['TC']],
    ];
    /**
     * 目的: Spec Symbol Notationの初期データを登録する。
     * 機能: 既定マスタを冪等に登録し、既存データへ必要な補完を行う。
     * 入力: なし。
     * 出力: なし。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: DBへマスタデータを書き込む。
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (self::SYMBOLS as $name => $definition) {
                $specTypes = SpecType::withTrashed()
                    ->where('name', $name)
                    ->where(function ($query) use ($definition): void {
                        $query->whereNull('symbol')
                            ->orWhere('symbol', $definition['symbol'])
                            ->orWhereIn('symbol', $definition['previous']);
                    })
                    ->get();

                foreach ($specTypes as $specType) {
                    $previousSymbol = $specType->symbol;
                    $specType->forceFill(['symbol' => $definition['symbol']])->save();
                    $this->seedSymbolAliases($specType, array_filter([
                        $definition['symbol'],
                        $previousSymbol,
                        ...$definition['previous'],
                    ]));
                }
            }
        });
    }

    /**
     * 目的: Spec Symbol Notationの初期データを登録する。
     * 機能: 既定マスタを冪等に登録し、既存データへ必要な補完を行う。
     * 入力: $name, $fallback。
     * 出力: ?stringで表される値。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: DBへマスタデータを書き込む。
     */
    public static function symbolFor(string $name, ?string $fallback): ?string
    {
        return self::SYMBOLS[$name]['symbol'] ?? $fallback;
    }

    /**
     * 目的: Spec Symbol Notationの初期データを登録する。
     * 機能: 既定マスタを冪等に登録し、既存データへ必要な補完を行う。
     * 入力: $specType, $aliases。
     * 出力: なし。
     * 動作条件: 呼び出し元が必要な依存オブジェクトと正規化前の入力値を渡すこと。
     * 副作用: DBへマスタデータを書き込む。
     * @param  array<int, string>  $aliases
     */
    private function seedSymbolAliases(SpecType $specType, array $aliases): void
    {
        foreach (array_values(array_unique($aliases)) as $index => $alias) {
            SpecTypeAlias::query()->updateOrCreate(
                ['spec_type_id' => $specType->id, 'alias' => $alias],
                [
                    'locale' => preg_match('/[ぁ-んァ-ン一-龠]/u', $alias) ? 'ja' : 'en',
                    'kind' => $alias === $specType->symbol ? 'symbol' : 'alias',
                    'sort_order' => 900 + ($index + 1) * 10,
                ]
            );
        }
    }
}
