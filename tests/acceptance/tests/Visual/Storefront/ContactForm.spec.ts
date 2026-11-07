import { test, expect } from '@fixtures/AcceptanceTest';

test(
    'Creates a screenshot of the Storefront Homepage.',
    { tag: '@Visual' },
    async ({
        ShopCustomer,
        StorefrontAccountLogin,
        StorefrontFooter,
        StorefrontContactForm,
        TestDataService,
        Translate,
    }) => {
        await TestDataService.setSystemConfig({ 'core.basicInformation.useDefaultCookieConsent': false });

        await test.step('Creates a screenshot and compare it on homepage in storefront.', async () => {
            await ShopCustomer.goesTo(StorefrontAccountLogin.url());
            await StorefrontFooter.footerContactFormLink.click();
            await ShopCustomer.expects(StorefrontContactForm.cardTitle).toContainText(Translate('storefront:contact:title'));
            await expect(StorefrontContactForm.page).toHaveScreenshot('Contact-Form.png', {
                fullPage: true,
            });
        });
    },
);
