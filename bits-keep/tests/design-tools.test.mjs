import assert from 'node:assert/strict';
import {
    assertClose,
    assertSurfaceTokens as assertSurfaceTokensIn,
    assertTextOrder,
    fetchCalls,
    installDesignToolBrowserStubs,
    loadDesignToolSources,
    localStore,
    metricValue,
} from './design-tools-fixtures.mjs';

installDesignToolBrowserStubs();

const { default: setupDesignTools } = await import('../resources/js/pages/design-tools.js');

const state = setupDesignTools();
const {
    designToolsBlade,
    designToolsScript,
    batteryUiBlade,
    designToolSurfaceText,
} = loadDesignToolSources();
/**
 * UIまたはスクリプト表面に必須語が露出していることを検証する。
 * 入力は検証名とトークン配列で、失敗時はassert例外を投げる。
 */
// 目的: 設計解析ツールのassert Surface Tokensを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: 失敗時にassert例外を投げる場合がある。
const assertSurfaceTokens = (label, tokens) => {
    assertSurfaceTokensIn(designToolSurfaceText, label, tokens);
};
const requiredTools = [
    'network-search',
    'divider-design',
    'variable-resistor',
    'eia96',
    'adc',
    'cap-life',
    'divider',
    'shunt',
    'power',
    'battery-runtime',
    'comparator',
    'thermal',
    'interface',
    'tolerance',
    'bode',
    'ovp',
    'tvs',
    'fuse',
    'polyfuse',
    'protection',
    'logic-ic',
    'connector',
    'cable',
    'jumper',
    'startup',
];
const verdicts = new Set(['PASS', 'WARN', 'FAIL', 'CHECK']);
const toolIds = state.tools.map((tool) => tool.id);

assert.equal(state.activeToolId.value, 'network-search', 'design tools should open on the network-search tab by default');
assert.equal(state.activeToolGroup.value, 'passive', 'design tools should start from the passive purpose group instead of showing every tab at once');
assert.deepEqual(
    state.toolGroups.map((group) => group.id),
    ['all', 'passive', 'measure', 'margin', 'protection', 'reference'],
    'design tool hub should expose purpose filters before the tool tabs'
);
assert.ok(state.tools.every((tool) => state.toolGroups.some((group) => group.id === tool.group)), 'every design tool should belong to a purpose group');
state.setToolGroup('passive');
assert.equal(state.activeToolGroup.value, 'passive', 'purpose filter should update active group');
assert.equal(localStore.get('bitskeep.designTools.toolGroup.v1'), 'passive', 'purpose filter should be saved to localStorage');
assert.deepEqual(
    state.visibleTools.value.map((tool) => tool.id),
    ['network-search', 'divider-design', 'variable-resistor', 'eia96'],
    'passive filter should show only passive design tools'
);
assert.equal(state.selectedToolGroup.value.label, '受動部品', 'selected purpose group should expose the Japanese label');
state.activeToolId.value = 'power';
assert.equal(state.activeToolInVisibleGroup.value, false, 'current tool should be flagged when hidden by the active purpose filter');
state.setToolGroup('margin');
assert.equal(state.activeToolId.value, 'power', 'switching to the active tool purpose should keep the current tool visible');
state.setToolGroup('measure');
assert.equal(state.activeToolId.value, 'adc', 'switching purpose should move the active tool to the first tool in that purpose when needed');
state.setToolGroup('all');
assert.equal(state.activeToolInVisibleGroup.value, true, 'all filter should show the active tool again');
state.moveToolTab('power', -1);
assert.ok(JSON.parse(localStore.get('bitskeep.designTools.toolOrder.v1')).includes('power'), 'tab order should be saved to localStorage');
state.setToolGroup('margin');
state.resetToolOrder();
assert.equal(localStore.has('bitskeep.designTools.toolOrder.v1'), false, 'reset should clear saved tab order');
assert.equal(localStore.has('bitskeep.designTools.lastTool.v1'), false, 'reset should clear the last active tool');
assert.equal(localStore.has('bitskeep.designTools.toolGroup.v1'), false, 'reset should clear the saved purpose filter');
assert.equal(state.tools[0].id, 'network-search', 'reset should restore the default first tab');
assert.equal(state.activeToolGroup.value, 'passive', 'reset should restore the passive purpose filter');
localStore.set('bitskeep.designTools.lastTool.v1', 'shunt');
const restoredState = setupDesignTools();
assert.equal(restoredState.activeToolId.value, 'shunt', 'saved last tool should be restored when no explicit tool is provided');
assert.equal(restoredState.activeToolGroup.value, 'measure', 'saved last tool should restore a matching purpose group when no group is saved');
localStore.set('bitskeep.designTools.toolGroup.v1', 'passive');
const purposeState = setupDesignTools();
assert.equal(purposeState.activeToolId.value, 'network-search', 'saved purpose group should select a visible tool when the saved last tool is outside the group');
assert.equal(purposeState.activeToolGroup.value, 'passive');
localStore.delete('bitskeep.designTools.toolGroup.v1');
localStore.set('bitskeep.designTools.toolGroup.v1', 'passive');
global.location.search = '?tool=power';
const explicitToolState = setupDesignTools();
assert.equal(explicitToolState.activeToolId.value, 'power', 'explicit tool query should override a saved purpose filter');
assert.equal(explicitToolState.activeToolGroup.value, 'margin', 'explicit tool query should restore the purpose matching the requested tool');
global.location.search = '?tool=battery-runtime%20';
const batteryToolAliasState = setupDesignTools();
assert.equal(batteryToolAliasState.activeToolId.value, 'battery-runtime', 'tool query with trailing spaces should normalize to battery-runtime');
assert.equal(batteryToolAliasState.activeToolGroup.value, 'margin', 'normalized battery runtime query should restore margin purpose');
global.location.search = '?tool=battery_runtime';
const batteryToolLegacyState = setupDesignTools();
assert.equal(batteryToolLegacyState.activeToolId.value, 'battery-runtime', 'tool query with underscore should normalize to battery-runtime');
assert.equal(batteryToolLegacyState.activeToolGroup.value, 'margin', 'legacy battery tool alias should restore margin purpose');
global.location.search = '?tool=batteryruntime';
const batteryToolCompactState = setupDesignTools();
assert.equal(batteryToolCompactState.activeToolId.value, 'battery-runtime', 'compact battery tool alias should normalize to battery-runtime');
assert.equal(batteryToolCompactState.activeToolGroup.value, 'margin', 'compact alias should restore margin purpose');
global.location.search = '';
localStore.clear();
localStore.set('bitskeep.designTools.toolOrder.v1', JSON.stringify(['power', 'power', 'unknown-tool', 'passive-network']));
const orderedState = setupDesignTools();
assert.deepEqual(orderedState.tools.slice(0, 2).map((tool) => tool.id), ['power', 'network-search'], 'saved tab order should dedupe, normalize legacy ids, and keep valid tools');
localStore.clear();

for (const toolId of requiredTools) {
    assert.ok(toolIds.includes(toolId), `${toolId} is missing from design tool tabs`);
}

assert.equal(state.numericInputValue(state.adc, 'vinMin'), '100m', 'sample ADC voltage should keep prefix-only notation without a unit suffix');
assert.equal(state.numericInputValue(state.shunt, 'Rs'), '10m', 'sample shunt resistance should keep prefix-only notation without an ohm suffix');
assert.equal(state.numericInputValue(state.power.loads[0], 'mA'), '50m', 'sample power load current should be expressed in A with prefix-only notation');
assert.equal(state.numericInputValue(state.battery, 'capacityMah'), '1000m', 'sample battery capacity should be expressed in Ah with prefix-only notation');
assert.equal(state.numericInputValue(state.battery.loads[0], 'currentMa'), '1m', 'sample LED battery load current should be expressed as 1mA in A notation');
assert.equal(state.numericInputValue(state.battery.loads[1], 'currentMa'), '100u', 'sample battery load current should be expressed in A with prefix-only notation');
assert.equal(state.numericInputValue(state.comp, 'inputBiasNa'), '50n', 'sample comparator bias current should be expressed in A with prefix-only notation');
assert.equal(state.numericInputValue(state.iface, 'i2cBusCapPf'), '200p', 'sample I2C bus capacitance should be expressed in F with prefix-only notation');
assert.equal(state.numericInputValue(state.quickForms.tvs, 'pulseMs'), '1m', 'sample TVS pulse width should be expressed in seconds with prefix-only notation');
assert.equal(state.numericInputValue(state.quickForms.startup, 'resetHoldMs'), '20m', 'sample startup reset hold should be expressed in seconds with prefix-only notation');

