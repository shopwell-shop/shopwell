import { getLanguageData, getSnippetSetId, test } from '@fixtures/AcceptanceTest';

test(
    'Shop customers should be able to view products in different languages.',
    {
        tag: [
            '@Languages',
            '@Storefront',
        ],
    },
    async ({ ShopCustomer, TestDataService, StorefrontHome }) => {
        const product = await TestDataService.createBasicProduct();

        const salesChannelId = TestDataService.defaultSalesChannel.id;
        const language = await getLanguageData(TestDataService.AdminApiClient, 'zh-CN');
        const englishLanguage = await getLanguageData(TestDataService.AdminApiClient, 'en-GB');
        const snippetSetId = await getSnippetSetId(TestDataService.AdminApiClient, 'zh-CN');
        const appUrl = (process.env.APP_URL || 'http://localhost:8000').replace(/\/$/, '');
        const zhCnDomainUrl = `${appUrl}/zh-CN-${salesChannelId}/`;
        const englishDomainUrl = `${appUrl}/en-GB-${salesChannelId}/`;

        const languagesResponse = await TestDataService.AdminApiClient.get(`sales-channel/${salesChannelId}/languages`);
        await ShopCustomer.expects(languagesResponse.ok()).toBeTruthy();
        const { data: assignedLanguages } = (await languagesResponse.json()) as { data: { id: string }[] };
        for (const requiredLanguage of [language, englishLanguage]) {
            if (!assignedLanguages.some((assignedLanguage) => assignedLanguage.id === requiredLanguage.id)) {
                await TestDataService.assignSalesChannelLanguage(salesChannelId, requiredLanguage.id);
            }
        }
        await TestDataService.createSalesChannelDomain({
            languageId: englishLanguage.id,
            snippetSetId: await getSnippetSetId(TestDataService.AdminApiClient, 'en-GB'),
            url: englishDomainUrl,
        });
        await TestDataService.createSalesChannelDomain({
            languageId: language.id,
            snippetSetId: snippetSetId,
            url: zhCnDomainUrl,
        });

        await TestDataService.clearCaches();

        const productListing = StorefrontHome.productListItems.filter({
            has: StorefrontHome.page.getByRole('link', { name: product.name }),
        });
        const addToCartButton = productListing.filter({ has: StorefrontHome.page.getByRole('button') });
        const languageDropdown = StorefrontHome.page.locator('#languagesDropdown-top-bar');

        await ShopCustomer.expects(async () => {
            await test.step('Customer can view languages menu', async () => {
                await ShopCustomer.goesTo(zhCnDomainUrl);
                await ShopCustomer.expects(languageDropdown).toContainText('简体中文');
                await ShopCustomer.expects(addToCartButton).toContainText('加入购物车');
            });
        }).toPass({
            intervals: [
                1_000,
                2_500,
            ], // retry after 1 seconds, then every 2.5 seconds
        });

        await test.step('Customer can select a different language', async () => {
            await ShopCustomer.presses(languageDropdown);
            const englishLanguageButton = StorefrontHome.page.locator(`#top-bar-${englishLanguage.id}`);
            await ShopCustomer.presses(englishLanguageButton);

            await ShopCustomer.expects(languageDropdown).toContainText('English');
            await ShopCustomer.expects(addToCartButton).toContainText('Add to shopping cart');
        });
    },
);
