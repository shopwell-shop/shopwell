import { test } from '@fixtures/AcceptanceTest';

test(
    'As a customer, I want to see 404 layout when I navigate to a non-existing page.',
    {
        tag: [
            '@Categories',
            '@Storefront',
        ],
    },
    async ({ ShopCustomer, StorefrontPageNotFound, StorefrontHome, Translate }) => {
        await ShopCustomer.goesTo(StorefrontPageNotFound.url());

        await ShopCustomer.expects(StorefrontPageNotFound.pageNotFoundImage).toBeVisible();
        await ShopCustomer.expects(StorefrontPageNotFound.headline).toHaveText(Translate('storefront:pageNotFound:title'));
        await ShopCustomer.expects(StorefrontPageNotFound.pageNotFoundMessage).toHaveText(
            Translate('storefront:pageNotFound:message'),
        );
        await ShopCustomer.presses(StorefrontPageNotFound.backToShopButton);
        await ShopCustomer.expects(StorefrontHome.page).toHaveURL(StorefrontHome.url());
        await ShopCustomer.expects(StorefrontHome.categoryTitle).toBeVisible();
    },
);
