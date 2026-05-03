import assert from 'node:assert/strict';
import { readdirSync, readFileSync } from 'node:fs';

/**
 * 目的: 設計ツールのNodeテストで使うブラウザAPIと保存済み解析APIを固定する。
 * 機能: document/localStorage/fetchを最小実装し、Vue setupの実行前提を作る。
 * 入力: なし。
 * 出力: なし。
 * 動作条件: design-tools.jsを動的importする前に呼び出すこと。
 * 副作用: globalオブジェクトへdocument/location/navigator/localStorage/fetchを設定する。
 */
export function installDesignToolBrowserStubs() {
    global.document = {
        getElementById: () => ({ dataset: { tool: '' } }),
        querySelector: () => null,
        createElement: () => ({}),
    };
    global.location = { search: '' };
    global.navigator = {};
    global.localStorage = {
        getItem: (key) => localStore.get(key) ?? null,
        setItem: (key, value) => localStore.set(key, String(value)),
        removeItem: (key) => localStore.delete(key),
        clear: () => localStore.clear(),
    };
    global.fetch = fetchDesignToolApi;
}

// localStorageスタブの実データ。Mapなのでテストごとにclearできる。
export const localStore = new Map();

// fetchスタブの呼び出し履歴。保存APIのpayload検証に使う。
export const fetchCalls = [];

// 保存済み解析一覧/復元テストで使う固定レスポンス。
export const savedSessionFixture = {
    id: 101,
    tool_id: 'battery-runtime',
    title: '保存済み バッテリー',
    verdict: 'WARN',
    summary: '前回条件',
    input_payload: {
        type: 'lipo',
        capacityMah: 2000,
        cellCount: 1,
        nominalVoltage: 3.7,
        usablePct: 80,
        internalResistance: 0.1,
        cycleSec: 120,
        requiredHours: 48,
        systemMinVoltage: 2.8,
        loads: [
            { name: '復元LED', voltageV: 3.3, currentMa: 2, efficiencyPct: 90, durationSec: 2 },
        ],
    },
    result_payload: { verdict: 'WARN' },
    project_id: 7,
    project: { id: 7, name: '案件A' },
    component_id: 11,
    component: { id: 11, part_number: 'LED-001' },
    bom_line_key: 'BOM-1',
    updated_at: '2026-05-03T00:00:00Z',
};

/**
 * 目的: 設計ツールテスト用にAPIレスポンスを固定する。
 * 機能: 解析セッション、部品検索、削除APIのResponse互換オブジェクトを返す。
 * 入力: URLとfetchオプション。
 * 出力: Response互換オブジェクト。
 * 動作条件: installDesignToolBrowserStubsからglobal.fetchへ設定して使う。
 * 副作用: fetchCallsへ呼び出し履歴を追加する。
 */
async function fetchDesignToolApi(url, options = {}) {
    const requestUrl = String(url);
    const method = options.method ?? 'GET';
    fetchCalls.push({ url: requestUrl, options, method });

    if (requestUrl.startsWith('/api/analysis-sessions') && method === 'GET') {
        return { ok: true, status: 200, json: async () => ({ data: [savedSessionFixture] }) };
    }
    if (requestUrl === '/api/analysis-sessions' && method === 'POST') {
        return { ok: true, status: 201, json: async () => ({ data: { ...savedSessionFixture, id: 202, ...JSON.parse(options.body) } }) };
    }
    if (requestUrl === '/api/analysis-sessions/101' && method === 'DELETE') {
        return { ok: true, status: 204, json: async () => ({}) };
    }
    if (requestUrl.startsWith('/api/components?')) {
        return {
            ok: true,
            status: 200,
            json: async () => ({
                data: {
                    data: [
                        { id: 11, part_number: 'LED-001', common_name: 'LED', manufacturer: 'RWC', quantity_new: 2, quantity_used: 0 },
                    ],
                },
            }),
        };
    }

    return { ok: false, status: 404, json: async () => ({ message: `unexpected fetch ${method} ${requestUrl}` }) };
}