state.activeToolId.value = 'battery-runtime';
state.outputSave.project = { id: 7, name: '案件A' };
state.selectComponentCandidate({ id: 11, part_number: 'LED-001', common_name: 'LED', manufacturer: 'RWC' });
state.outputSave.bomLineKey = 'BOM-1';
assert.equal(state.analysisPayload.value.project_id, 7, 'analysis save payload should derive project_id from the selected project');
assert.equal(state.analysisPayload.value.component_id, 11, 'analysis save payload should derive component_id from the selected component');
assert.ok(Array.isArray(state.analysisPayload.value.input_payload.loads), 'battery-runtime payload should be saved with load rows');
await state.loadSavedAnalysis();
assert.equal(state.savedAnalysis.sessions.length, 1, 'saved analysis list should expose all sessions returned by the API');
assert.equal(fetchCalls.at(-1).url, '/api/analysis-sessions?tool_id=battery-runtime&project_id=7&component_id=11&bom_line_key=BOM-1', 'saved analysis list should filter by tool, selected project, selected component, and BOM line');
state.restoreSavedAnalysis(state.savedAnalysis.sessions[0]);
assert.equal(state.activeToolId.value, 'battery-runtime', 'restoring a saved battery runtime session should activate the same tool');
assert.equal(state.battery.capacityMah, 2000, 'restoring a saved battery runtime session should restore battery pack inputs');
assert.equal(state.battery.loads[0].name, '復元LED', 'restoring a saved battery runtime session should restore load rows');
assert.equal(state.outputSave.projectId, '7', 'restoring a saved session should keep the saved project context');
assert.equal(state.outputSave.componentId, '11', 'restoring a saved session should keep the saved component context');
await state.saveAnalysisReport();
const lastPost = fetchCalls.findLast((call) => call.url === '/api/analysis-sessions' && call.method === 'POST');
assert.equal(JSON.parse(lastPost.options.body).tool_id, 'battery-runtime', 'saving should post the active design tool id');
state.beginDeleteSavedAnalysis(state.savedAnalysis.sessions.find((session) => session.id === 101));
await state.deleteSavedAnalysis();
assert.ok(!state.savedAnalysis.sessions.some((session) => session.id === 101), 'delete should remove the selected saved session from the local list');
assertTextOrder(
    batteryUiBlade,
    ['電池パック', '動作条件', '周期負荷', '結果', 'グラフ'],
    'battery runtime UI should follow the requested block order'
);
assert.doesNotMatch(
    batteryUiBlade,
    /lg:grid-cols-2/,
    'battery runtime UI should not split inputs and results into desktop columns that change the visual reading order'
);
assertTextOrder(
    batteryUiBlade,
    ['種類', 'セル数', '容量(Ah)', '公称電圧(V)', '内部抵抗(Ω)', '使用可能容量(%)'],
    'battery pack fields should stay grouped together'
);
assertTextOrder(
    batteryUiBlade,
    ['周期時間(s)', 'システム最低電圧(V)', '要求稼働時間(h)'],
    'battery operating condition fields should stay grouped together'
);
assertTextOrder(
    batteryUiBlade,
    ['名称', '負荷電圧(V)', '負荷電流(A)', '変換効率(%)', 'ON時間(s)'],
    'battery load columns should stay as one horizontal set'
);
assertTextOrder(
    batteryUiBlade,
    ['実効稼働時間', '制限要因', '容量ベース時間', '電圧下限到達時間', 'Wh/周期', '平均電力', '平均電流', 'ピーク負荷'],
    'battery result cards should keep the decision result before supporting values'
);
assert.doesNotMatch(
    batteryUiBlade,
    /容量100%消費/,
    'battery runtime UI should not show the confusing 100% capacity consumption time as a normal result'
);

for (const toolId of requiredTools) {
    state.activeToolId.value = toolId;
    const diagram = state.activeDiagram.value;
    const report = state.analysisReport.value;
    const workflow = state.workflow.value;

    if (toolId === 'battery-runtime') {
        assert.equal(diagram, null, 'battery runtime should not show a fake circuit/context diagram');
    } else {
        assert.ok(diagram, `${toolId} should expose a circuit/context diagram`);
        assert.ok(Array.isArray(diagram.parts) && diagram.parts.length > 0, `${toolId} diagram should expose labeled parts`);
    }
    assert.ok(report, `${toolId} should produce an analysis report`);
    assert.ok(verdicts.has(report.verdict), `${toolId} verdict should be normalized`);
    assert.notEqual(report.summary, 'このツールの判定モデルが未定義です。', `${toolId} should not fall back to the undefined model report`);
    assert.ok(report.metrics.length > 0, `${toolId} should expose metrics`);
    assert.ok(report.nextActions.length > 0, `${toolId} should expose next actions`);
    assert.deepEqual(
        workflow.map((step) => step.label),
        ['仕様・前提確認', 'パラメータ入力', '結果確認', '次アクション'],
        `${toolId} should expose the common review workflow`
    );
    assert.ok(Array.isArray(report.dominantFactors), `${toolId} should expose dominantFactors as an array`);
    assert.ok(Array.isArray(report.warnings), `${toolId} should expose warnings as an array`);
    assert.ok(Array.isArray(report.summaryLines), `${toolId} should expose summaryLines as an array`);
    assert.ok(Array.isArray(report.missingConditions), `${toolId} should expose missingConditions as an array`);
    assert.ok(Array.isArray(report.assumptions), `${toolId} should expose assumptions as an array`);
    assert.ok(Array.isArray(report.candidateLinks), `${toolId} should expose candidateLinks as an array`);
    assert.ok(report.copySummary && !report.copySummary.includes('undefined'), `${toolId} should expose a clean copy summary`);
}

assertSurfaceTokens('受動部品', ['目標値', 'E系列', '在庫値', '許容誤差', '温度係数', '抵抗電力', '実装条件']);
assertSurfaceTokens('ADC', ['min/typ/max入力', 'Vref', 'オフセット', '入力源インピーダンス', 'サンプル時間', '固定小数点係数']);
assertSurfaceTokens('EIA-96', ['3文字マーキング', '0Ω', '3桁/4桁', 'BOM値', 'サイズ', 'メーカー']);
assertSurfaceTokens('NTC/PTC', ['測定対象範囲', '固定抵抗候補', '自己発熱', 'ADC量子化', 'min/typ/max表']);
assertSurfaceTokens('電流検出', ['片方向', '双方向', 'Rs電力定格', 'ケルビン接続', 'ADC量子化幅', 'CMRR', '帯域']);
assertSurfaceTokens('電源', ['レールツリー', '上流換算負荷', 'ドロップアウト', '突入電流の時間幅', '最大負荷シナリオ', '支配負荷']);
assertSurfaceTokens('バッテリー', ['電池パック', '動作条件', '周期負荷', '実効稼働時間', '電圧下限到達時間', '劣化', '効率']);
assertSurfaceTokens('比較器', ['入力極性', '基準方式', 'R1/R3比', 'VOH/VOL', '入力バイアス', 'ノイズ余裕']);
assertSurfaceTokens('熱', ['発熱源', '周囲温度', '通常Tj', '最悪Tj', 'ディレーティング', '支配熱抵抗', '放熱候補']);
assertSurfaceTokens('IF/ロジック/コネクタ/保護/起動', ['レベル余裕', '候補IC', 'Pin1', '定格', '故障波形', '動作順序', 'PG/RESET', 'バックパワー']);

for (const toolId of ['ovp', 'tvs', 'fuse', 'polyfuse']) {
    state.activeToolId.value = toolId;
    const report = state.analysisReport.value;

    assert.equal(report.verdict, 'CHECK', `${toolId} should stay CHECK while component ratings are blank`);
    assert.ok(report.missingConditions.length > 0, `${toolId} should list missing rating/curve conditions`);
}

