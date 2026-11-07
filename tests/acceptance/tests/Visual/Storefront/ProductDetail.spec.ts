import { test, expect, getLanguageCode } from '@fixtures/AcceptanceTest';

test(
    'Visual: Storefront Product Detail Page',
    { tag: '@Visual' },
    async ({ ShopCustomer, TestDataService, StorefrontProductDetail, CheckVisibilityInHome }) => {
        const product = await TestDataService.createBasicProduct({
            name: 'TestProduct',
            productNumber: '123',
        });
        await TestDataService.setSystemConfig({ 'core.basicInformation.useDefaultCookieConsent': false });

        await test.step('Creates a screenshot of the product detail page General tab.', async () => {
            await CheckVisibilityInHome(product.name);
            await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
            await ShopCustomer.expects(StorefrontProductDetail.addToCartButton).toBeVisible();
            await expect(StorefrontProductDetail.page).toHaveScreenshot('Product-Detail-Page.png', {
                fullPage: true,
            });
        });
    },
);

test(
    'Visual: Storefront Product Detail Review',
    { tag: '@Visual' },
    async ({ ShopCustomer, TestDataService, StorefrontProductDetail, CheckVisibilityInHome }) => {
        const product = await TestDataService.createBasicProduct({
            name: 'TestProduct',
            productNumber: '123',
        });

        await TestDataService.createProductReview(product.id, {
            title: 'Very Good',
            points: 4,
            createdAt: '2025-01-01T12:00:00.213+00:00',
        });
        await TestDataService.createProductReview(product.id, {
            title: 'Excellent',
            points: 5,
            createdAt: '2025-01-02T13:00:00.213+00:00',
        });
        await TestDataService.setSystemConfig({ 'core.basicInformation.useDefaultCookieConsent': false });

        await test.step('Creates a screenshot of the product detail page reviews tab.', async () => {
            await CheckVisibilityInHome(product.name);
            await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
            await StorefrontProductDetail.reviewsTab.click();

            await ShopCustomer.expects(StorefrontProductDetail.productReviewRating).toBeVisible();
            const reviewLabels = {
                'en-GB': { link: '2 Reviews', counter: '2 reviews' },
                'zh-CN': { link: '2 条评价', counter: '2 条评价' },
            }[getLanguageCode()];
            await ShopCustomer.expects(StorefrontProductDetail.productReviewsLink).toHaveText(reviewLabels.link);
            await ShopCustomer.expects(StorefrontProductDetail.reviewCounter).toHaveText(reviewLabels.counter);
            await ShopCustomer.expects(StorefrontProductDetail.reviewListingItems).toHaveCount(2);

            await expect(StorefrontProductDetail.page).toHaveScreenshot('Product-Detail-Review.png', {
                fullPage: true,
            });
        });
    },
);
