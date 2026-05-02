import assert from 'node:assert/strict';

global.document = {
    getElementById: () => ({ dataset: { tool: 'adc' } }),
    querySelector: () => null,
    createElement: () => ({}),
};
global.navigator = {};

const { default: setupDesignTools } = await import('../resources/js/pages/design-tools.js');

const state = setupDesignTools();
const assertClose = (actual, expected, tolerance = Math.abs(expected) * 1e-9 + 1e-12) => {
    assert.ok(Math.abs(actual - expected) <= tolerance, `${actual} should be close to ${expected}`);
};
const metricValue = (report, label) => report.metrics.find((metric) => metric.label === label)?.value ?? '';
const requiredTools = [
    'passive-network',
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
const setLogicSearch = (overrides = {}) => {
    Object.assign(logicForm, {
        family: 'any',
        function: 'any',
        inputs: 0,
        packagePins: 0,
        supplyV: 5,
        outputType: 'any',
        driverFamily: '74LS',
        driverVcc: 5,
        receiverFamily: '74HCT',
        receiverVcc: 5,
        ...overrides,
    });
};
const logicFieldOptions = (key) => (
    state.quickTool.value.fields.find((field) => field.key === key)?.options ?? []
).map(([value]) => value);
const logicCandidateCount = () => Number.parseInt(metricValue(state.analysisReport.value, '候補数'), 10) || 0;
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

setLogicSearch({ driverFamily: '74LS', driverVcc: 5, receiverFamily: '74HC', receiverVcc: 5 });
const highLevelShortagePattern = /High余裕不足|High.*不足|VOH\(min\)\s+[0-9.]+V\s+<\s+VIH\(min\)/i;
assert.equal(state.analysisReport.value.verdict, 'FAIL', 'TTL 74LS 5V to 74HC 5V should fail on High-level margin');
assert.match(logicReportText(), highLevelShortagePattern, 'TTL 74LS 5V to 74HC 5V should explain the High-level margin shortage');

setLogicSearch({ driverFamily: '74LS', driverVcc: 5, receiverFamily: '74HCT', receiverVcc: 5 });
assert.ok(
    ['PASS', 'CHECK', 'WARN'].includes(state.analysisReport.value.verdict),
    'TTL 74LS 5V to 74HCT 5V should be at least a non-failing approximate match'
);
assert.ok(!highLevelShortagePattern.test(logicReportText()), 'TTL 74LS 5V to 74HCT 5V should not report a High-level margin shortage');

setLogicSearch({ driverFamily: '74HC', driverVcc: 5, receiverFamily: '74HC', receiverVcc: 3.3 });
assert.equal(state.analysisReport.value.verdict, 'FAIL', '5V 74HC to 3.3V 74HC should fail or be blocked by input overvoltage risk');
assert.match(
    logicReportText(),
    /入力耐圧|入力上限|overvoltage|input.*(voltage|toler|max)/i,
    '5V 74HC to 3.3V 74HC should warn about receiver input voltage tolerance'
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
assert.ok(Number.isFinite(Number(state.dividerResult.value.temp_c)), 'NTC/PTC tool should calculate temperature');
assert.equal(state.analysisReport.value.verdict, 'CHECK', 'NTC/PTC tool should stay CHECK until thermistor tolerance/self-heating are confirmed');

state.activeToolId.value = 'passive-network';
assert.equal(state.analysisReport.value.verdict, 'CHECK');
assert.ok(
    state.analysisReport.value.candidateLinks.some((link) => link.url === '/tools/network'),
    'passive network hub card should link to the authoritative network/divider tool'
);

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
assertClose(Number(state.quickTool.value.rows.find((row) => row[0] === 'fc')?.[1].split(' ')[0]), 159.155, 0.01);

state.setNumericInput(state.power.loads[0], 'mA', { target: { value: '50mA' } }, 1e-3);
assertClose(state.power.loads[0].mA, 50);
state.setNumericInput(state.iface, 'i2cBusCapPf', { target: { value: '200pF' } }, 1e-12);
assertClose(state.iface.i2cBusCapPf, 200);
state.setNumericInput(state.quickForms.tvs, 'pulseMs', { target: { value: '100us' } }, 1e-3);
assertClose(state.quickForms.tvs.pulseMs, 0.1);
state.setNumericInput(state.iface, 'i2cBusCapPf', { target: { value: '100p' } }, 1e-12);
assertClose(state.iface.i2cBusCapPf, 100);

console.log('design tools assertions passed');