/**
 * 目的: 計算結果を相対誤差込みで検証する。
 * 機能: 浮動小数点の丸め差を許容して期待値と比較する。
 * 入力: 実測値、期待値、許容差。
 * 出力: なし。
 * 動作条件: 数値同士の比較に使うこと。
 * 副作用: 失敗時にassert例外を投げる。
 */
export function assertClose(actual, expected, tolerance = Math.abs(expected) * 1e-9 + 1e-12) {
    assert.ok(Math.abs(actual - expected) <= tolerance, `${actual} should be close to ${expected}`);
}

/**
 * 目的: 設計ツールのBladeとJSを文字列としてまとめて読み込む。
 * 機能: 親ファイルと分割partial/moduleを結合し、UI表面テスト用の検索対象を作る。
 * 入力: なし。
 * 出力: Blade文字列、JS文字列、バッテリーUI partial、結合文字列。
 * 動作条件: テストファイルからの相対パス構成が現行リポジトリと一致すること。
 * 副作用: ファイルシステムを読み取る。
 */
export function loadDesignToolSources() {
    const designToolBladeMain = readFileSync(new URL('../resources/views/app/design-tools.blade.php', import.meta.url), 'utf8');
    const designToolBladePartialDir = new URL('../resources/views/app/design-tools/', import.meta.url);
    const designToolBladePartials = readdirSync(designToolBladePartialDir)
        .filter((file) => file.endsWith('.blade.php'))
        .map((file) => readFileSync(new URL(`../resources/views/app/design-tools/${file}`, import.meta.url), 'utf8'));
    const designToolsBlade = [
        designToolBladeMain,
        ...designToolBladePartials,
    ].join('\n');
    const designToolsScript = [
        readFileSync(new URL('../resources/js/pages/design-tools.js', import.meta.url), 'utf8'),
        ...readdirSync(new URL('../resources/js/pages/design-tools/', import.meta.url))
            .filter((file) => file.endsWith('.js'))
            .map((file) => readFileSync(new URL(`../resources/js/pages/design-tools/${file}`, import.meta.url), 'utf8')),
    ].join('\n');
    const batteryUiBlade = readFileSync(new URL('../resources/views/app/design-tools/_battery-runtime-tool.blade.php', import.meta.url), 'utf8');

    return {
        designToolsBlade,
        designToolsScript,
        batteryUiBlade,
        designToolSurfaceText: `${designToolsBlade}\n${designToolsScript}`,
    };
}

/**
 * 目的: 指定ラベル群が文字列内で順番通りに出現することを検証する。
 * 機能: UI項目の並び順が崩れていないかを検索位置で確認する。
 * 入力: 検索対象、ラベル配列、失敗メッセージ。
 * 出力: なし。
 * 動作条件: ラベルが一意に近いUI文言であること。
 * 副作用: 失敗時にassert例外を投げる。
 */
export function assertTextOrder(source, labels, message) {
    let cursor = -1;
    for (const label of labels) {
        const index = source.indexOf(label, cursor + 1);
        assert.ok(index > cursor, `${message}: ${label} should appear after the previous label`);
        cursor = index;
    }
}

/**
 * 目的: 解析レポートから指定メトリクスの値を取り出す。
 * 機能: label一致のmetricを検索し、存在しない場合は空文字へ正規化する。
 * 入力: レポートとメトリクスラベル。
 * 出力: 値文字列または空文字。
 * 動作条件: report.metricsが配列であること。
 * 副作用: なし。
 */
export const metricValue = (report, label) => report.metrics.find((metric) => metric.label === label)?.value ?? '';

/**
 * 目的: UIまたはスクリプト表面に必須語が露出していることを検証する。
 * 機能: 受入条件に必要なトークンが結合文字列へ含まれるか確認する。
 * 入力: 検索対象、検証名、トークン配列。
 * 出力: なし。
 * 動作条件: loadDesignToolSourcesの結合文字列を渡すこと。
 * 副作用: 失敗時にassert例外を投げる。
 */
export function assertSurfaceTokens(source, label, tokens) {
    for (const token of tokens) {
        assert.ok(source.includes(token), `${label} should expose "${token}" before the design acceptance checklist is marked complete`);
    }
}
