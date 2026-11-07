import { expect, replaceElements } from '@shopwell-ag/acceptance-test-suite';
import type { Page } from '@shopwell-ag/acceptance-test-suite';

export async function stabilizeDashboardValues(page: Page): Promise<void> {
    const metrics = page.locator('.sw-dashboard-metrics');
    await expect(metrics).toBeVisible();
    await expect(metrics.locator('.mt-loader')).toHaveCount(0);
    const values = metrics.locator('.sw-dashboard-metrics__value');
    await expect(values).toHaveCount(6);
    await values.evaluateAll((elements) => {
        for (const element of elements) {
            const value = Array.from(element.childNodes).find((node) => node.nodeType === 3 && node.textContent?.trim());
            if (!value?.textContent || !/\d/.test(value.textContent)) {
                throw new Error('Dashboard metric has no numeric text value');
            }
            value.textContent = value.textContent.replace(/[+-]?\d[\d,. ]*/g, '***');
        }
    });
    const wallet = page.locator('.sw-dashboard-wallet');
    await expect(wallet).toBeVisible();
    await expect(wallet.locator('.mt-loader')).toHaveCount(0);
    await replaceElements(page, [
        '.sw-dashboard-metrics__delta',
        '.sw-dashboard-wallet__top-up-value',
    ]);
}
