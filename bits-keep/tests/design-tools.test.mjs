import assert from 'node:assert/strict';

global.document = {
    getElementById: () => ({ dataset: { tool: 'adc' } }),
    querySelector: () => null,
    createElement: () => ({}),
};
global.navigator = {};

const { default: setupDesignTools } = await import('../resources/js/pages/design-tools.js');

const state = setupDesignTools();
const requiredTools = [
    'adc',
    'cap-life',
    'divider',
    'shunt',
    'power',
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

for (const toolId of requiredTools) {
    assert.ok(toolIds.includes(toolId), `${toolId} is missing from design tool tabs`);
}

for (const toolId of requiredTools) {
    state.activeToolId.value = toolId;
    const diagram = state.activeDiagram.value;
    const report = state.analysisReport.value;

    assert.ok(diagram, `${toolId} should expose a circuit/context diagram`);
    assert.ok(Array.isArray(diagram.parts) && diagram.parts.length > 0, `${toolId} diagram should expose labeled parts`);
    assert.ok(report, `${toolId} should produce an analysis report`);
    assert.ok(verdicts.has(report.verdict), `${toolId} verdict should be normalized`);
    assert.notEqual(report.summary, 'このツールの判定モデルが未定義です。', `${toolId} should not fall back to the undefined model report`);
    assert.ok(report.metrics.length > 0, `${toolId} should expose metrics`);
    assert.ok(report.nextActions.length > 0, `${toolId} should expose next actions`);
}

for (const toolId of ['ovp', 'tvs', 'fuse', 'polyfuse']) {
    state.activeToolId.value = toolId;
    const report = state.analysisReport.value;

    assert.equal(report.verdict, 'CHECK', `${toolId} should stay CHECK while component ratings are blank`);
    assert.ok(report.missingConditions.length > 0, `${toolId} should list missing rating/curve conditions`);
}

state.activeToolId.value = 'power';
assert.equal(state.analysisReport.value.verdict, 'CHECK', 'power tool should not PASS while inrush pulse conditions are unresolved');
assert.ok(state.analysisReport.value.missingConditions.includes('突入電流の時間幅'));

state.activeToolId.value = 'adc';
state.adc.vinMax = 5;
assert.equal(state.analysisReport.value.verdict, 'FAIL', 'ADC max input above Vref should fail');
state.adc.vinMax = 3;

state.activeToolId.value = 'thermal';
state.thermal.scenarioMultiplier = 200;
assert.equal(state.analysisReport.value.verdict, 'FAIL', 'thermal worst-case Tj should participate in FAIL judgment');
state.thermal.scenarioMultiplier = 1.5;
assert.ok(
    state.thermalResult.value.heatsinkCandidates.some((item) => Number(item.tj) > state.thermal.Tambient + state.thermal.P * Number(item.rth)),
    'thermal heatsink candidates should include fixed upstream thermal resistance'
);

state.activeToolId.value = 'bode';
state.quickForms.bode.type = 'highpass';
state.quickForms.bode.passbandFreq = 10000;
state.quickForms.bode.stopbandFreq = 100;
assert.equal(state.analysisReport.value.verdict, 'PASS', 'high-pass pass/stop margins should use the high-pass direction');

state.activeToolId.value = 'connector';
assert.equal(state.analysisReport.value.verdict, 'CHECK', 'connector reference should not PASS without current/temperature ratings');

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

console.log('design tools assertions passed');
