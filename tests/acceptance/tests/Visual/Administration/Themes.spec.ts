import { test, assertScreenshot, setViewport, replaceElements, getLanguageCode } from '@fixtures/AcceptanceTest';

test(
    'Visual: Administration themes page',
    { tag: '@Visual' },
    async ({ ShopAdmin, AdminThemesListing, AdminThemesDetail }) => {
        await test.step('Creates a screenshot of the themes page.', async () => {
            await ShopAdmin.goesTo(AdminThemesListing.url());
            await setViewport(AdminThemesListing.page, {
                waitForSelector: AdminThemesListing.installedTheme('Shopwell default theme'),
            });
            await assertScreenshot(AdminThemesListing.page, 'Themes-Listing.png');
        });

        await test.step('Creates a screenshot of the themes page.', async () => {
            const themeColoursTitle = { 'en-GB': 'Theme colours', 'zh-CN': '主题颜色' }[getLanguageCode()];
            await AdminThemesListing.installedTheme('Shopwell default theme').click();
            await ShopAdmin.expects(AdminThemesDetail.themeCard(themeColoursTitle)).toBeVisible();
            await AdminThemesDetail.sidebarButton.click();

            const sidebarMediaItems = AdminThemesDetail.page.locator('.sw-sidebar-media-item .sw-media-media-item');
            await ShopAdmin.expects(AdminThemesDetail.page.locator('.sw-sidebar-media-item .sw-loader')).toHaveCount(0);
            await ShopAdmin.expects(sidebarMediaItems.first()).toBeVisible();

            await setViewport(AdminThemesDetail.page, {
                width: 1600,
                scrollableElementVertical: AdminThemesDetail.scrollableElement,
                waitForSelector: AdminThemesDetail.themeCard(themeColoursTitle),
            });
            await replaceElements(AdminThemesDetail.page, [
                AdminThemesDetail.page.locator('.sw-media-media-item__metadata').nth(0),
                AdminThemesDetail.page.locator('.sw-media-media-item__metadata').nth(1),
                AdminThemesDetail.page.locator('.sw-media-media-item__metadata').nth(2),
            ]);
            await assertScreenshot(AdminThemesDetail.page, 'Themes-Detail.png', AdminThemesDetail.contentView);
        });
    },
);