state.activeToolId.value = 'power';
assert.equal(state.analysisReport.value.verdict, 'CHECK', 'power tool should not PASS while inrush pulse conditions are unresolved');
assert.ok(state.analysisReport.value.missingConditions.includes('突入電流の時間幅'));
assertClose(Number(state.powerResult.value.totalW), 0.231, 0.0005);
assertClose(Number(state.powerResult.value.inputEquivalentW), 0.272, 0.001);
assertClose(Number(state.powerResult.value.margin), 9.769, 0.0005);
assertClose(Number(state.powerResult.value.maxScenarioW), 0.347, 0.001);
assert.equal(state.powerResult.value.supplyExceeded, false, '10W supply with 0.231W load should not be marked as supply exceeded');
assert.equal(state.powerResult.value.capacityExceeded, false, 'unresolved inrush should not be shown as a capacity overrun');
assert.ok(
    state.powerResult.value.railMargins.some((rail) => rail.name === '3V3' && rail.dropoutMargin > 0 && rail.marginW > 1),
    'power tool should calculate rail dropout and per-rail margin'
);
assert.ok(
    metricValue(state.analysisReport.value, '最大負荷').includes('MCU'),
    'power report should expose the dominant load'
);
state.power.rails = 'VIN,,12,2\n3V3,VIN,3.3,0.05';
assert.equal(state.powerResult.value.railMargins.find((rail) => rail.name === '3V3')?.overloaded, true, 'rail capacity below assigned load should overload the rail');
assert.equal(state.analysisReport.value.verdict, 'FAIL', 'rail overload should fail even when total supply is sufficient');
state.power.rails = 'VIN,,12,2\n3V3,VIN,3.3,0.4\n1V8,3V3,1.8,0.2';
state.power.maxLoadFactor = 50;
assert.equal(state.powerResult.value.supplyExceeded, true, 'maximum load scenario above supply should be marked as supply exceeded');
assert.equal(state.analysisReport.value.verdict, 'FAIL', 'maximum load scenario above supply should fail');
state.power.maxLoadFactor = 1.5;
state.power.supply_w = 0.25;
assert.equal(state.powerResult.value.supplyExceeded, true, 'upstream efficiency-converted load above supply should be marked as supply exceeded');
assert.equal(state.analysisReport.value.verdict, 'FAIL', 'upstream efficiency-converted load above supply should fail');
state.power.supply_w = 0.2;
assert.equal(state.powerResult.value.supplyExceeded, true, 'normal load above supply should be marked as supply exceeded');
assert.equal(state.analysisReport.value.verdict, 'FAIL', 'supply overrun should fail');
assert.match(state.analysisReport.value.summary, /供給|負荷|レール|突入/u, 'power failure summary should name the failing power condition');
assert.doesNotMatch(state.analysisReport.value.summary, /どれか|いずれか/u, 'power failure summary should not use vague wording');
assert.ok(state.analysisReport.value.summaryLines.length > 1, 'multiple power issues should be exposed as separate summary lines');
state.power.supply_w = 10;

state.activeToolId.value = 'battery-runtime';
state.battery.type = 'lipo';
state.battery.cellCount = 1;
state.battery.capacityMah = 1000;
state.battery.usablePct = 100;
state.battery.nominalVoltage = 3.7;
state.battery.cycleSec = 60;
state.battery.systemMinVoltage = 3.0;
state.battery.loads = [
    { name: 'LED', currentMa: 1, durationSec: 1 },
    { name: 'マイコン', currentMa: 0.1, durationSec: 10 },
    { name: 'GPS', currentMa: 30, durationSec: 30 },
];
assertClose(Number(state.batteryResult.value.averageCurrentMa), 15.0333, 0.0001);
assertClose(Number(state.batteryResult.value.mahPerCycle), 0.250556, 0.000001);
assertClose(Number(state.batteryResult.value.whPerCycle), 0.000927, 0.000001);
assertClose(Number(state.batteryResult.value.averagePowerW), 0.055623, 0.000001);
assert.equal(state.batteryResult.value.peakLoadName, '全負荷同時', 'battery result should use simultaneous active loads for internal resistance drop');
assertClose(Number(state.batteryResult.value.peakPowerW), 0.11507, 0.000001);
assertClose(Number(state.batteryResult.value.runtimeHours), 66.47, 0.02);
assert.deepEqual(
    state.batteryResult.value.runtimeScaleRows.map((row) => row.label),
    ['時間', '日'],
    'runtime above 24h should add a day-scale display while keeping hours'
);
assert.ok(state.batteryResult.value.runtimeScaleText.includes('日'), 'runtime scale text should include day scale above 24h');
assert.equal(state.batteryResult.value.cellCount, 1, 'LiPo should use the entered cell count');
assert.equal(state.batteryResult.value.standardFullVoltage, '4.200', 'LiPo 1cell full voltage should be shown');
assert.equal(state.batteryResult.value.standardNominalVoltage, '3.700', 'LiPo 1cell nominal voltage should be shown');
assert.equal(state.batteryResult.value.standardCutoffVoltage, '3.000', 'LiPo 1cell cutoff voltage should be shown');
state.battery.loads = [
    { name: 'LED', voltageV: 3.3, currentMa: 1, efficiencyPct: 90, durationSec: 1 },
    { name: 'マイコン', voltageV: 3.3, currentMa: 0.1, efficiencyPct: 90, durationSec: 10 },
    { name: 'GPS', voltageV: 3.3, currentMa: 30, efficiencyPct: 90, durationSec: 30 },
];
assertClose(Number(state.batteryResult.value.whPerCycle), 0.000919, 0.000001);
assertClose(Number(state.batteryResult.value.averagePowerW), 0.055122, 0.000001);
assertClose(Number(state.batteryResult.value.averageCurrentMa), 14.8979, 0.0001);
state.battery.internalResistance = 1.5;
const highResistanceRuntime = Number(state.batteryResult.value.runtimeToMinVoltageHours);
const highResistanceStartVoltage = Number(state.batteryResult.value.startVoltage);
state.battery.internalResistance = 0;
assert.ok(Number(state.batteryResult.value.runtimeToMinVoltageHours) > highResistanceRuntime, 'higher internal resistance should reduce voltage-limited runtime');
assert.ok(Number(state.batteryResult.value.startVoltage) > highResistanceStartVoltage, 'higher internal resistance should reduce loaded start voltage');
state.battery.internalResistance = 0.12;
state.battery.loads[0].efficiencyPct = 0;
assert.equal(state.analysisReport.value.verdict, 'FAIL', 'invalid battery load efficiency should fail the runtime analysis');
assert.match(
    state.analysisReport.value.summary,
    /効率 0(?:\.0+)?% は0%超かつ100%以下/u,
    'invalid efficiency warning should use the entered efficiency value'
);
state.battery.loads[0].efficiencyPct = 90;
state.battery.cellCount = 2;
assert.equal(state.battery.nominalVoltage, 7.4, 'changing battery cell count should apply a common nominal voltage');
assert.equal(state.batteryResult.value.standardFullVoltage, '8.400', 'LiPo 2cell full voltage should be shown');
state.battery.type = 'lead';
assert.equal(state.battery.cellCount, 6, 'lead battery should default to a 6cell pack');
assert.equal(state.battery.nominalVoltage, 12, 'lead battery should apply the common 12V nominal voltage');
assert.equal(state.batteryResult.value.standardFullVoltage, '12.720', 'lead 6cell full voltage should be shown');
assert.equal(state.batteryResult.value.standardCutoffVoltage, '10.500', 'lead 6cell cutoff voltage should be shown');
state.battery.capacityMah = 200000;
assert.deepEqual(
    state.batteryResult.value.runtimeScaleRows.map((row) => row.label),
    ['時間', '日', '月', '年'],
    'very long runtime should show all larger time scales, not just one'
);
state.battery.capacityMah = 1000;
state.battery.type = 'lipo';
state.battery.cellCount = 1;
state.battery.internalResistance = 0.12;
assert.ok(state.batteryGraph.value.points.includes(','), 'battery runtime should expose discharge curve points');
assert.equal(state.batteryCapacityPie.value.rows.length, 3, 'battery capacity pie should expose one row per periodic load');
assertClose(state.batteryCapacityPie.value.totalSharePct, 100, 0.00001);
assert.equal(state.batteryCapacityPie.value.totalWhLabel, '0.000919 Wh/周期', 'battery capacity pie should show the per-cycle Wh being allocated across loads');
assert.ok(
    state.batteryCapacityPie.value.rows.find((row) => row.name === 'GPS')?.sharePct > 99,
    'battery capacity pie should normalize load consumption shares to the consumed battery capacity'
);
const batteryLoadsForPieSortCheck = JSON.parse(JSON.stringify(state.battery.loads));
state.battery.loads = [
    { name: '小負荷', currentMa: 10, durationSec: 5 },
    { name: '大負荷', currentMa: 20, durationSec: 30 },
    { name: '中負荷', currentMa: 20, durationSec: 10 },
];
assert.deepEqual(
    state.batteryCapacityPie.value.rows.map((row) => row.name),
    ['大負荷', '中負荷', '小負荷'],
    'battery capacity pie should show larger load consumption first'
);
state.battery.loads = batteryLoadsForPieSortCheck;
assertTextOrder(
    batteryUiBlade,
    ['グラフ', '電圧降下 / 放電時間', '容量消費内訳', '負荷内訳', '電池100%の消費配分'],
    'battery graph section should show voltage graph and battery-consumption breakdown pie side by side'
);
const batteryGraphForCursor = state.batteryGraph.value;
state.updateBatteryGraphCursor({ currentTarget: { getBoundingClientRect: () => ({ left: 0, width: batteryGraphForCursor.width }) }, clientX: batteryGraphForCursor.plot.left + batteryGraphForCursor.plot.width / 2 });
assert.ok(state.batteryGraphTooltipBox.value?.voltage.includes('V'), 'battery graph hover should expose an SVG tooltip box with voltage');
assert.ok(state.batteryGraph.value.yTicks.every((tick) => tick.label.includes('V')), 'battery graph should expose voltage axis ticks');
state.battery.usablePct = 100;
state.battery.systemMinVoltage = 3.55;
const highCutoffRuntime = Number(state.batteryResult.value.runtimeHours);
assert.ok(Number(state.batteryResult.value.graphEndDepth) < 1, 'battery graph should end before full capacity when the cutoff voltage crosses early');
assertClose(Number(state.batteryGraph.value.samples.at(-1).voltage), Number(state.batteryResult.value.systemMinVoltage), 0.002);
assertClose(state.batteryGraph.value.runtimeHours, Number(state.batteryResult.value.runtimeToMinVoltageHours), 0.01);
assert.match(
    state.analysisReport.value.summary,
    /電圧下限到達 .*容量ベース .*より先/u,
    'battery warn summary should identify the cutoff-voltage-limited runtime'
);
assert.match(state.analysisReport.value.summary, /システム最低電圧 3\.550\s*V/u, 'battery warn summary should include the cutoff voltage threshold');
assert.doesNotMatch(state.analysisReport.value.summary, /どれか|いずれか|確認が必要/u, 'battery warn summary should not use vague wording');
state.battery.systemMinVoltage = 3.0;
const lowCutoffRuntime = Number(state.batteryResult.value.runtimeHours);
assert.ok(lowCutoffRuntime > highCutoffRuntime, 'lowering the discharge cutoff voltage should increase the effective runtime');
state.battery.capacityMah = 2000;
assert.ok(Number(state.batteryResult.value.runtimeHours) > lowCutoffRuntime * 1.9, 'battery capacity should scale the effective runtime');
state.battery.capacityMah = 1000;
state.battery.loads = [
    { name: 'LED', currentMa: 1, durationSec: 20 },
    { name: 'マイコン', currentMa: 0.1, durationSec: 20 },
    { name: 'GPS', currentMa: 30, durationSec: 30 },
];
assert.notEqual(state.analysisReport.value.verdict, 'FAIL', 'combined load duration above cycle should be allowed for overlapping loads');
assert.doesNotMatch(state.analysisReport.value.summary, /負荷ON合計 70\.000\s*s が周期 60s/u, 'battery summary should not reject overlapping load durations by total ON time');
assert.equal(metricValue(state.analysisReport.value, 'ON秒数合計(重複可)'), '70.000 s');
state.battery.loads = [
    { name: 'LED', currentMa: 1, durationSec: 1 },
    { name: 'マイコン', currentMa: 0.1, durationSec: 10 },
    { name: 'GPS', currentMa: 30, durationSec: 30 },
];
state.updateBatteryGraphCursor({ currentTarget: { getBoundingClientRect: () => ({ left: 0, width: batteryGraphForCursor.width }) }, clientX: state.batteryGraph.value.plot.left + state.batteryGraph.value.plot.width });
assert.equal(state.batteryGraphCursor.remainingPct, 0, 'right edge of the battery graph should represent 0% remaining capacity');
state.battery.loads[0].durationSec = 120;
assert.equal(state.analysisReport.value.verdict, 'FAIL', 'load duration above cycle should fail battery runtime analysis');
assert.match(state.analysisReport.value.summary, /LED のON秒数 120\.000\s*s が周期 60\.000\s*s を超えています/u, 'battery failure summary should identify the offending load, value, and cycle');
assert.doesNotMatch(state.analysisReport.value.summary, /どれか|いずれか/u, 'battery failure summary should not hide the failing parameter behind vague wording');
state.battery.loads[0].durationSec = 1;

