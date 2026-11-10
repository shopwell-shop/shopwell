import { stabilizeDashboardValues } from '@helpers/visual-dashboard-helpers';
import { test, hideElements, setViewport, assertScreenshot } from '@fixtures/AcceptanceTest';

test('Administration dashboard', { tag: '@Visual' }, async ({ ShopAdmin, AdminDashboard }) => {
    await test.step('Creates a screenshot of the Administration dashboard.', async () => {
        await ShopAdmin.goesTo(AdminDashboard.url());
        await setViewport(AdminDashboard.page, {
            contentHeight: 1180,
        });
        await hideElements(AdminDashboard.page, ['.sw-dashboard-statistics__chart']);
        await stabilizeDashboardValues(AdminDashboard.page);
        await assertScreenshot(AdminDashboard.page, 'Dashboard.png');
    });
});
