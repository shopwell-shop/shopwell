import {
    test,
    assertScreenshot,
    setViewport,
    hideElements,
    replaceElements,
    getLanguageCode,
} from '@fixtures/AcceptanceTest';

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
            const channelName = AdminSalesChannelDetail.page.locator('.smart-bar__header h2');
            const nameInput = AdminSalesChannelDetail.page.locator('.sw-field--salesChannel-name input');
            const navigationCategory = AdminSalesChannelDetail.page.locator(
                '.sw-sales-channel-detail__select-navigation-category-id .sw-category-tree-field__label-property',
            );
            const customerGroup = AdminSalesChannelDetail.page.locator(
                '.sw-sales-channel-detail__select-customer-group .sw-entity-single-select__selection-text',
            );
            await ShopAdmin.expects(channelName).toHaveText(DefaultSalesChannel.salesChannel.name);
            await ShopAdmin.expects(nameInput).toHaveValue(DefaultSalesChannel.salesChannel.name);
            for (const fixtureLabel of [navigationCategory, customerGroup]) {
                await ShopAdmin.expects(fixtureLabel).toHaveCount(1);
                await ShopAdmin.expects(fixtureLabel).toBeVisible();
                await ShopAdmin.expects(fixtureLabel).toHaveText(/^\d+ Acceptance test$/);
            }
            await replaceElements(AdminSalesChannelDetail.page, [
                channelName,
                navigationCategory,
                customerGroup,
                nameInput,
            ]);
            const accessKeyLabel = { 'en-GB': 'API access key', 'zh-CN': 'API 访问密钥' }[getLanguageCode()];
            const accessKey = AdminSalesChannelDetail.page.getByRole('textbox', { name: accessKeyLabel, exact: true });
            await ShopAdmin.expects(accessKey).toHaveCount(1);
            await ShopAdmin.expects(accessKey).toBeVisible();
            await ShopAdmin.expects(accessKey).toHaveValue(DefaultSalesChannel.salesChannel.accessKey);
            await hideElements(AdminSalesChannelDetail.page, [
                'td.sw-data-grid__cell--url .sw-data-grid__cell-content',
                accessKey,
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
            const analyticsCard = AdminSalesChannelDetail.page.locator('.sw-sales-channel-detail-analytics__headline-text');
            await ShopAdmin.expects(analyticsCard).toBeVisible();
            await setViewport(AdminSalesChannelDetail.page, { waitForSelector: analyticsCard });
            await assertScreenshot(AdminSalesChannelDetail.page, 'Sales-Channel-Analytics-Tab.png');
        });
    },
);