state.activeToolId.value = 'eia96';
state.eia96.codeQuery = '01C';
assert.equal(state.eia96Lookup.value.display, '10 kΩ', '01C should decode to 10k ohm');
state.eia96.codeQuery = '68X';
assert.equal(state.eia96Lookup.value.display, '49.9 Ω', '68X should decode to 49.9 ohm');
state.eia96.codeQuery = '96A';
assert.equal(state.eia96Lookup.value.display, '976 Ω', '96A should decode to 976 ohm');
state.eia96.codeQuery = '01R';
assert.equal(state.eia96Lookup.value.valid, true, 'R should be accepted as an alternate multiplier for Y');
assert.equal(state.eia96Lookup.value.display, '1 Ω', '01R should decode through the Y multiplier');
state.eia96.valueQuery = '24.3k';
assert.equal(state.eia96ReverseMatches.value[0].code, '38C', 'reverse lookup should return the exact EIA-96 code when available');
state.eia96.selectedMultiplier = 'B';
assert.equal(state.eia96SelectedRows.value[0].code, '01B', 'selected multiplier table should use the chosen multiplier');
assert.equal(state.eia96SelectedRows.value.at(-1).display, '9.76 kΩ', 'selected multiplier table should expose all 96 base values');
state.eia96.searchQuery = '68X';
assert.ok(state.eia96FilteredRows.value.some((row) => row.code === '68X'), 'EIA-96 search should find codes across all multipliers');
assert.equal(metricValue(state.analysisReport.value, 'EIA-96コード'), '01R', 'EIA-96 report should expose the decoded code');
assert.ok(metricValue(state.analysisReport.value, '抵抗値').includes('1 Ω'), 'EIA-96 report should expose the decoded resistance');

state.activeToolId.value = 'adc';
state.adc.vinMax = 5;
assert.equal(state.analysisReport.value.verdict, 'FAIL', 'ADC max input above Vref should fail');
state.adc.vinMax = 3;

state.activeToolId.value = 'thermal';
Object.assign(state.thermal, {
    P: 30,
    Tambient: 25,
    TjLimit: 145,
    scenarioMultiplier: 1.5,
    nodes: [
        { label: '接合-ケース(θjc)', Rth: 0.5 },
        { label: 'ケース-放熱板(θcs)', Rth: 0.2 },
        { label: '放熱板-雰囲気(θsa)', Rth: 2 },
    ],
});
assert.equal(state.thermalResult.value.Tjunction, '106.0', 'thermal chain should calculate Tj from P, Ta, and total Rth');
assert.notEqual(state.analysisReport.value.verdict, 'FAIL', 'thermal normal Tj inside the limit should not fail even when worst scenario needs review');
state.thermal.scenarioMultiplier = 200;
assert.equal(state.analysisReport.value.verdict, 'WARN', 'thermal worst-case Tj should warn without failing a valid normal operating point');
state.thermal.scenarioMultiplier = 1.5;
assert.ok(
    state.thermalResult.value.heatsinkCandidates.some((item) => Number(item.tj) > state.thermal.Tambient + state.thermal.P * Number(item.rth)),
    'thermal heatsink candidates should include fixed upstream thermal resistance'
);
Object.assign(state.thermal, {
    P: 1,
    Tambient: 25,
    TjLimit: 125,
    scenarioMultiplier: 1.5,
    nodes: [
        { label: '接合-ケース(θjc)', Rth: 5 },
        { label: 'ケース-放熱板(θcs)', Rth: 0.5 },
        { label: '放熱板-雰囲気(θsa)', Rth: 10 },
    ],
});

state.activeToolId.value = 'bode';
state.quickForms.bode.type = 'highpass';
state.quickForms.bode.passbandFreq = 10000;
state.quickForms.bode.stopbandFreq = 100;
assert.equal(state.analysisReport.value.verdict, 'CHECK', 'high-pass pass/stop margins should be calculated but remain CHECK until load/parasitic conditions are confirmed');

state.activeToolId.value = 'connector';
assert.equal(state.analysisReport.value.verdict, 'CHECK', 'connector design should not PASS while evidence/temperature conditions are unresolved');

state.activeToolId.value = 'jumper';
state.quickForms.jumper.entries = 'JP1,0Ω,,実装\\nBROKEN';
assert.equal(state.analysisReport.value.verdict, 'WARN', 'jumper malformed rows and missing purposes should warn');

