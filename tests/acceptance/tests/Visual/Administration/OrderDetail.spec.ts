import {
    test,
    setViewport,
    hideElements,
    replaceElements,
    assertScreenshot,
    getLanguageCode,
    getCountryAddressData,
} from '@fixtures/AcceptanceTest';

test(
    'Visual: Order Detail Page',
    { tag: '@Visual' },
    async ({ ShopAdmin, TestDataService, AdminOrderDetail, DefaultSalesChannel }) => {
        await test.step('Creates a screenshot of the order detail page General tab.', async () => {
            const product = await TestDataService.createBasicProduct();
            const order = await TestDataService.createOrder([{ product, quantity: 1 }], DefaultSalesChannel.customer);
            await ShopAdmin.goesTo(AdminOrderDetail.url(order.id));

            await ShopAdmin.expects(AdminOrderDetail.generalTabLink).toBeVisible();
            await ShopAdmin.expects(AdminOrderDetail.page.locator('.sw-order-line-items-grid__item-label')).toHaveText(
                product.name,
            );
            await setViewport(AdminOrderDetail.page);

            await replaceElements(AdminOrderDetail.page, [
                'td.sw-data-grid__cell--label .sw-order-line-items-grid__item-label',
                '.sw-order-general-info__summary-main-header',
                '.sw-order-general-info__summary-sub',
                '.smart-bar__header',
            ]);

            await assertScreenshot(AdminOrderDetail.page, 'Order-Detail-General-Tab.png');
        });

        await test.step('Creates a screenshot of the product detail page Details tab.', async () => {
            await AdminOrderDetail.detailsTabLink.click();
            await setViewport(AdminOrderDetail.page, {
                requestURL: '/api/search/custom-field-set',
                contentHeight: 2666,
            });

            const addresses = AdminOrderDetail.page.locator(
                '.sw-order-detail-details__billing-address .sw-address__formatting, ' +
                    '.sw-order-detail-details__shipping-address .sw-address__formatting',
            );
            await ShopAdmin.expects(addresses).toHaveCount(2);
            await ShopAdmin.expects(addresses).toContainText([
                DefaultSalesChannel.customer.name,
                DefaultSalesChannel.customer.name,
            ]);
            const addressSelections = AdminOrderDetail.page.locator(
                '.sw-order-detail-details__billing-address .sw-single-select__selection-text, ' +
                    '.sw-order-detail-details__shipping-address .sw-single-select__selection-text',
            );
            await ShopAdmin.expects(addressSelections).toHaveCount(2);
            await ShopAdmin.expects(addressSelections).toContainText([
                getCountryAddressData().street,
                getCountryAddressData().street,
            ]);

            await replaceElements(AdminOrderDetail.page, [
                '.sw-order-detail-details__email input',
                '.sw-order-general-info__summary-sub',
                '.sw-order-detail-details__billing-address .sw-single-select__selection-text',
                '.sw-order-detail-details__shipping-address .sw-single-select__selection-text',
            ]);

            await addresses.evaluateAll((elements, customerName) => {
                for (const element of elements) {
                    const name = Array.from(element.childNodes).find(
                        (node) => node.nodeType === 3 && node.textContent?.trim() === customerName,
                    );
                    if (!name) {
                        throw new Error('Order address has no customer name text');
                    }
                    name.textContent = '***';
                }
            }, DefaultSalesChannel.customer.name);

            const salesChannelLabel = { 'en-GB': 'Sales channel', 'zh-CN': '销售渠道' }[getLanguageCode()];
            const salesChannel = AdminOrderDetail.page.locator('.sw-entity-single-select').filter({
                has: AdminOrderDetail.page.getByText(salesChannelLabel, { exact: true }),
            });
            await ShopAdmin.expects(salesChannel).toHaveCount(1);
            await hideElements(AdminOrderDetail.page, [
                '.dp__input_reg',
                '.sw-order-detail-details__email input',
                salesChannel.locator('.sw-entity-single-select__selection-text'),
                '.dp__input_icon_pad',
            ]);

            await assertScreenshot(AdminOrderDetail.page, 'Order-Detail-Details-Tab.png');
        });

        await test.step('Creates a screenshot of the product detail page Documents tab.', async () => {
            await AdminOrderDetail.documentsTabLink.click();
            await setViewport(AdminOrderDetail.page, {
                requestURL: '/api/search/document',
                contentHeight: 1080,
            });
            await assertScreenshot(AdminOrderDetail.page, 'Order-Detail-Documents-Tab.png');
        });
    },
);
