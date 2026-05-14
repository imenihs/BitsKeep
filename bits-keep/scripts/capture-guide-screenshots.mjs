import { chromium } from 'playwright';
import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptDir = path.dirname(fileURLToPath(import.meta.url));
const appRoot = path.resolve(scriptDir, '..');
const outputDir = path.resolve(appRoot, 'resources/help/screenshots');

// 目的: ガイド撮影に必要な環境変数を必須化し、認証情報のソース混入を防ぐ。
// 入力: 環境変数名。出力: trim済みの環境変数値。
// 動作条件: 実行者が撮影用アカウントと対象URLを明示していること。副作用: 未設定時に例外で処理を止める。
function requiredEnv(name) {
    const value = String(process.env[name] ?? '').trim();
    if (!value) {
        throw new Error(`${name} を設定してください。ガイド撮影は公開可能なデモ/検証データで実行します。`);
    }
    return value;
}

const baseUrl = requiredEnv('BITSKEEP_URL').replace(/\/+$/, '');
const loginEmail = requiredEnv('BITSKEEP_GUIDE_EMAIL');
const loginPassword = requiredEnv('BITSKEEP_GUIDE_PASSWORD');
const allowRealDataCapture = process.env.BITSKEEP_GUIDE_ALLOW_REAL_DATA === '1';
const baseHostname = new URL(baseUrl).hostname;

if (baseHostname === 'bits-keep.rwc.0t0.jp' && !allowRealDataCapture) {
    throw new Error('共有環境を撮影する場合は BITSKEEP_GUIDE_ALLOW_REAL_DATA=1 を明示してください。公開ガイドへ載せてよいデータだけを使います。');
}

const shots = [
    ['dashboard-overview.png', 'ダッシュボード全体'],
    ['dashboard-search-zoom.png', '検索欄と検索結果'],
    ['components-list-overview.png', '部品一覧全体'],
    ['components-filter-zoom.png', '部品一覧の詳細条件'],
    ['component-detail-overview.png', '部品詳細全体'],
    ['component-stock-modal-zoom.png', '入庫モーダル'],
    ['component-create-specs-zoom.png', '部品登録のスペック入力'],
    ['component-create-ai-zoom.png', 'データシート解析補助'],
    ['component-compare-overview.png', '部品比較全体'],
    ['master-management-overview.png', 'マスタ管理全体'],
    ['design-tools-overview.png', '設計解析ツール全体'],
    ['backup-overview.png', 'データのバックアップ'],
];

// 目的: スクリーンショット出力先を空にせず安全に作る。
// 入力: 出力ディレクトリの絶対パス。出力: なし。
// 動作条件: Node.js からリポジトリ内で実行すること。副作用: resources/help/screenshots 配下へ画像ディレクトリを作成する。
async function ensureOutputDirectory() {
    await fs.mkdir(outputDir, { recursive: true });
}