state.activeToolId.value = 'logic-ic';
assert.equal(state.analysisReport.value.verdict, 'CHECK', 'logic IC reference should remain CHECK until datasheet operating conditions are confirmed');
assert.ok(state.analysisReport.value.metrics.some((metric) => metric.label === '第一候補'), 'logic IC reference should expose candidate parts');
state.quickForms['logic-ic'].inputs = 3;
assert.ok(
    state.analysisReport.value.metrics.some((metric) => metric.label === '第一候補' && metric.value.includes('74HC10')),
    'logic IC reference should use input count when filtering candidates'
);

const logicForm = state.quickForms['logic-ic'];
/**
 * ロジックIC参照ツールの検索条件を既定値へ戻しつつ上書きする。
 * 入力は上書き項目で、logicFormのreactive状態を更新する副作用がある。
 */
// 目的: 設計解析ツールのset Logic Searchを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: 失敗時にassert例外を投げる場合がある。
const setLogicSearch = (overrides = {}) => {
    Object.assign(logicForm, {
        family: 'any',
        function: 'any',
        inputs: 0,
        packagePins: 0,
        supplyV: 5,
        outputType: 'any',
        ...overrides,
    });
};
/**
 * ロジックIC参照ツールの指定フィールドから選択肢value一覧を取得する。
 * 入力はフィールドキー、戻り値は選択肢配列で、副作用はない。
 */
// 目的: 設計解析ツールのlogic Field Optionsを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: 失敗時にassert例外を投げる場合がある。
const logicFieldOptions = (key) => (
    state.quickTool.value.fields.find((field) => field.key === key)?.options ?? []
).map(([value]) => value);
/** ロジックIC候補数メトリクスを数値化する。候補数が読めない場合は0を返し、副作用はない。 */
// 目的: 設計解析ツールのlogic Candidate Countを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: 失敗時にassert例外を投げる場合がある。
const logicCandidateCount = () => Number.parseInt(metricValue(state.analysisReport.value, '候補数'), 10) || 0;
/**
 * ロジックIC参照レポートを正規表現検証しやすい単一文字列へまとめる。
 * 入力は現在のstateで、戻り値はサマリ/メトリクス/警告の結合文字列、副作用はない。
 */
// 目的: 設計解析ツールのlogic Report Textを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: 失敗時にassert例外を投げる場合がある。
const logicReportText = () => [
    state.analysisReport.value.summary,
    ...state.analysisReport.value.metrics.flatMap((metric) => [metric.label, metric.value]),
    ...state.analysisReport.value.warnings,
].join(' ');
const requiredLogicFamilies = [
    '74LS',
    '74F',
    '74HC',
    '74HCU',
    '74HCT',
    '74VHC',
    '74VHCT',
    '74LC',
    '4000',
    '4500',
    '5000',
];
const logicCandidateMarkers = {
    '74LS': ['74LS'],
    '74F': ['74F'],
    '74HC': ['74HC'],
    '74HCU': ['74HCU'],
    '74HCT': ['74HCT'],
    '74VHC': ['74VHC'],
    '74VHCT': ['74VHCT'],
    '74LC': ['74LC', '74LVC', '74LCX'],
    '4000': ['4000', 'CD40'],
    '4500': ['4500', 'CD45'],
    '5000': ['5000', 'MC145', 'MC140'],
};

setLogicSearch();
assert.match(logicReportText(), /未使用入力/, 'logic IC reference should surface unused input handling as a required datasheet check');
const familyOptions = logicFieldOptions('family');
for (const family of requiredLogicFamilies) {
    const selectable = familyOptions.includes(family);
    if (!selectable) {
        setLogicSearch({ family });
    }

    assert.ok(
        selectable || (
            logicCandidateCount() > 0
            && logicCandidateMarkers[family].some((marker) => logicReportText().includes(marker))
        ),
        `logic IC reference should expose ${family} as a family option or candidate`
    );
}

for (const logicFunction of ['counter', 'decoder']) {
    setLogicSearch({ function: logicFunction });
    assert.ok(logicCandidateCount() > 0, `logic IC reference should expose ${logicFunction} candidates`);
    assert.notEqual(metricValue(state.analysisReport.value, '第一候補'), '該当なし', `logic IC ${logicFunction} candidate should not be empty`);
}

setLogicSearch({ family: '74HC', supplyV: 12 });
assert.equal(state.analysisReport.value.verdict, 'FAIL', 'logic IC candidate Vcc outside family range should fail');
assert.equal(metricValue(state.analysisReport.value, 'Vcc範囲内'), '0', 'logic IC candidate Vcc outside family range should have no in-range candidates');
assert.match(logicReportText(), /電源範囲外|Vcc範囲/, 'logic IC candidate Vcc outside family range should explain the supply range issue');
assert.equal(logicFieldOptions('driverFamily').length, 0, 'logic IC reference should not expose series-connection level judgment fields');
assert.equal(metricValue(state.analysisReport.value, 'レベル判定'), '', 'logic IC reference should not report series level judgment');

state.activeToolId.value = 'interface';
const highLevelShortagePattern = /High余裕不足|High.*不足|VOH\(min\)\s+[0-9.]+V\s+<\s+VIH\(min\)/i;
/**
 * IF余裕ツールのロジックレベル条件を既定値へ戻しつつ上書きする。
 * 入力は上書き項目で、state.ifaceのreactive状態を更新する副作用がある。
 */
// 目的: 設計解析ツールのset Interface Levelを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: 失敗時にassert例外を投げる場合がある。
const setInterfaceLevel = (overrides = {}) => {
    Object.assign(state.iface, {
        driverFamily: '74LS',
        driverVcc: 5,
        receiverFamily: '74HCT',
        receiverVcc: 5,
        VOH: 2.4,
        VOL: 0.4,
        VIH: 2.0,
        VIL: 0.8,
        ...overrides,
    });
};
/**
 * IF余裕レポートを正規表現検証しやすい単一文字列へまとめる。
 * 入力は現在のstateで、戻り値はサマリ/メトリクス/警告の結合文字列、副作用はない。
 */
// 目的: 設計解析ツールのinterface Report Textを扱う。機能: 入力値を検証・整形し、画面または計算処理へ渡す。入力: 関数シグネチャの値。出力: 処理結果またはなし。動作条件: 設計解析ツールの初期化後に呼び出す。副作用: 失敗時にassert例外を投げる場合がある。
const interfaceReportText = () => [
    state.analysisReport.value.summary,
    ...state.analysisReport.value.metrics.flatMap((metric) => [metric.label, metric.value]),
    ...state.analysisReport.value.warnings,
].join(' ');
setInterfaceLevel({ driverFamily: '74LS', driverVcc: 5, receiverFamily: '74HC', receiverVcc: 5 });
assert.equal(state.analysisReport.value.verdict, 'FAIL', 'TTL 74LS 5V to 74HC 5V should fail on High-level margin in IF余裕');
assert.match(interfaceReportText(), highLevelShortagePattern, 'TTL 74LS 5V to 74HC 5V should explain the High-level margin shortage in IF余裕');
assert.match(state.analysisReport.value.summary, highLevelShortagePattern, 'IF余裕 failure summary should identify the exact high-level threshold violation');
assert.doesNotMatch(state.analysisReport.value.summary, /どれか|いずれか/u, 'IF余裕 failure summary should not use vague wording');
assert.ok(metricValue(state.analysisReport.value, 'シリーズ接続').includes('FAIL'), 'IF余裕 should expose series-connection verdict');
assert.ok(metricValue(state.analysisReport.value, 'シリーズH/L余裕'), 'IF余裕 should expose series H/L margin');
assert.ok(metricValue(state.analysisReport.value, '入力耐圧余裕'), 'IF余裕 should expose receiver input voltage margin');
assert.ok(metricValue(state.analysisReport.value, '系列しきい値'), 'IF余裕 should expose approximate VOH/VOL/VIH/VIL thresholds');

setInterfaceLevel({ driverFamily: '74LS', driverVcc: 5, receiverFamily: '74HCT', receiverVcc: 5 });
assert.ok(
    ['PASS', 'CHECK', 'WARN'].includes(state.analysisReport.value.verdict),
    'TTL 74LS 5V to 74HCT 5V should be at least a non-failing approximate match in IF余裕'
);
assert.ok(!highLevelShortagePattern.test(interfaceReportText()), 'TTL 74LS 5V to 74HCT 5V should not report a High-level margin shortage in IF余裕');

