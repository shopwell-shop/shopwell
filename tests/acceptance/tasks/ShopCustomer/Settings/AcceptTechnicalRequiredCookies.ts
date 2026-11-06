import type { FixtureTypes } from '@fixtures/AcceptanceTest';
import { test as base } from '@playwright/test';

interface AcceptTechnicalRequiredCookies {
    acceptTechnicalRequiredCookies: () => Promise<void>;
}

export const AcceptTechnicalRequiredCookies = base.extend<AcceptTechnicalRequiredCookies, FixtureTypes>({
    acceptTechnicalRequiredCookies: async ({ Translate, StorefrontHome, ShopCustomer }, use) => {
        const acceptTechnicalRequiredCookies = async () => {
            const cookiePermissionButton = StorefrontHome.page.getByRole('button', {
                name: Translate('storefront:home:consent.onlyTechnicallyRequired'),
            });
            await ShopCustomer.expects(cookiePermissionButton).toBeVisible();
            await ShopCustomer.presses(cookiePermissionButton);
            await ShopCustomer.expects(
                StorefrontHome.page.getByRole('region', { name: Translate('storefront:home:consent.cookiePreferences') }),
            ).not.toBeVisible();
        };
        await use(acceptTechnicalRequiredCookies);
    },
});
