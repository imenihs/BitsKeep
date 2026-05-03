<?php

namespace Tests\Feature\UiApiSurfaceSmoke;

trait ChecksDesignToolsSurface
{
    /**
     * 目的: 設計解析ツールの主要セクションがBlade上に欠落せず出ていることを固定する。
     * 機能: ネットワーク探索、温度変換、シャント、バッテリー、ロジックIC、コネクタなどの表示語を横断確認する。
     * 入力: なし。/tools/design をHTTP GETする。
     * 出力: アサーション結果。
     * 動作条件: 管理者として認証済みで、Bladeが描画できること。
     * 副作用: テストクライアントから画面リクエストを1回発行する。
     */
    public function test_design_tools_page_exposes_network_hub_temperature_logic_ic_and_connector_surfaces(): void
    {
        $response = $this->get('/tools/design')
            ->assertOk()
            ->assertSee('data-page="design-tools"', false)
            ->assertSee('ネットワーク探索', false)
            ->assertSee('分圧', false)
            ->assertSee('可変抵抗', false)
            ->assertSee('EIA-96コード早見表', false)
            ->assertSee('設計目的', false)
            ->assertSee('仕様・前提確認', false)
            ->assertSee('パラメータ入力', false)
            ->assertSee('追加条件', false)
            ->assertSee('結果確認', false)
            ->assertSee('最終判定 / 結果確認', false)
            ->assertSee('次アクション', false)
            ->assertSee('解析メモ名', false)
            ->assertSee('入力へ戻す操作 / 保存済み確認', false)
            ->assertSee('保存済み解析一覧', false)
            ->assertSee('入力へ復元', false)
            ->assertSee('削除する', false)
            ->assertSee('バッテリー稼働の前提', false)
            ->assertSee('R/C探索条件', false)
            ->assertDontSee('正本ツール', false)
            ->assertDontSee('href="'.route('tools.network').'"', false)
            ->assertSee('NTC/PTC温度変換', false)
            ->assertSee('Rthと固定抵抗で作る温度センサ測定回路を確認します。', false)
            ->assertDontSee('通常分圧とVR分圧は上部の「分圧」タブで扱います。', false)
            ->assertSee('温度-電圧 / 感度', false)
            ->assertSee('測定対象範囲と高感度域', false)
            ->assertSee('緑点: 入力中のRth測定点', false)
            ->assertSee('黄点: 感度最大点', false)
            ->assertSee('自己発熱', false)
            ->assertSee('公差起因温度振れ', false)
            ->assertSee('Rs電力定格 (W)', false)
            ->assertSee('片方向: オフセットなし', false)
            ->assertSee('双方向: オフセットあり', false)
            ->assertSee('電流 → Vshunt → Vout → ADCコード', false)
            ->assertSee('シミュレーション電流', false)
            ->assertSee('ADCコード', false)
            ->assertSee('ADC下限余裕', false)
            ->assertSee('ADC上限余裕', false)
            ->assertSee('ADCレンジ使用率', false)
            ->assertSee('最小電流時アンプ出力', false)
            ->assertSee('最大電流時アンプ出力', false)
            ->assertSee('アンプ出力下限余裕', false)
            ->assertSee('アンプ出力上限余裕', false)
            ->assertSee('通常余裕', false)
            ->assertSee('上流換算負荷', false)
            ->assertSee('バッテリー稼働時間', false)
            ->assertSee('電池パック', false)
            ->assertSee('動作条件', false)
            ->assertSee('電圧降下 / 放電時間', false)
            ->assertSee('周期負荷', false)
            ->assertSee('Vref方式', false)
            ->assertSee('Vcc分圧方式', false)
            ->assertSee('+入力(非反転)', false)
            ->assertSee('-入力(反転)', false)
            ->assertSee('Vin上昇時しきい値', false)
            ->assertSee('Vin下降時しきい値', false)
            ->assertSee('R1 Vin側抵抗', false)
            ->assertSee('R1 基準側抵抗', false)
            ->assertSee('チップ抵抗器 EIA-96コード早見表', false)
            ->assertSee('倍率文字', false)
            ->assertSee('01C = 10 kΩ', false)
            ->assertSee('R/S/H別表記対応', false)
            ->assertSee('並び替え', false)
            ->assertSee('初期状態へ戻す', false)
            ->assertDontSee('Vout → 電流', false)
            ->assertDontSee('センサ分圧', false)
            ->assertDontSee('Labels / Formula', false)
            ->assertDontSee('SMD Resistor Marking', false)
            ->assertDontSee('>Index<', false)
            ->assertDontSee('>Load<', false)
            ->assertDontSee('>Supply<', false)
            ->assertDontSee('>Driver<', false)
            ->assertDontSee('mating face</text>', false)
            ->assertDontSee('ADC bits', false)
            ->assertDontSee('ADC Bin', false)
            ->assertDontSee('I min', false)
            ->assertDontSee('I max', false)
            ->assertDontSee('base @', false);

        $html = $response->getContent();
        $bladeMain = file_get_contents(resource_path('views/app/design-tools.blade.php'));
        $bladePartials = implode("\n", array_map(
            static fn (string $path): string => file_get_contents($path),
            glob(resource_path('views/app/design-tools/*.blade.php')) ?: []
        ));
        $blade = $html."\n".$bladeMain."\n".$bladePartials;
        $scriptMain = file_get_contents(resource_path('js/pages/design-tools.js'));
        $scriptModules = implode("\n", array_map(
            static fn (string $path): string => file_get_contents($path),
            glob(resource_path('js/pages/design-tools/*.js')) ?: []
        ));
        $script = $scriptMain."\n".$scriptModules;
        $batteryScript = file_get_contents(resource_path('js/pages/design-tools/batteryRuntime.js'));
        $surface = $html.$blade.$script.$batteryScript;

        $this->assertStringContainsString("label: '受動部品'", $script);
        $this->assertStringContainsString("label: '計測・変換'", $script);
        $this->assertStringContainsString("label: '余裕・信頼性'", $script);
        $this->assertStringContainsString("label: '保護・起動'", $script);
        $this->assertStringContainsString("label: '参照・実装'", $script);
        $this->assertStringContainsString("{ id: 'network-search', group: 'passive', label: 'ネットワーク探索'", $script);
        $this->assertStringContainsString("{ id: 'divider-design', group: 'passive', label: '分圧'", $script);
        $this->assertStringContainsString("{ id: 'variable-resistor', group: 'passive', label: '可変抵抗'", $script);
        $this->assertStringContainsString("{ id: 'eia96', group: 'passive', label: 'EIA-96早見表'", $script);
        $this->assertStringContainsString("import setupBatteryRuntimeTool from './design-tools/batteryRuntime.js';", $script);
        $this->assertStringContainsString("import setupPassiveNetworkTool from './resistance-calc.js';", $script);
        $this->assertStringContainsString("'passive-network': 'network-search'", $script);
        $this->assertStringNotContainsString("label: '受動部品ネットワーク/分圧'", $script);
        $this->assertStringNotContainsString("url: '/tools/network'", $script);
        $this->assertStringNotContainsString('network/divider/VR -> candidates -> margin', $script);
        $this->assertStringContainsString("['spec', '仕様・前提確認']", $script);
        $this->assertStringContainsString("['input', 'パラメータ入力']", $script);
        $this->assertStringContainsString("['result', '結果確認']", $script);
        $this->assertStringContainsString("['next', '次アクション']", $script);
        $this->assertStringContainsString("title: 'ローサイド・シャント電流検出'", $script);
        $this->assertStringContainsString('power_margin_mw: hasPowerRatingValue', $script);
        $this->assertStringContainsString('adc_range_used_pct', $script);
        $this->assertStringContainsString('vout_at_imin_v', $script);
        $this->assertStringContainsString('vout_at_imax_v', $script);
        $this->assertStringContainsString('amp_range_margin_high_v', $script);
        $this->assertStringContainsString('FAIL理由', $surface);
        $this->assertStringContainsString('Rs電力定格超過', $surface.$script);
        $this->assertStringContainsString("senseMode: 'unipolar'", $script);
        $this->assertStringNotContainsString('lossMw > 250', $script);
        $this->assertStringNotContainsString('Vout上側余裕', $surface);
        $this->assertStringNotContainsString('Vout下側余裕', $surface);
        $this->assertStringContainsString('supplyExceeded', $script);
        $this->assertStringContainsString("{ id: 'battery-runtime', group: 'margin', label: 'バッテリー稼働'", $script);
        $this->assertStringContainsString('batteryGraphCursor', $surface);
        $this->assertStringContainsString('batteryGraphTooltipBox', $surface);
        $this->assertStringContainsString('runtimeScaleRows', $batteryScript);
        $this->assertStringContainsString('runtimeScaleText', $batteryScript);
        $this->assertStringContainsString('limitingFactor', $batteryScript);
        $this->assertStringContainsString('maxCurveDepth', $batteryScript);
        $this->assertStringContainsString('voltageV', $batteryScript);
        $this->assertStringContainsString('efficiencyPct', $batteryScript);
        $this->assertStringContainsString('averagePowerW', $batteryScript);
        $this->assertStringContainsString('peakPowerW', $batteryScript);
        $this->assertStringContainsString('graphEndHours', $batteryScript);
        $this->assertStringContainsString('graphEndDepth', $batteryScript);
        $this->assertStringContainsString('batteryCapacityPie', $surface);
        $this->assertStringContainsString('totalSharePctLabel', $batteryScript);
        $this->assertStringNotContainsString('容量100%消費', $surface);
        $this->assertStringContainsString('容量(Ah)', $surface);
        $this->assertStringContainsString('負荷電圧(V)', $surface);
        $this->assertStringContainsString('負荷電流(A)', $surface);
        $this->assertStringContainsString('変換効率(%)', $surface);
        $this->assertStringContainsString('ON時間(s)', $surface);
        $this->assertStringContainsString('Wh/周期', $surface.$script);
        $this->assertStringContainsString('平均電力', $surface.$script);
        $this->assertStringContainsString('平均電流', $surface.$script);
        $this->assertStringContainsString('ピーク負荷', $surface.$script);
        $this->assertStringContainsString('制限要因', $surface.$script);
        $this->assertStringContainsString('容量ベース時間', $surface.$script);
        $this->assertStringContainsString('電圧下限到達時間', $surface.$script);
        $this->assertStringContainsString('実効稼働時間', $surface.$script);
        $this->assertStringContainsString('容量消費内訳', $surface);
        $this->assertStringContainsString('負荷内訳', $surface);
        $this->assertStringContainsString('電池100%の消費配分', $surface);
        $this->assertStringContainsString('ON秒数合計(重複可)', $script);
        $this->assertStringContainsString('summaryLines', $blade);
        $this->assertStringContainsString('standardFullVoltage', $batteryScript);
        $this->assertStringContainsString('standardNominalVoltage', $batteryScript);
        $this->assertStringContainsString('standardCutoffVoltage', $batteryScript);
        $this->assertStringContainsString('満充電電圧', $surface);
        $this->assertStringContainsString('公称電圧', $surface);
        $this->assertStringContainsString('下限電圧', $surface);
        $this->assertStringContainsString('セル数', $surface);
        $this->assertStringContainsString('電圧 (V)', $surface);
        $this->assertStringNotContainsString('容量 (mAh)', $surface);
        $this->assertStringNotContainsString('電流 mA', $surface);
        $this->assertStringNotContainsString('周期負荷からバッテリー稼働時間を見積もる', $surface);
        $this->assertStringNotContainsString('runtime[h] = usable_capacity[mAh] / average_current[mA]', $script);
        $this->assertStringContainsString("referenceMode: 'external'", $script);
        $this->assertStringContainsString("inputPolarity: 'positive'", $script);
        $this->assertStringContainsString('reference_thevenin_ohm', $script);
        $this->assertStringContainsString('feedback_ratio', $script);
        $this->assertStringContainsString('r1_shorted', $script);
        $this->assertStringContainsString('R1 ショート', $surface.$script);
        $this->assertStringContainsString('R1短絡', $blade);
        $this->assertStringNotContainsString('R1 short', $blade);
        $this->assertStringContainsString('P 発熱源', $surface);
        $this->assertStringContainsString('周囲温度 Ta', $surface);
        $this->assertStringContainsString('接合部→周囲', $surface);
        $this->assertStringContainsString('OUT→V+ 正帰還', $blade);
        $this->assertStringContainsString('x1="506" y1="130" x2="506" y2="42"', $blade);
        $this->assertStringNotContainsString('C 510 42', $blade);
        $this->assertStringNotContainsString('x1="156" y1="218" x2="288" y2="152"', $blade);
        $this->assertStringContainsString('bitskeep.designTools.toolOrder.v1', $script);
        $this->assertStringContainsString('bitskeep.designTools.lastTool.v1', $script);
        $this->assertStringContainsString('bitskeep.designTools.toolGroup.v1', $script);
        $this->assertStringContainsString('const toolGroups = [', $script);
        $this->assertStringContainsString('const explicitToolId = queryToolId || datasetToolId || null;', $script);
        $this->assertStringContainsString('const toolGroupForToolId = (toolId)', $script);
        $this->assertStringContainsString('const activeToolGroup = ref(explicitToolId', $script);
        $this->assertStringContainsString('? toolGroupForToolId(activeToolId.value)', $script);
        $this->assertStringContainsString('ensureActiveToolMatchesGroup', $script);
        $this->assertStringContainsString('visibleTools', $script);
        $this->assertStringContainsString('activeToolInVisibleGroup', $script);
        $this->assertStringNotContainsString('v-if="false && analysisReport"', $blade);
        $this->assertDoesNotMatchRegularExpression('/false\s*&&\s*analysisReport/u', $blade);
        $this->assertStringNotContainsString('Labels / Formula', $blade);
        $this->assertStringNotContainsString('SMD Resistor Marking', $blade);
        $this->assertStringNotContainsString('<th class="px-3 py-2">Index</th>', $blade);
        foreach ([
            'Vin min (V)',
            'Vin typ (V)',
            'Vin max (V)',
            'UART nominal baud',
            'UART actual baud',
            'レールツリー name,parent,V,A',
            'mating face/solder side',
            'BOM note',
            'ADC Bin',
            'I min',
            'I max',
            'base @',
        ] as $legacyLabel) {
            $this->assertStringNotContainsString($legacyLabel, $surface);
        }
        $this->assertStringContainsString('eia96BaseValues', $script);
        $this->assertStringContainsString('eia96Multipliers', $script);
        $this->assertStringContainsString("if (activeToolId.value === 'eia96')", $script);
        $this->assertStringContainsString('R/S/H別表記対応', $blade);
        $this->assertStringNotContainsString('data-tool="adc"', $blade);
        $stageSource = $html;
        $specStage = strpos($stageSource, 'data-review-stage="spec"');
        $firstToolInputStage = strpos($stageSource, 'data-review-stage="tool-input"');
        $lastToolInputStage = strrpos($stageSource, 'data-review-stage="tool-input"');
        $quickInputStage = strpos($stageSource, 'data-review-stage="quick-input"');
        $connectorOutputStage = strpos($stageSource, 'data-review-stage="connector-output"');
        $resultStage = strrpos($stageSource, 'data-review-stage="result"');
        foreach ([
            'spec' => $specStage,
            'tool-input' => $firstToolInputStage,
            'quick-input' => $quickInputStage,
            'connector-output' => $connectorOutputStage,
            'result' => $resultStage,
        ] as $stage => $position) {
            $this->assertNotFalse($position, "Design tool review stage {$stage} is missing.");
        }
        $this->assertStringNotContainsString('data-review-stage="input-detail"', $blade);
        $this->assertLessThan($firstToolInputStage, $specStage, 'Circuit/spec context must appear before tool-specific inputs/results.');
        $this->assertLessThan($connectorOutputStage, $quickInputStage, 'Quick tool input must appear before connector pin-map output.');
        $this->assertGreaterThan($lastToolInputStage, $resultStage, 'Final judgment and save actions must appear after every tool-specific input surface.');
        $this->assertGreaterThan($connectorOutputStage, $resultStage, 'Final judgment and save actions must appear after connector output/review.');
        $this->assertStringContainsString("{ id: 'logic-ic', group: 'reference', label: 'ロジックIC参照'", $script);
        $this->assertStringContainsString("if (activeToolId.value === 'logic-ic')", $script);
        $this->assertStringContainsString("model: 'logic-ic'", $script);
        $this->assertStringNotContainsString('v-model="divider.mode"', $blade);
        $this->assertDoesNotMatchRegularExpression("/id:\\s*'divider'\\s*,\\s*label:\\s*'分圧'/u", $script);
        $this->assertMatchesRegularExpression(
            "/<\\/div>\\s*<section v-if=\"activeToolId === 'connector'\"/u",
            $blade,
            'Connector pin-map and registration surface must be a top-level section, not nested under the ADC-only block.'
        );

        foreach ([
            'ロジックIC参照',
            '候補ファミリ',
            '機能',
            '入力数の目安',
            'ピン数(0=不問)',
            '使用Vcc(V)',
            '出力形式',
            '候補一覧',
            '伝搬遅延/最大周波数',
            'パッケージピン配置',
        ] as $label) {
            $this->assertStringContainsString($label, $surface);
        }

        foreach ([
            'IF余裕',
            '送信側シリーズ',
            '受信側シリーズ',
            'シリーズ接続',
            'シリーズH/L余裕',
            '入力耐圧余裕',
            '系列しきい値',
        ] as $label) {
            $this->assertStringContainsString($label, $surface.$script);
        }

        $this->assertStringContainsString('コネクタ設計/ピン配置', $surface);
        $this->assertTrue(
            str_contains($surface, 'USB Type-C') || str_contains($surface, '標準/ユーザーコネクタ'),
            'Design tools must expose a standard connector template label such as USB Type-C or 標準/ユーザーコネクタ.'
        );
        foreach ([
            'ピン割付',
            '写真URL',
            'ピン配置図URL',
            'データシートURL',
            'BOM注記',
            'シルク/組立注記',
            'ユーザーコネクタを登録',
            '登録写真URL',
            '登録ピン配置図URL',
            '登録データシートURL',
        ] as $label) {
            $this->assertStringContainsString($label, $surface);
        }
    }
}