setInterfaceLevel({ driverFamily: '74HC', driverVcc: 5, receiverFamily: '74HC', receiverVcc: 3.3 });
assert.equal(state.analysisReport.value.verdict, 'FAIL', '5V 74HC to 3.3V 74HC should fail or be blocked by input overvoltage risk in IF余裕');
assert.match(
    interfaceReportText(),
    /入力耐圧|入力上限|overvoltage|input.*(voltage|toler|max)/i,
    '5V 74HC to 3.3V 74HC should warn about receiver input voltage tolerance in IF余裕'
);

assertClose(state.parseNumber('1fF'), 1e-15);
assertClose(state.parseNumber('100nF'), 100e-9);
assertClose(state.parseNumber('2.2MΩ'), 2.2e6);
assertClose(state.parseNumber('3.3μV'), 3.3e-6);
assertClose(state.parseNumber('1T'), 1e12);
assertClose(state.parseNumber('1P'), 1e15);
assertClose(state.parseNumber('512KiB'), 512 * 1024);
assertClose(state.parseNumber('1Mi'), 1048576);

state.activeToolId.value = 'divider';
state.setNumericInput(state.divider, 'R0', { target: { value: '10kΩ' } });
state.setNumericInput(state.divider, 'Rmeas', { target: { value: '4.7k' } });
assertClose(state.divider.R0, 10000);
assertClose(state.divider.Rmeas, 4700);
assert.equal(state.numericInputValue(state.divider, 'Rmeas'), '4.7k', 'engineering notation should remain in the focused/editable NTC input after realtime parsing');
state.setNumericInput(state.divider, 'Rmeas', { target: { value: '6.2k' } });
assertClose(state.divider.Rmeas, 6200);
assert.equal(state.numericInputValue(state.divider, 'Rmeas'), '6.2k', 'NTC inputs should be editable as engineering notation without being rewritten to base units');
assert.ok(
    !state.toolSupplementInputGroups.value.flatMap((group) => group.fields).some((field) => field.key === 'dissipationMwPerC'),
    'NTC/PTC dissipation constant should live only in the tool-specific input surface'
);
assert.match(
    designToolsBlade,
    /放熱定数 \(mW\/℃\)[\s\S]*setNumericInput\(divider, 'dissipationMwPerC', \$event, 1e-3, false\)/,
    'NTC/PTC primary dissipation input should keep mW/℃ conversion in the single editable field'
);
state.setNumericInput(state.divider, 'dissipationMwPerC', { target: { value: '2' } }, 1e-3, false);
assertClose(state.divider.dissipationMwPerC, 2);
state.setNumericInput(state.divider, 'dissipationMwPerC', { target: { value: '2mW' } }, 1e-3, false);
assertClose(state.divider.dissipationMwPerC, 2);
state.setNumericInput(state.divider, 'dissipationMwPerC', { target: { value: '0.002W' } }, 1e-3, false);
assertClose(state.divider.dissipationMwPerC, 2);
assert.ok(Number.isFinite(Number(state.dividerResult.value.temp_c)), 'NTC/PTC tool should calculate temperature');
assert.ok(state.dividerGraph.value.points.includes(','), 'NTC/PTC tool should expose a temperature-voltage graph');
assert.ok(state.dividerGraph.value.sensitivityPoints.includes(','), 'NTC/PTC tool should expose a sensitivity graph');
assert.ok(state.dividerGraph.value.toleranceMinPoints.includes(','), 'NTC/PTC tool should expose a lower tolerance envelope');
assert.ok(state.dividerGraph.value.toleranceMaxPoints.includes(','), 'NTC/PTC tool should expose an upper tolerance envelope');
assert.ok(Number.isFinite(state.dividerGraph.value.targetRatio), 'NTC/PTC tool should quantify target range sensitivity');
assert.ok(parseFloat(state.dividerGraph.value.targetPower) > 0, 'NTC/PTC tool should calculate thermistor power loss');
assert.ok(parseFloat(state.dividerGraph.value.targetSelfHeat) > 0, 'NTC/PTC tool should calculate self-heating from power loss and dissipation constant');
assert.ok(parseFloat(state.dividerGraph.value.targetToleranceTempError) >= 0, 'NTC/PTC tool should calculate tolerance-driven temperature deviation');
assert.ok(
    Math.abs(state.dividerGraph.value.bestPoint.sensitivity - state.dividerGraph.value.maxSensitivity) <= 1e-12,
    'NTC/PTC graph should place the yellow marker on the maximum sensitivity point'
);
assert.ok(
    state.analysisReport.value.metrics.some((metric) => metric.label === '対象範囲感度'),
    'NTC/PTC report should expose sensitivity in the target temperature range'
);
assert.ok(
    state.analysisReport.value.metrics.some((metric) => metric.label === '自己発熱'),
    'NTC/PTC report should expose self-heating'
);
assert.ok(
    state.analysisReport.value.metrics.some((metric) => metric.label === '公差起因温度振れ'),
    'NTC/PTC report should expose tolerance-driven temperature deviation'
);
state.divider.sensorType = 'ptc';
assert.ok(Number.isFinite(Number(state.dividerResult.value.temp_c)), 'PTC mode should calculate temperature with the same graph surface');
state.divider.sensorType = 'ntc';
state.divider.targetTempMin = 180;
state.divider.targetTempMax = 220;
assert.equal(state.dividerGraph.value.sensitivityTone, 'bad', 'target temperature outside the sweep should be marked as a bad sensitivity match');
assert.ok(
    state.analysisReport.value.warnings.some((warning) => warning.includes('測定対象範囲')),
    'NTC/PTC report should warn when the measurement target is outside the useful sensitivity area'
);
state.divider.targetTempMin = 20;
state.divider.targetTempMax = 40;
assert.equal(state.analysisReport.value.verdict, 'CHECK', 'NTC/PTC tool should stay CHECK until thermistor tolerance/self-heating are confirmed');

