import {
    test,
    setViewport,
    replaceElements,
    assertScreenshot,
    getCurrencyCodeFromLocale,
    getLanguageCode,
} from '@fixtures/AcceptanceTest';

test(
    'Visual: Product Detail Page',
    { tag: '@Visual' },
    async ({ ShopAdmin, TestDataService, AdminProductDetail, SalesChannelBaseConfig, Translate }) => {
        const labels = {
            'en-GB': {
                properties: 'Configure properties',
                layout: 'Change layout',
                crossSelling: 'Add new Cross Selling',
                reviews: 'Go to reviews',
            },
            'zh-CN': { properties: '配置属性', layout: '修改布局', crossSelling: '添加关联商品', reviews: '前往评价' },
        }[getLanguageCode()];
        const currency = await TestDataService.getCurrency(getCurrencyCodeFromLocale());

        const product = await TestDataService.createBasicProduct({
            name: 'Test Product',
            productNumber: 'TEST-123',
            description: null,
            stock: 10,
            price: [
                {
                    currencyId: currency.id,
                    gross: 10,
                    linked: false,
                    net: 8.4,
                },
                {
                    currencyId: SalesChannelBaseConfig.defaultCurrencyId,
                    gross: 10,
                    linked: false,
                    net: 8.4,
                },
            ],
        });

        await test.step('Creates a screenshot of the product detail page General tab.', async () => {
            await ShopAdmin.goesTo(AdminProductDetail.url(product.id));
            await setViewport(AdminProductDetail.page, {
                waitForSelector: AdminProductDetail.releaseDateInput,
            });
            await replaceElements(AdminProductDetail.page, [
                AdminProductDetail.productHeadline,
                AdminProductDetail.page.locator('.sw-select-selection-list__item'),
                AdminProductDetail.page.locator('.sw-category-tree-field__label-property'),
            ]);
            await assertScreenshot(AdminProductDetail.page, 'Product-Detail-General-Tab.png');
        });

        await test.step('Creates a screenshot of the product detail page Specifications tab.', async () => {
            await AdminProductDetail.specificationsTabLink.click();
            const sectionButton = AdminProductDetail.page.getByRole('button', { name: labels.properties, exact: true });
            await ShopAdmin.expects(sectionButton).toBeVisible();
            await setViewport(AdminProductDetail.page, { waitForSelector: sectionButton });
            await replaceElements(AdminProductDetail.page, [
                AdminProductDetail.productHeadline,
            ]);
            await assertScreenshot(AdminProductDetail.page, 'Product-Detail-Specifications-Tab.png');
        });

        await test.step('Creates a screenshot of the product detail page Advanced Pricing tab.', async () => {
            await AdminProductDetail.advancedPricingTabLink.click();
            await setViewport(AdminProductDetail.page, {
                waitForSelector: AdminProductDetail.page.locator('.sw-product-detail-context-prices__empty-state'),
            });
            await replaceElements(AdminProductDetail.page, [
                AdminProductDetail.productHeadline,
            ]);
            await assertScreenshot(AdminProductDetail.page, 'Product-Detail-Advanced-Pricing-Tab.png');
        });

        await test.step('Creates a screenshot of the product detail page Variants tab.', async () => {
            await AdminProductDetail.variantsTabLink.click();
            const sectionButton = AdminProductDetail.page.getByRole('button', { name: labels.properties, exact: true });
            await ShopAdmin.expects(sectionButton).toBeVisible();
            await setViewport(AdminProductDetail.page, { waitForSelector: sectionButton });
            await replaceElements(AdminProductDetail.page, [
                AdminProductDetail.productHeadline,
            ]);
            await assertScreenshot(AdminProductDetail.page, 'Product-Detail-Variants-Tab.png');
        });

        await test.step('Creates a screenshot of the product detail page Layout tab.', async () => {
            await AdminProductDetail.layoutTabLink.click();
            const sectionButton = AdminProductDetail.page.getByRole('button', { name: labels.layout, exact: true });
            await ShopAdmin.expects(sectionButton).toBeVisible();
            await setViewport(AdminProductDetail.page, { waitForSelector: sectionButton });
            await replaceElements(AdminProductDetail.page, [
                AdminProductDetail.productHeadline,
            ]);
            await assertScreenshot(AdminProductDetail.page, 'Product-Detail-Layout-Tab.png');
        });

        await test.step('Creates a screenshot of the product detail page SEO tab.', async () => {
            await AdminProductDetail.SEOTabLink.click();
            const uploadButton = AdminProductDetail.page
                .locator('.sw-product-seo-form__open-graph')
                .getByRole('button', { name: Translate('administration:product:buttons.uploadFile'), exact: true });
            await ShopAdmin.expects(uploadButton).toBeVisible();
            await setViewport(AdminProductDetail.page, { waitForSelector: uploadButton });
            await replaceElements(AdminProductDetail.page, [
                AdminProductDetail.productHeadline,
            ]);
            await assertScreenshot(AdminProductDetail.page, 'Product-Detail-SEO-Tab.png');
        });

        await test.step('Creates a screenshot of the product detail page Cross Selling tab.', async () => {
            await AdminProductDetail.crossSellingTabLink.click();
            const sectionButton = AdminProductDetail.page.getByRole('button', { name: labels.crossSelling, exact: true });
            await ShopAdmin.expects(sectionButton).toBeVisible();
            await setViewport(AdminProductDetail.page, { waitForSelector: sectionButton });
            await replaceElements(AdminProductDetail.page, [
                AdminProductDetail.productHeadline,
            ]);
            await assertScreenshot(AdminProductDetail.page, 'Product-Detail-Cross-Selling-Tab.png');
        });

        await test.step('Creates a screenshot of the product detail page Reviews tab.', async () => {
            await AdminProductDetail.reviewsTabLink.click();
            const sectionButton = AdminProductDetail.page.getByRole('button', { name: labels.reviews, exact: true });
            await ShopAdmin.expects(sectionButton).toBeVisible();
            await setViewport(AdminProductDetail.page, { waitForSelector: sectionButton });
            await replaceElements(AdminProductDetail.page, [
                AdminProductDetail.productHeadline,
            ]);
            await assertScreenshot(AdminProductDetail.page, 'Product-Detail-Reviews-Tab.png');
        });
    },
);
