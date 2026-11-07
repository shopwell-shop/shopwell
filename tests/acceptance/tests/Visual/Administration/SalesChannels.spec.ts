import { test, assertScreenshot, setViewport, hideElements, replaceElements } from '@fixtures/AcceptanceTest';

test(
    'Visual: Administration sales channels page',
    {
        tag: '@Visual',
    },
    async ({ ShopAdmin, AdminSalesChannelDetail, DefaultSalesChannel }) => {
        await test.step('Creates a screenshot of the sales channel page general tab.', async () => {
            await ShopAdmin.goesTo(AdminSalesChannelDetail.url(DefaultSalesChannel.salesChannel.id));
            await setViewport(AdminSalesChannelDetail.page, {
                waitForSelector: '.sw-sales-channel-detail-base',
            });
            await replaceElements(AdminSalesChannelDetail.page, [
                AdminSalesChannelDetail.page.getByText(/^\d+ [Aa]cceptance test$/),
                '.sw-field--salesChannel-name input',
            ]);
            await hideElements(AdminSalesChannelDetail.page, [
                'td.sw-data-grid__cell--url .sw-data-grid__cell-content',
                '[position-identifier="sw-sales-channel-detail-base-options-api"] input',
            ]);
            await assertScreenshot(AdminSalesChannelDetail.page, 'Sales-Channel-General-Tab.png');
        });

        await test.step('Creates a screenshot of the sales channel page products tab.', async () => {
            await AdminSalesChannelDetail.productsTabLink.click();
            const productsCard = AdminSalesChannelDetail.page.locator('.sw-sales-channel-detail-products');
            await ShopAdmin.expects(productsCard).toBeVisible();
            await setViewport(AdminSalesChannelDetail.page, { waitForSelector: productsCard });
            await assertScreenshot(AdminSalesChannelDetail.page, 'Sales-Channel-Products-Tab.png');
        });

        await test.step('Creates a screenshot of the sales channel page theme tab.', async () => {
            await AdminSalesChannelDetail.themeTabLink.click();
            const themeCard = AdminSalesChannelDetail.page.locator('.sw-sales-channel-detail-theme');
            await ShopAdmin.expects(themeCard).toBeVisible();
            await setViewport(AdminSalesChannelDetail.page, { waitForSelector: themeCard });
            await assertScreenshot(AdminSalesChannelDetail.page, 'Sales-Channel-Theme-Tab.png');
        });

        await test.step('Creates a screenshot of the sales channel page analytics tab.', async () => {
            await AdminSalesChannelDetail.analyticsTabLink.click();
            const analyticsCard = AdminSalesChannelDetail.page.locator(
                '[position-identifier="sw-sales-channel-detail-analytics"]',
            );
            await ShopAdmin.expects(analyticsCard).toBeVisible();
            await setViewport(AdminSalesChannelDetail.page, { waitForSelector: analyticsCard });
            await assertScreenshot(AdminSalesChannelDetail.page, 'Sales-Channel-Analytics-Tab.png');
        });
    },
);