state.activeToolId.value = 'shunt';
state.shunt.senseMode = 'unipolar';
state.shunt.I = 5;
state.shunt.Rs = 0.01;
state.shunt.gain = 20;
state.shunt.currentMin = 0;
state.shunt.currentMax = 5;
state.shunt.powerRating = 5;
state.shunt.adcBits = 12;
state.shunt.adcVref = 3.3;
state.shunt.ampOutputMin = 0;
state.shunt.ampOutputMax = 3.3;
assertClose(Number(state.shuntResult.value.Vshunt_mv), 50, 0.001);
assertClose(Number(state.shuntResult.value.Vout), 1, 0.0001);
assert.equal(state.shuntResult.value.adc_code, '1240', 'unipolar shunt simulator should expose the floored ADC code');
assert.equal(state.shuntResult.value.adc_bin, '010011011000', 'unipolar shunt simulator should expose ADC bin');
assert.equal(state.shuntResult.value.vout_at_imin_v, '0.0000', 'unipolar shunt range should expose amplifier output at Imin');
assert.equal(state.shuntResult.value.vout_at_imax_v, '1.0000', 'unipolar shunt range should expose amplifier output at Imax');
assertClose(Number(state.shuntResult.value.P_mW), 250, 0.001);
assert.notEqual(state.analysisReport.value.verdict, 'FAIL', 'rated shunt loss should be accepted when the selected shunt power rating has margin');
assertClose(parseFloat(metricValue(state.analysisReport.value, '入力換算オフセット誤差')), 0.005, 0.00001);
assertClose(parseFloat(metricValue(state.analysisReport.value, 'ADC電流LSB')), 0.004029, 0.00001);
assert.ok(metricValue(state.analysisReport.value, '100℃ TCR目安').includes('0.500'), 'shunt report should expose TCR drift');
assert.equal(metricValue(state.analysisReport.value, 'ADCコード'), '1240 / 010011011000 / 0x4D8');
assert.ok(metricValue(state.analysisReport.value, 'ADC下限余裕'), 'shunt report should expose ADC lower margin');
assert.ok(metricValue(state.analysisReport.value, 'ADC上限余裕'), 'shunt report should expose ADC upper margin');
assert.ok(metricValue(state.analysisReport.value, 'アンプ出力下限余裕'), 'shunt report should expose amplifier lower swing margin');
assert.ok(metricValue(state.analysisReport.value, 'アンプ出力上限余裕'), 'shunt report should expose amplifier upper swing margin');
assert.ok(
    !state.analysisReport.value.warnings.some((warning) => warning.includes('電力定格')),
    'shunt report should not use an arbitrary mW threshold when power rating is sufficient'
);
state.shunt.I = 6;
state.shunt.currentMax = 6;
state.shunt.powerRating = 0.25;
assert.equal(state.analysisReport.value.verdict, 'FAIL', 'shunt loss above the selected resistor power rating should fail');
assert.ok(
    state.analysisReport.value.warnings.some((warning) => warning.includes('電力定格を超えています')),
    'shunt report should warn only when the selected resistor rating is exceeded'
);
state.shunt.powerRating = 5;
state.setNumericInput(state.shunt, 'tcrPpm', { target: { value: '50ppm' } });
assertClose(state.shunt.tcrPpm, 50, 0.000001);
state.shunt.senseMode = 'bidirectional';
state.shunt.outputOffset = 1.65;
state.shunt.currentMin = -5;
state.shunt.currentMax = 5;
state.shunt.I = -2;
assertClose(Number(state.shuntResult.value.Vshunt_mv), -20, 0.001);
assertClose(Number(state.shuntResult.value.Vout), 1.25, 0.0001);
assert.equal(state.shuntResult.value.adc_code, '1551', 'bidirectional shunt simulator should calculate negative-current ADC code from offset');
assert.equal(state.shuntResult.value.adc_bin, '011000001111');
assertClose(Number(state.shuntResult.value.adc_range_used_pct), 60.61, 0.01);
assert.equal(state.shuntResult.value.vout_at_imin_v, '0.6500', 'bidirectional shunt range should expose amplifier output at negative Imin');
assert.equal(state.shuntResult.value.vout_at_imax_v, '2.6500', 'bidirectional shunt range should expose amplifier output at positive Imax');
assert.equal(metricValue(state.analysisReport.value, '最小電流時アンプ出力'), '0.6500 V', 'shunt report should expose amplifier output at the minimum current');
assert.equal(metricValue(state.analysisReport.value, '最大電流時アンプ出力'), '2.6500 V', 'shunt report should expose amplifier output at the maximum current');
assert.notEqual(state.analysisReport.value.verdict, 'FAIL', 'bidirectional shunt range should fit ADC and amplifier ranges');
Object.assign(state.shunt, {
    senseMode: 'bidirectional',
    currentMin: -1,
    currentMax: 10,
    I: 0,
    Rs: 0.01,
    gain: 25,
    outputOffset: 0.5,
    powerRating: 0.25,
    ampOutputMin: 0,
    ampOutputMax: 3.3,
    adcVref: 3.3,
});
assert.equal(state.shuntResult.value.vout_at_imin_v, '0.2500', 'bidirectional offset case should keep Imin Vout inside range');
assert.equal(state.shuntResult.value.vout_at_imax_v, '3.0000', 'bidirectional offset case should keep Imax Vout inside range');
assert.equal(state.analysisReport.value.verdict, 'FAIL', 'bidirectional offset case should fail only because the selected Rs power rating is exceeded');
assert.ok(metricValue(state.analysisReport.value, 'FAIL理由').includes('Rs電力定格超過'), 'shunt report should surface Rs power rating as the explicit fail reason');
assert.match(state.analysisReport.value.summary, /シャント抵抗|Rs|電力定格/u, 'shunt failure summary should identify the selected Rs power rating as the failing parameter');
assert.doesNotMatch(state.analysisReport.value.summary, /どれか|いずれか/u, 'shunt failure summary should not use vague wording');
assert.ok(parseFloat(metricValue(state.analysisReport.value, 'ADC下限余裕')) > 0, 'offset case should keep ADC lower margin positive');
assert.ok(parseFloat(metricValue(state.analysisReport.value, 'ADC上限余裕')) > 0, 'offset case should keep ADC upper margin positive');
state.shunt.senseMode = 'unipolar';
state.shunt.currentMin = -1;
state.shunt.currentMax = 5;
assert.equal(state.analysisReport.value.verdict, 'FAIL', 'unipolar shunt mode should reject negative-current ranges');

state.activeToolId.value = 'comparator';
Object.assign(state.comp, {
    referenceMode: 'external',
    inputPolarity: 'positive',
    Vcc: 3.3,
    Vref: 1.65,
    VOH: 3.3,
    VOL: 0,
    R1: 100000,
    R3: 1000000,
    R2: 100000,
    R4: 100000,
    tolerancePct: 1,
    inputOffsetMv: 5,
    inputBiasNa: 50,
    noiseMv: 20,
    candidateResistors: '10000,47000,100000',
});
assertClose(Number(state.compResult.value.Vth_rising), 1.815, 0.0005);
assertClose(Number(state.compResult.value.Vth_falling), 1.485, 0.0005);
assertClose(Number(state.compResult.value.hysteresis), 0.33, 0.0005);
assert.equal(state.compResult.value.r1_shorted, false, '+入力/Vref方式 should keep R1 active');
assert.ok(Number(state.compResult.value.tolerance_band) > 0.02, 'comparator should include tolerance, offset, and input-bias error in the tolerance band');
assert.equal(state.compResult.value.candidates.length, 3, 'comparator should parse candidate resistor values');
assert.ok(state.compResult.value.topology.includes('Vref基準'), 'comparator should expose the Vref reference topology');
assert.ok(metricValue(state.analysisReport.value, 'R1/R3比'), 'comparator report should expose the R1/R3 feedback ratio');
assert.ok(metricValue(state.analysisReport.value, '入力極性').includes('+入力'), 'comparator report should expose positive input polarity');
state.comp.noiseMv = 200;
assert.equal(state.analysisReport.value.verdict, 'WARN', 'comparator should warn when noise exceeds hysteresis margin');
assert.ok(
    state.analysisReport.value.warnings.some((warning) => warning.includes('ノイズ')),
    'comparator report should explain noise margin shortage'
);
state.comp.noiseMv = 20;
state.comp.inputOffsetMv = 50;
assert.ok(Number(state.compResult.value.tolerance_band) > 0.06, 'comparator should reflect input offset in tolerance band');
state.comp.inputOffsetMv = 5;
state.comp.referenceMode = 'divider';
assertClose(Number(state.compResult.value.reference_voltage_v), 1.65, 0.0005);
assertClose(Number(state.compResult.value.reference_thevenin_ohm), 50000, 1);
assert.equal(state.compResult.value.r1_shorted, false, '+入力/Vcc分圧方式 should not short R1');
assert.equal(Number(state.compResult.value.input_series_ohm), 100000, '+入力/Vcc分圧方式 should use R1 as the Vin-side feedback ratio resistor');
assertClose(Number(state.compResult.value.hysteresis), 0.33, 0.0005);
assert.ok(state.activeDiagram.value.parts.some((part) => part.key === 'R4'), 'divider reference mode should expose the lower divider resistor');
state.comp.referenceMode = 'external';
state.comp.inputPolarity = 'negative';
assertClose(Number(state.compResult.value.Vth_rising), 1.8, 0.0005);
assertClose(Number(state.compResult.value.Vth_falling), 1.5, 0.0005);
assertClose(Number(state.compResult.value.hysteresis), 0.3, 0.0005);
assert.equal(state.compResult.value.r1_shorted, false, '-入力/Vref方式 should keep R1 as the reference-side source resistance');
assert.equal(Number(state.compResult.value.source_resistance_ohm), 100000, '-入力/Vref方式 should use R1 as Rsrc');
assert.ok(state.compResult.value.rising_transition.includes('High→Low'), 'inverting comparator should flip the rising-input output transition');
assert.ok(metricValue(state.analysisReport.value, '入力極性').includes('-入力'), 'comparator report should expose negative input polarity');
assert.equal(metricValue(state.analysisReport.value, 'R1 Vin側抵抗'), '', 'negative input mode should not label R1 as a Vin-side resistor');
assert.ok(metricValue(state.analysisReport.value, 'R1 基準側抵抗'), 'negative input mode should label R1 as a reference-side resistor');
state.comp.referenceMode = 'divider';
assertClose(Number(state.compResult.value.reference_thevenin_ohm), 50000, 1);
assert.equal(state.compResult.value.r1_shorted, true, '-入力/Vcc分圧方式 should short R1');
assert.equal(Number(state.compResult.value.input_series_ohm), 0, '-入力/Vcc分圧方式 should report R1 as shorted');
assert.equal(Number(state.compResult.value.source_resistance_ohm), 50000, '-入力/Vcc分圧方式 should use R2||R4 as Rsrc');
assertClose(Number(state.compResult.value.hysteresis), 0.1571, 0.001);
assert.ok(metricValue(state.analysisReport.value, 'R1 ショート'), 'negative divider mode should report R1 as shorted');
state.comp.R3 = 0;
assert.equal(Number(state.compResult.value.hysteresis), 0, 'R3=0 should explicitly disable hysteresis');
assert.ok(
    state.analysisReport.value.warnings.some((warning) => warning.includes('R3')),
    'comparator report should explain that R3 is required for hysteresis'
);
state.comp.referenceMode = 'external';
state.comp.inputPolarity = 'positive';
state.comp.R3 = 1000000;
state.setNumericInput(state.comp, 'inputBiasNa', { target: { value: '50nA' } }, 1e-9);
assertClose(state.comp.inputBiasNa, 50, 0.000001);

