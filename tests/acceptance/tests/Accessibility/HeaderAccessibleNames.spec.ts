import { test } from '@fixtures/AcceptanceTest';

test(
    'The empty header cart has an accessible name.',
    { tag: '@Accessibility' },
    async ({ ShopCustomer, StorefrontHome }) => {
        await ShopCustomer.goesTo(StorefrontHome.url());

        await ShopCustomer.expects(StorefrontHome.page.locator('button.sw-cart-widget')).toHaveAccessibleName(/\S/);
    },
);
