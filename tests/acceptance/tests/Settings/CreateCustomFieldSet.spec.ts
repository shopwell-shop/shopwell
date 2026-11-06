import { test, expect, getLanguageCode } from '@fixtures/AcceptanceTest';

test(
    'As a merchant, I want to be able to create and assign custom fields to different entities and be able to use them there exclusively.',
    { tag: '@Settings' },
    async ({
        ShopAdmin,
        AdminOrderDetail,
        AdminCategoryDetail,
        AdminProductDetail,
        AdminRuleCreate,
        AdminCustomerDetail,
        AdminCustomFieldCreate,
        AdminCustomFieldDetail,
        AdminCustomFieldListing,
        TestDataService,
        IdProvider,
        DefaultSalesChannel,
        CreateCustomField,
    }) => {
        const labels = {
            'en-GB': {
                products: 'Products',
                categories: 'Categories',
                orders: 'Orders',
                rule: 'Item with custom field',
                unavailable: 'This custom field is currently not available in shopping carts.',
            },
            'zh-CN': {
                products: '商品',
                categories: '分类',
                orders: '订单',
                rule: '带自定义字段的项目',
                unavailable: '该自定义字段目前在购物车中不可用。',
            },
        }[getLanguageCode()];
        const product = await TestDataService.createBasicProduct();
        const customer = await TestDataService.createCustomer();
        const order = await TestDataService.createOrder([{ product: product, quantity: 5 }], customer);
        const customFieldSetName = `custom_field_set_${IdProvider.getIdPair().id}`;
        const customFieldTextName = `custom_field_set_text_${IdProvider.getIdPair().id}`;
        const customFieldNumberName = `custom_field_set_number_${IdProvider.getIdPair().id}`;

        await ShopAdmin.goesTo(AdminCustomFieldCreate.url());
        await AdminCustomFieldCreate.technicalNameInput.fill(customFieldSetName);
        await AdminCustomFieldCreate.positionInput.fill('1');
        await AdminCustomFieldCreate.assignToSelectionList.click();
        await AdminCustomFieldCreate.resultAssignToPopoverItemList.getByText(labels.products, { exact: true }).click();
        await AdminCustomFieldCreate.resultAssignToPopoverItemList.getByText(labels.categories, { exact: true }).click();
        await AdminCustomFieldCreate.resultAssignToPopoverItemList.getByText(labels.orders, { exact: true }).click();

        const responsePromise = AdminCustomFieldCreate.page.waitForResponse('**/api/search/custom-field-set');
        await AdminCustomFieldCreate.saveButton.click();
        let customFieldSetResponse = await responsePromise;
        expect(customFieldSetResponse).toBeTruthy();

        await ShopAdmin.attemptsTo(CreateCustomField(customFieldTextName, 'Text field'));
        await ShopAdmin.attemptsTo(CreateCustomField(customFieldNumberName, 'Number field'));

        let customFields = await AdminCustomFieldDetail.getLineItemByCustomFieldName(customFieldTextName);
        await ShopAdmin.expects(customFields.customFieldLabelText).toBeVisible();
        await customFields.customFieldLabelText.click();
        await AdminCustomFieldDetail.customFieldEditAvailableInShoppingCartCheckbox.click();
        await AdminCustomFieldDetail.customFieldEditApplyButton.click();
        customFieldSetResponse = await responsePromise;
        expect(customFieldSetResponse).toBeTruthy();

        const url = AdminCustomFieldDetail.page.url();
        const customFieldSetUuid = url.split('/')[url.split('/').length - 1];
        TestDataService.addCreatedRecord('custom_field_set', customFieldSetUuid);

        await test.step('Validate the availability of custom fields in the custom field listing.', async () => {
            await ShopAdmin.goesTo(AdminCustomFieldListing.url());
            const customFieldSetLineItemName =
                await AdminCustomFieldListing.getLineItemByCustomFieldSetName(customFieldSetName);
            await ShopAdmin.expects(customFieldSetLineItemName.customFieldSetNameText).toHaveText(customFieldSetName);
        });

        await test.step('Validate the availability of the custom fields on an order detail page.', async () => {
            await ShopAdmin.goesTo(AdminOrderDetail.url(order.id, 'details'));
            customFields = await AdminOrderDetail.getCustomFieldCardLocators(customFieldSetName, customFieldTextName);
            await ShopAdmin.expects(customFields.customFieldCard).toBeVisible();
            await customFields.customFieldSetTab.click();
            await ShopAdmin.expects(customFields.customFieldLabel).toBeVisible();
            customFields = await AdminOrderDetail.getCustomFieldCardLocators(customFieldSetName, customFieldNumberName);
            await ShopAdmin.expects(customFields.customFieldLabel).toBeVisible();
        });

        await test.step('Validate the availability of custom fields on a category detail page.', async () => {
            await ShopAdmin.goesTo(AdminCategoryDetail.url(DefaultSalesChannel.salesChannel.navigationCategoryId));
            customFields = await AdminCategoryDetail.getCustomFieldCardLocators(customFieldSetName, customFieldTextName);
            await ShopAdmin.expects(customFields.customFieldCard).toBeVisible();
            await ShopAdmin.expects(customFields.customFieldSetTab).toBeVisible();
            await ShopAdmin.expects(customFields.customFieldSetTab).toHaveText(customFieldSetName);
            await customFields.customFieldSetTab.click();
            await ShopAdmin.expects(customFields.customFieldSelect).toBeVisible();
            await ShopAdmin.expects(customFields.customFieldSelect.getByLabel(customFieldTextName)).toBeVisible();
            await ShopAdmin.expects(customFields.customFieldSelect.getByLabel(customFieldNumberName)).toBeVisible();
        });

        await test.step('Validate the availability of custom fields on a product detail page.', async () => {
            await ShopAdmin.goesTo(AdminProductDetail.url(product.id));
            await AdminProductDetail.specificationsTabLink.click();
            customFields = await AdminProductDetail.getCustomFieldCardLocators(customFieldSetName, customFieldTextName);
            await ShopAdmin.expects(customFields.customFieldCard).toBeVisible();
            await ShopAdmin.expects(customFields.customFieldSetTab).toBeVisible();
            await ShopAdmin.expects(customFields.customFieldSetTab).toHaveText(customFieldSetName);
            await customFields.customFieldSetTab.click();
            await ShopAdmin.expects(customFields.customFieldSelect).toBeVisible();
            await ShopAdmin.expects(customFields.customFieldSelect.getByLabel(customFieldTextName)).toBeVisible();
            await ShopAdmin.expects(customFields.customFieldSelect.getByLabel(customFieldNumberName)).toBeVisible();
        });

        await test.step('Validate the availability of one custom field on a rule builder page.', async () => {
            await ShopAdmin.goesTo(AdminRuleCreate.url());
            await AdminRuleCreate.conditionTypeSelectionInput.click();
            await AdminRuleCreate.filtersResultPopoverSelectionList.filter({ hasText: labels.rule }).click();
            await AdminRuleCreate.conditionValueSelectionInput.click();
            await AdminRuleCreate.filtersResultPopoverSelectionList.getByText(customFieldTextName).hover();
            await ShopAdmin.expects(
                AdminRuleCreate.filtersResultPopoverSelectionList.getByText(customFieldTextName),
            ).not.toHaveClass(/.*is--disabled.*/);
            await ShopAdmin.expects(AdminRuleCreate.valueNotAvailableTooltip).not.toBeVisible();
            await AdminRuleCreate.filtersResultPopoverSelectionList.getByText(customFieldNumberName).hover();
            await ShopAdmin.expects(
                AdminRuleCreate.filtersResultPopoverSelectionList.filter({ hasText: customFieldNumberName }),
            ).toHaveClass(/.*is--disabled.*/);
            await ShopAdmin.expects(AdminRuleCreate.valueNotAvailableTooltip).toContainText(labels.unavailable);
        });

        await test.step('Validate the unavailability of the custom field on a customer detail page.', async () => {
            await ShopAdmin.goesTo(AdminCustomerDetail.url(customer.id), true);
            await ShopAdmin.expects(AdminCustomerDetail.customFieldCard).not.toBeVisible();
        });
    },
);
