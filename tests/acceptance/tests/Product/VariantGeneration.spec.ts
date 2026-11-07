import { test } from '@fixtures/AcceptanceTest';
import { PropertyGroup } from '@shopwell-ag/acceptance-test-suite';
import type { Page } from '@playwright/test';

async function applyNavigationStyle(page: Page, css: string): Promise<void> {
    if (css) {
        await page.addStyleTag({ content: css });
    }
}

const navigationCases = [
    {
        name: 'Shop administrator should be able to create product variants.',
        css: '',
        overflow: false,
    },
    {
        name: 'Shop administrator should be able to create product variants from the overflow menu.',
        css: '.sw-product-detail-page__tabs { max-width:160px !important; }',
        overflow: true,
    },
];

for (const navigation of navigationCases) {
    test(
        `${navigation.name}`,
        { tag: '@Product' },
        async ({ ShopAdmin, TestDataService, AdminProductDetail, GenerateVariants, InstanceMeta }) => {
            test.skip(
                navigation.overflow && !InstanceMeta.features['V6_8_0_0'],
                'Overflow navigation requires Meteor tabs.',
            );

            const product = await TestDataService.createBasicProduct();
            const color = await TestDataService.createColorPropertyGroup();
            const size = await TestDataService.createTextPropertyGroup();

            await applyNavigationStyle(AdminProductDetail.page, navigation.css);
            await ShopAdmin.goesTo(AdminProductDetail.url(product.id));
            await ShopAdmin.page.waitForLoadState('domcontentloaded');
            const tabs = AdminProductDetail.page.locator('.sw-product-detail-page__tabs');
            await ShopAdmin.expects(tabs).toBeVisible();
            await ShopAdmin.expects(AdminProductDetail.variantsTabLink).toBeVisible({ visible: !navigation.overflow });
            await ShopAdmin.expects(tabs.getByRole('button', { name: 'Context menu', exact: true })).toBeVisible({
                visible: navigation.overflow,
            });

            await ShopAdmin.attemptsTo(GenerateVariants(color.name, size.name));

            /**
             * The test has to handle random behaviour.
             * Variants displayed in the admin grid can have different order and naming combinations.
             */
            const variantLocators = AdminProductDetail.page.locator('.sw-product-variants-overview__variation-link');
            await ShopAdmin.expects(variantLocators).toHaveCount(4);
            const variantTexts = await variantLocators.allInnerTexts();
            const allowedVariants = [
                'RedMedium',
                'RedLarge',
                'BlueMedium',
                'BlueLarge',
                'MediumRed',
                'MediumBlue',
                'LargeRed',
                'LargeBlue',
            ];

            const validateVariants = variantTexts.every((variant) => allowedVariants.includes(variant.trim()));

            ShopAdmin.expects(validateVariants).toBeTruthy();
        },
    );
}

test(
    'Customer should be able to see a new property displayed on the product detail page',
    {
        tag: [
            '@Product',
            '@Storefront',
        ],
    },
    async ({ ShopCustomer, TestDataService, StorefrontProductDetail, CheckVisibilityInHome }) => {
        await TestDataService.setSystemConfig({ 'core.listing.disableEmptyFilterOptions': true });
        const color = await TestDataService.createColorPropertyGroup({
            name: 'Color',
            description: 'Color Description',
            options: [{ name: 'Red', colorHexCode: '#bf0f2a' }],
        });
        const propertyGroupsColor: PropertyGroup[] = [color];
        const colorManufacturer = await TestDataService.createBasicManufacturer({
            name: 'Color Manufacturer',
            description: 'Color Description Manufacturer',
        });
        const parentProductColor = await TestDataService.createBasicProduct({ manufacturerId: colorManufacturer.id });

        const variantProductColor = await TestDataService.createVariantProducts(parentProductColor, propertyGroupsColor, {
            description: 'Variant description',
        });
        await TestDataService.clearCaches();

        await test.step('Verify property display on the product detail page', async () => {
            await CheckVisibilityInHome(variantProductColor.at(0).name)();
            await ShopCustomer.goesTo(StorefrontProductDetail.url(variantProductColor.at(0)));
            await ShopCustomer.expects(StorefrontProductDetail.addToCartButton).toBeVisible();
            await ShopCustomer.expects(StorefrontProductDetail.propertyRadioGroup(color.name)).toBeVisible();
            await ShopCustomer.expects(
                StorefrontProductDetail.propertyRadioGroup(color.name).getByRole('radio'),
            ).toHaveCount(variantProductColor.length);
        });
    },
);
