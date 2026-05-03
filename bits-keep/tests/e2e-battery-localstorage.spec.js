import { test, expect } from '@playwright/test';

test('battery page with mixed localStorage', async ({ page }) => {
  await page.goto('http://127.0.0.1:8000/login');
  await page.fill('input[name="email"]', 'imenihs@gmail.com');
  await page.fill('input[name="password"]', 'lA4sdBnnJuV2qBwJ');
  await Promise.all([
    page.waitForURL('**/dashboard', { timeout: 15000 }),
    page.click('button[type="submit"]'),
  ]);

  const logs = [];
  page.on('console', msg => logs.push(`[${msg.type()}] ${msg.text()}`));
  page.on('pageerror', err => logs.push(`[pageerror] ${err}`));

  await page.addInitScript(() => {
    const bad = {
      'bitskeep.designTools.toolOrder.v1': JSON.stringify(['battery-runtime', 'network-search', 'battery-runtime']),
      'bitskeep.designTools.lastTool.v1': 'battery-runtime',
      'bitskeep.designTools.toolGroup.v1': 'margin',
    };
    for (const [k,v] of Object.entries(bad)) {
      localStorage.setItem(k, v);
    }
  });

  await page.goto('http://127.0.0.1:8000/tools/design?tool=battery-runtime', { waitUntil: 'networkidle' });
  await page.waitForTimeout(1000);
  await page.screenshot({ path: '/tmp/battery-runtime-storage-debug.png', fullPage: true });
  const h2 = page.locator('h2:has-text("バッテリー稼働")');
  await expect(h2.first()).toBeVisible({ timeout: 15000 });
  console.log('LOGS:\n' + logs.join('\n'));
});
