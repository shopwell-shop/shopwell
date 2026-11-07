import { test as base } from '@playwright/test';
import type { FixtureTypes, Task } from '@fixtures/AcceptanceTest';

export const GenerateVariants = base.extend<{ GenerateVariants: Task }, FixtureTypes>({
    GenerateVariants: async ({ Translate, ShopAdmin, AdminProductDetail }, use) => {
        const task = (colorProperty: string, sizeProperty: string) => {
            return async function GenerateVariants() {
                // Navigate to variants tab
                const moreTabs = AdminProductDetail.page
                    .locator('.sw-product-detail-page__tabs')
                    .getByRole('button', { name: 'More', exact: true });
                await ShopAdmin.expects(AdminProductDetail.variantsTabLink.or(moreTabs).first()).toBeVisible();
                if (!(await AdminProductDetail.variantsTabLink.isVisible())) {
                    await moreTabs.click();
                }
                await AdminProductDetail.variantsTabLink.click();
                await AdminProductDetail.generateVariantsButton.click();
                await ShopAdmin.expects(AdminProductDetail.variantsModalHeadline).toBeVisible();

                // Select color properties
                await AdminProductDetail.propertyName(colorProperty).click();

                await AdminProductDetail.propertyValueCheckbox('Blue').check();
                await ShopAdmin.expects(AdminProductDetail.propertyValueCheckbox('Blue')).toBeChecked();

                await AdminProductDetail.propertyValueCheckbox('Red').check();
                await ShopAdmin.expects(AdminProductDetail.propertyValueCheckbox('Red')).toBeChecked();

                // Select size properties
                await AdminProductDetail.propertyName(sizeProperty).click();

                await AdminProductDetail.propertyValueCheckbox('Medium').check();
                await ShopAdmin.expects(AdminProductDetail.propertyValueCheckbox('Medium')).toBeChecked();

                await AdminProductDetail.propertyValueCheckbox('Large').check();
                await ShopAdmin.expects(AdminProductDetail.propertyValueCheckbox('Large')).toBeChecked();

                // Proceed to generate variants
                await AdminProductDetail.variantsNextButton.click();
                const summary = AdminProductDetail.variantsModal.locator(
                    '.sw-product-modal-variant-generation__infoBoxContent',
                );
                await ShopAdmin.expects(summary).toBeVisible();
                await ShopAdmin.expects(summary.locator('.sw-product-modal-variant-generation__variant-amount')).toHaveText([
                    /^4\b/,
                    /^0\b/,
                ]);
                await ShopAdmin.expects(summary).toContainText(Translate('administration:product:variants.added'));
                await ShopAdmin.expects(summary).toContainText(Translate('administration:product:variants.deleted'));

                // Save variants
                await AdminProductDetail.variantsSaveButton.click();
                await ShopAdmin.expects(AdminProductDetail.variantsModal).not.toBeVisible({ timeout: 30000 });
            };
        };

        await use(task);
    },
});