state.activeToolId.value = 'network-search';
assert.equal(state.analysisReport.value.verdict, 'CHECK');
assert.ok(state.passiveNetwork, 'passive network surface should be embedded in design tools');
assert.equal(state.passiveNetwork.activeMode, 'network', 'network-search should select the network exploration surface');
assert.ok(state.activeDiagram.value.title.includes('ネットワーク探索'));
state.activeToolId.value = 'divider-design';
assert.equal(state.passiveNetwork.activeMode, 'divider', 'divider-design should select the divider surface');
assert.ok(state.activeDiagram.value.title.includes('分圧'));
state.activeToolId.value = 'variable-resistor';
assert.equal(state.passiveNetwork.activeMode, 'variable', 'variable-resistor should select the variable resistor surface');
assert.ok(state.activeDiagram.value.title.includes('可変抵抗'));
assert.ok(
    !state.analysisReport.value.candidateLinks.some((link) => link.url === '/tools/network'),
    'passive design tools should not send the user to another page'
);
state.activeToolId.value = 'passive-network';
assert.equal(state.activeToolId.value, 'network-search', 'legacy passive-network id should normalize to network-search');

state.activeToolId.value = 'connector';
assert.ok(state.connectorCatalog.value.some((template) => template.label.includes('USB Type-C')));
assert.ok(state.connectorCatalog.value.some((template) => template.label.includes('D-sub')));
assert.ok(state.connectorCatalog.value.some((template) => template.label.includes('RJ45')));
assert.ok(state.connectorPinMap.value.some((pin) => pin.pin === 'A4' && pin.signal === 'VBUS'));
assert.ok(state.connectorSummary.value.missingConditions.includes('写真またはピン配置図'));
state.quickForms.connector.pinAssignments = 'A4,VBUS,power,5,10,red,30,over current';
assert.equal(state.analysisReport.value.verdict, 'FAIL', 'connector pin current over derated rating should fail');
const usbA = state.connectorCatalog.value.find((template) => template.id === 'usb2-type-a');
state.applyConnectorTemplate(usbA);
assert.ok(state.quickForms.connector.pinAssignments.includes('1,VBUS,power'), 'connector template apply should replace the assignment rows with the selected pin map');
assert.equal(state.connectorPinMap.value.length, 4, 'connector template apply should switch the active pin map');
state.quickForms.connector.pinAssignments = '1,VCC,power,12,0.5,red,24\n2,GND,ground,0,0.5,black,24';
state.quickForms.connector.userTemplateName = 'User 2P Power';
state.quickForms.connector.userTemplatePins = 2;
state.quickForms.connector.userTemplateCurrentRatingPerPin = 1;
state.quickForms.connector.userTemplatePhotoUrl = 'https://example.test/connector.jpg';
state.quickForms.connector.userTemplateDiagramUrl = 'https://example.test/pinout.png';
state.quickForms.connector.userTemplateDatasheetUrl = 'https://example.test/datasheet.pdf';
state.saveConnectorTemplate();
assert.ok(state.connectorUserTemplates.value.some((template) => template.label === 'User 2P Power'));
const savedConnector = state.connectorUserTemplates.value.find((template) => template.label === 'User 2P Power');
assert.equal(savedConnector.photoUrl, 'https://example.test/connector.jpg');
assert.equal(savedConnector.diagramUrl, 'https://example.test/pinout.png');
assert.equal(savedConnector.datasheetUrl, 'https://example.test/datasheet.pdf');
assert.equal(state.connectorActiveTemplate.value.label, 'User 2P Power');

state.activeToolId.value = 'bode';
state.setNumericInput(state.quickForms.bode, 'r', { target: { value: '10kΩ' } });
state.setNumericInput(state.quickForms.bode, 'c', { target: { value: '100nF' } });
assertClose(state.quickForms.bode.r, 10000);
assertClose(state.quickForms.bode.c, 100e-9);
assert.equal(state.numericInputValue(state.quickForms.bode, 'r'), '10kΩ', 'quick calculation inputs should retain engineering notation');
assert.equal(state.numericInputValue(state.quickForms.bode, 'c'), '100nF', 'quick calculation inputs should retain engineering notation');
assertClose(Number(state.quickTool.value.rows.find((row) => row[0] === 'fc')?.[1].split(' ')[0]), 159.155, 0.01);

state.setNumericInput(state.power.loads[0], 'mA', { target: { value: '50mA' } }, 1e-3);
assertClose(state.power.loads[0].mA, 50);
assert.equal(state.numericInputValue(state.power.loads[0], 'mA'), '50mA', 'power load current inputs should retain engineering notation while storing mA');
state.setNumericInput(state.power.loads[0], 'mA', { target: { value: '50m' } }, 1e-3, true);
assertClose(state.power.loads[0].mA, 50);
assert.equal(state.numericInputValue(state.power.loads[0], 'mA'), '50m', 'power load current inputs should accept prefix-only A notation while storing mA');
state.setNumericInput(state.battery, 'capacityMah', { target: { value: '1000mAh' } }, 1e-3);
assertClose(state.battery.capacityMah, 1000);
assert.equal(state.numericInputValue(state.battery, 'capacityMah'), '1000mAh', 'battery capacity inputs should retain mAh notation while storing mAh');
state.setNumericInput(state.battery, 'capacityMah', { target: { value: '1000m' } }, 1e-3, true);
assertClose(state.battery.capacityMah, 1000);
assert.equal(state.numericInputValue(state.battery, 'capacityMah'), '1000m', 'battery capacity inputs should accept prefix-only Ah notation while storing mAh');
state.setNumericInput(state.battery.loads[1], 'currentMa', { target: { value: '100uA' } }, 1e-3);
assertClose(state.battery.loads[1].currentMa, 0.1);
assert.equal(state.numericInputValue(state.battery.loads[1], 'currentMa'), '100uA', 'battery load currents should retain microamp notation while storing mA');
state.setNumericInput(state.battery.loads[1], 'currentMa', { target: { value: '100u' } }, 1e-3, true);
assertClose(state.battery.loads[1].currentMa, 0.1);
assert.equal(state.numericInputValue(state.battery.loads[1], 'currentMa'), '100u', 'battery load currents should accept prefix-only A notation while storing mA');
state.setNumericInput(state.adc, 'vin', { target: { value: '100mV' } });
assertClose(state.adc.vin, 0.1);
assert.equal(state.numericInputValue(state.adc, 'vin'), '100mV', 'ADC inputs should retain engineering notation');
state.setNumericInput(state.shunt, 'Rs', { target: { value: '10mΩ' } });
assertClose(state.shunt.Rs, 0.01);
assert.equal(state.numericInputValue(state.shunt, 'Rs'), '10mΩ', 'shunt inputs should retain engineering notation');
state.setNumericInput(state.comp, 'R1', { target: { value: '100k' } });
assertClose(state.comp.R1, 100000);
assert.equal(state.numericInputValue(state.comp, 'R1'), '100k', 'comparator resistor inputs should retain engineering notation');
state.setNumericInput(state.thermal, 'P', { target: { value: '500mW' } });
assertClose(state.thermal.P, 0.5);
assert.equal(state.numericInputValue(state.thermal, 'P'), '500mW', 'thermal power inputs should retain engineering notation');
state.setNumericInput(state.iface, 'i2cBusCapPf', { target: { value: '200pF' } }, 1e-12);
assertClose(state.iface.i2cBusCapPf, 200);
assert.equal(state.numericInputValue(state.iface, 'i2cBusCapPf'), '200pF', 'advanced interface inputs should retain engineering notation while storing pF');
state.setNumericInput(state.quickForms.tvs, 'pulseMs', { target: { value: '100us' } }, 1e-3);
assertClose(state.quickForms.tvs.pulseMs, 0.1);
assert.equal(state.numericInputValue(state.quickForms.tvs, 'pulseMs'), '100us', 'quick protection inputs should retain engineering notation while storing ms');
state.setNumericInput(state.iface, 'i2cBusCapPf', { target: { value: '100p' } }, 1e-12);
assertClose(state.iface.i2cBusCapPf, 100);
assert.equal(state.numericInputValue(state.iface, 'i2cBusCapPf'), '100p');

console.log('design tools assertions passed');
