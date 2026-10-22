import { expect, getCountryAddressData, mergeTests, test as ShopwellTestSuite } from '@shopwell-ag/acceptance-test-suite';
import { test as shopAdminTasks } from '@tasks/ShopAdminTasks';
import { test as shopCustomerTasks } from '@tasks/ShopCustomerTasks';
import { test as HomeProducts } from './HomeProducts';
import { test as VisualTestSetup } from './VisualTestSetup';

export * from '@shopwell-ag/acceptance-test-suite';

const mergedTest = mergeTests(ShopwellTestSuite, shopCustomerTasks, shopAdminTasks, HomeProducts, VisualTestSetup);

/**
 * The upstream `DefaultSalesChannel` fixture creates its customer with a `salutationId`. Salutation does
 * not exist in this fork any more - the personal form stores the technical name of a `gender` dictionary
 * entry in a plain string column - so the admin api rejects that write and every test using the fixture
 * fails in its setup with `Cannot read properties of undefined (reading '0')`.
 *
 * The fixture is overridden here instead of patched in `node_modules`: the package is a published
 * dependency, so a local patch would be gone after the next `npm install`. Everything except the
 * customer payload is unchanged from `@shopwell-ag/acceptance-test-suite@13.0.1`. Drop this override as
 * soon as upstream ships a version that writes `gender` instead of `salutationId`.
 */
export const test = mergedTest.extend({
    // a fixture that already exists keeps its scope and options, so only the body is provided here
    DefaultSalesChannel: async ({ IdProvider, AdminApiContext, SalesChannelBaseConfig }, use) => {
        const { id, uuid } = IdProvider.getWorkerDerivedStableId('salesChannel');
        const { uuid: rootCategoryUuid } = IdProvider.getWorkerDerivedStableId('category');
        const { uuid: customerGroupUuid } = IdProvider.getWorkerDerivedStableId('customerGroup');
        const { uuid: domainUuid } = IdProvider.getWorkerDerivedStableId('domain');
        const { uuid: customerUuid } = IdProvider.getWorkerDerivedStableId('customer');
        const baseUrl = `${SalesChannelBaseConfig.appUrl}test-${uuid}/`;

        await AdminApiContext.delete(`./customer/${customerUuid}`);

        const wantedLanguages = new Set([
            SalesChannelBaseConfig.currentLanguageId,
            SalesChannelBaseConfig.defaultLanguageId,
        ]);
        const languages: { id: string }[] = [];

        const result = await AdminApiContext.get(`./sales-channel/${uuid}/languages`);
        if (result.ok()) {
            const salesChannelLanguages = await result.json();
            wantedLanguages.forEach((languageId) => {
                if (!salesChannelLanguages.data.find((language: { id: string }) => language.id === languageId)) {
                    languages.push({ id: languageId });
                }
            });
        } else {
            wantedLanguages.forEach((languageId) => {
                languages.push({ id: languageId });
            });
        }

        const syncResp = await AdminApiContext.post('./_action/sync', {
            data: {
                'write-sales-channel': {
                    entity: 'sales_channel',
                    action: 'upsert',
                    payload: [
                        {
                            id: uuid,
                            name: `${id} acceptance test`,
                            typeId: SalesChannelBaseConfig.storefrontTypeId,
                            languageId: SalesChannelBaseConfig.currentLanguageId,
                            currencyId: SalesChannelBaseConfig.currentCurrencyId,
                            paymentMethodId: SalesChannelBaseConfig.invoicePaymentMethodId,
                            shippingMethodId: SalesChannelBaseConfig.defaultShippingMethod,
                            countryId: SalesChannelBaseConfig.currentCountryId,
                            accessKey: 'SWSC' + uuid,
                            homeEnabled: true,
                            navigationCategory: {
                                id: rootCategoryUuid,
                                name: `${id} Acceptance test`,
                                displayNestedProducts: true,
                                type: 'page',
                                productAssignmentType: 'product',
                            },
                            domains: [
                                {
                                    id: domainUuid,
                                    url: baseUrl,
                                    languageId: SalesChannelBaseConfig.currentLanguageId,
                                    snippetSetId: SalesChannelBaseConfig.currentSnippetSetId,
                                    currencyId: SalesChannelBaseConfig.currentCurrencyId,
                                },
                            ],
                            customerGroup: {
                                id: customerGroupUuid,
                                name: `${id} Acceptance test`,
                            },
                            languages,
                            countries: [{ id: SalesChannelBaseConfig.currentCountryId }],
                            shippingMethods: [{ id: SalesChannelBaseConfig.defaultShippingMethod }],
                            paymentMethods: [{ id: SalesChannelBaseConfig.invoicePaymentMethodId }],
                            currencies: [{ id: SalesChannelBaseConfig.currentCurrencyId }],
                        },
                    ],
                },
            },
        });
        expect(syncResp.ok()).toBeTruthy();

        const salesChannelPromise = AdminApiContext.get(`./sales-channel/${uuid}`);
        const addressData = getCountryAddressData();
        const customerData = {
            id: customerUuid,
            email: `customer_${id}@example.com`,
            password: 'shopwell',
            languageId: SalesChannelBaseConfig.currentLanguageId,
            defaultShippingAddress: {
                name: `${id} admin`,
                city: addressData.city,
                street: addressData.street,
                zipcode: addressData.postalCode,
                countryId: SalesChannelBaseConfig.currentCountryId,
            },
            defaultBillingAddress: {
                name: `${id} admin`,
                city: addressData.city,
                street: addressData.street,
                zipcode: addressData.postalCode,
                countryId: SalesChannelBaseConfig.currentCountryId,
            },
            name: `${id} admin`,
            salesChannelId: uuid,
            groupId: customerGroupUuid,
            customerNumber: `${customerUuid}`,
            defaultPaymentMethodId: SalesChannelBaseConfig.invoicePaymentMethodId,
        };

        const customerRespPromise = AdminApiContext.post('./customer?_response=detail', {
            data: customerData,
        });

        const [customerResp, salesChannelResp] = await Promise.all([customerRespPromise, salesChannelPromise]);
        expect(customerResp.ok()).toBeTruthy();
        expect(salesChannelResp.ok()).toBeTruthy();

        const customer = await customerResp.json();
        const salesChannel = await salesChannelResp.json();

        await use({
            salesChannel: salesChannel.data,
            customer: { ...customer.data, password: customerData.password },
            url: baseUrl,
        });
    },
});