// 目的: Headless Chrome へログイン済みセッションを作る。
// 入力: Playwright page。出力: なし。
// 動作条件: BitsKeep のログイン画面と撮影用アカウントが利用できること。副作用: ブラウザセッションにログイン Cookie を保存する。
async function login(page) {
    await page.goto(`${baseUrl}/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="email"]', loginEmail);
    await page.fill('input[name="password"]', loginPassword);
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }),
        page.click('button[type="submit"]'),
    ]);
}

// 目的: Vue 画面の描画完了を待つ。
// 入力: Playwright page。出力: なし。
// 動作条件: 対象ページが通常のブラウザ表示を返すこと。副作用: なし。
async function waitForAppSettled(page) {
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(650);
}

// 目的: ガイド画像に不要な個人名や認証情報を写さない。
// 入力: Playwright page。出力: なし。
// 動作条件: DOM が読み込まれていること。副作用: 画面上のテキストノードだけを撮影用に置換する。
async function maskGuideSensitiveText(page) {
    await page.evaluate(() => {
        document.querySelectorAll('.app-shell-user__text').forEach((node) => {
            node.textContent = 'ログイン: ユーザー';
        });

        const replacements = [
            [/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/gi, 'user@example.com'],
            [/ログイン[:：]\s*[^\s]+(?:\s+[^\s]+)?/g, 'ログイン: ユーザー'],
        ];
        const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
        const textNodes = [];
        while (walker.nextNode()) {
            textNodes.push(walker.currentNode);
        }
        textNodes.forEach((node) => {
            let text = node.nodeValue ?? '';
            replacements.forEach(([pattern, replacement]) => {
                text = text.replace(pattern, replacement);
            });
            node.nodeValue = text;
        });
    });
}

// 目的: 任意ページを開き、ライトテーマで安定表示させる。
// 入力: Playwright page とパス。出力: なし。
// 動作条件: ログイン済みで指定パスが存在すること。副作用: ページ遷移を行う。
async function gotoApp(page, pathname) {
    await page.goto(`${baseUrl}${pathname}`, { waitUntil: 'domcontentloaded' });
    await waitForAppSettled(page);
    await maskGuideSensitiveText(page);
}

// 目的: 現在のビューポート全体を撮影する。
// 入力: Playwright page、ファイル名。出力: なし。
// 動作条件: 出力先ディレクトリが存在すること。副作用: PNGファイルを作成または上書きする。
async function captureViewport(page, filename) {
    await page.screenshot({
        path: path.join(outputDir, filename),
        fullPage: false,
        animations: 'disabled',
    });
}

// 目的: 指定領域を少し余白付きで切り抜き撮影する。
// 入力: Playwright page、locator、ファイル名、余白px。出力: なし。
// 動作条件: locator が1件以上表示されること。副作用: PNGファイルを作成または上書きする。
async function captureAround(page, locator, filename, margin = 24) {
    await locator.first().waitFor({ state: 'visible', timeout: 10000 });
    await locator.first().scrollIntoViewIfNeeded();
    await page.waitForTimeout(200);

    const box = await locator.first().boundingBox();
    if (!box) throw new Error(`撮影対象が見つかりません: ${filename}`);

    const clip = {
        x: Math.max(0, box.x - margin),
        y: Math.max(0, box.y - margin),
        width: box.width + margin * 2,
        height: box.height + margin * 2,
    };

    await page.screenshot({
        path: path.join(outputDir, filename),
        clip,
        animations: 'disabled',
    });
}

// 目的: APIからガイド撮影に使う部品IDを取得する。
// 入力: Playwright page。出力: 部品ID配列。
// 動作条件: ログイン済みで /api/components が応答すること。副作用: なし。
async function fetchComponentIds(page) {
    const result = await page.evaluate(async () => {
        const response = await fetch('/api/components?per_page=6');
        const json = await response.json();
        const rows = json?.data?.data ?? json?.data ?? json ?? [];
        return (Array.isArray(rows) ? rows : []).map((item) => item.id).filter(Boolean);
    });
    if (result.length < 2) {
        throw new Error('比較撮影に必要な部品が2件以上ありません。');
    }
    return result;
}

// 目的: select の先頭以外の候補を選ぶ。
// 入力: select locator。出力: 選択できたかどうか。
// 動作条件: Vue の v-model と連動する select が表示されていること。副作用: 画面上の選択状態を変更する。
async function selectFirstRealOption(select) {
    await select.waitFor({ state: 'visible', timeout: 10000 });
    const values = await select.locator('option').evaluateAll((options) => (
        options.map((option) => option.value).filter((value) => value !== '')
    ));
    if (!values.length) return false;
    await select.selectOption(values[0]);
    return true;
}

// 目的: ガイド用スクリーンショット一式を取得する。
// 入力: なし。出力: なし。
// 動作条件: Headless Chrome、Playwright、BitsKeep のURLと撮影用アカウントが利用できること。副作用: resources/help/screenshots にPNGを生成する。
async function main() {
    await ensureOutputDirectory();

    const browserLaunchOptions = { headless: true };
    if (process.env.CHROME_BIN) {
        browserLaunchOptions.executablePath = process.env.CHROME_BIN;
    }

    const browser = await chromium.launch(browserLaunchOptions);
    const context = await browser.newContext({
        viewport: { width: 1440, height: 1000 },
        deviceScaleFactor: 1,
        ignoreHTTPSErrors: true,
        locale: 'ja-JP',
    });
    await context.addInitScript(() => {
        localStorage.setItem('bitskeep-theme', 'light');
    });

    const page = await context.newPage();
    page.on('dialog', async (dialog) => {
        await dialog.accept();
    });

    await page.emulateMedia({ colorScheme: 'light' });
    await login(page);

    await gotoApp(page, '/dashboard');
    await captureViewport(page, 'dashboard-overview.png');
    await page.keyboard.press('Control+K');
    const dashboardSearch = page.locator('input[placeholder*="型番"], input[placeholder*="検索"]').first();
    await dashboardSearch.fill('2SC');
    await waitForAppSettled(page);
    await captureAround(page, page.locator('#launcher-section'), 'dashboard-search-zoom.png', 20);

    await gotoApp(page, '/components');
    const componentIds = await fetchComponentIds(page);
    await captureViewport(page, 'components-list-overview.png');
    await page.getByRole('button', { name: /詳細条件を開く/ }).click();
    await page.locator('input[placeholder*="Murata"]').fill('TOSHIBA').catch(() => {});
    await waitForAppSettled(page);
    await captureAround(
        page,
        page.locator('section:has-text("部品索引")'),
        'components-filter-zoom.png',
        20,
    );

    await gotoApp(page, `/components/${componentIds[0]}`);
    await captureViewport(page, 'component-detail-overview.png');
    await page.getByRole('button', { name: /^入庫$/ }).click();
    await captureAround(page, page.locator('.modal-window:has-text("入庫")'), 'component-stock-modal-zoom.png', 36);
    await page.keyboard.press('Escape');
    await page.waitForTimeout(200);

    await gotoApp(page, '/components/create');
    const specSection = page.locator('section').filter({
        has: page.getByRole('heading', { name: 'スペック' }),
    }).first();
    await selectFirstRealOption(specSection.locator('select').nth(0));
    await selectFirstRealOption(specSection.locator('select').nth(1));
    await specSection.getByRole('button', { name: /^追加$/ }).click().catch(() => {});
    await waitForAppSettled(page);
    await captureAround(page, specSection, 'component-create-specs-zoom.png', 20);
    await captureAround(
        page,
        page.locator('section').filter({
            has: page.getByRole('heading', { name: 'データシート・画像' }),
        }).first(),
        'component-create-ai-zoom.png',
        20,
    );

    await gotoApp(page, `/component-compare?ids=${componentIds.slice(0, 3).join(',')}`);
    await captureViewport(page, 'component-compare-overview.png');

    await gotoApp(page, '/master');
    await captureViewport(page, 'master-management-overview.png');

    await gotoApp(page, '/tools/design');
    await captureViewport(page, 'design-tools-overview.png');

    await gotoApp(page, '/backup');
    await captureViewport(page, 'backup-overview.png');

    await browser.close();

    console.log('Captured help screenshots:');
    for (const [filename, label] of shots) {
        console.log(`- ${filename}: ${label}`);
    }
}

main().catch((error) => {
    console.error(error);
    process.exit(1);
});
