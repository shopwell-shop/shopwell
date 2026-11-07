import { test, setViewport, replaceElements, hideElements, assertScreenshot, FlowConfig } from '@fixtures/AcceptanceTest';

test('Visual: Flow Builder listing', { tag: '@Visual' }, async ({ ShopAdmin, AdminFlowBuilderListing }) => {
    await test.step('Create a screenshot of the flow listing.', async () => {
        await ShopAdmin.goesTo(AdminFlowBuilderListing.url());
        await ShopAdmin.expects(AdminFlowBuilderListing.firstFlowName).toBeVisible();
        await setViewport(AdminFlowBuilderListing.page, {
            waitForSelector: AdminFlowBuilderListing.createFlowButton,
        });
        await replaceElements(AdminFlowBuilderListing.page, [AdminFlowBuilderListing.testFlowNameCells]);
        await AdminFlowBuilderListing.flowTemplatesTab.hover();
        await assertScreenshot(AdminFlowBuilderListing.page, 'Flow-Builder-Listing-Hover.png');
    });

    await test.step('Create a screenshot of the flow templates listing.', async () => {
        await AdminFlowBuilderListing.flowTemplatesTab.click();
        await setViewport(AdminFlowBuilderListing.page, {
            waitForSelector: AdminFlowBuilderListing.pagination,
        });
        await assertScreenshot(AdminFlowBuilderListing.page, 'Flow-Builder-Templates-Listing-Hover.png');
    });
});

test(
    'Visual: Flow Builder detail page',
    { tag: '@Visual' },
    async ({
        ShopAdmin,
        TestDataService,
        IdProvider,
        AdminFlowBuilderListing,
        AdminFlowBuilderDetail,
        CreateFlow,
        AdminApiContext,
        Translate,
    }) => {
        const uniqueId = IdProvider.getIdPair().uuid;
        const tagName = `Test tag - ${uniqueId}`;
        const flowName = `Test flow - ${uniqueId}`;
        await TestDataService.createTag(tagName);
        const mailTemplatesResponse = await AdminApiContext.post('search/mail-template', {
            data: {
                limit: 2,
                filter: [{ type: 'equals', field: 'mailTemplateType.technicalName', value: 'order_confirmation_mail' }],
                associations: { mailTemplateType: {} },
            },
        });
        await ShopAdmin.expects(mailTemplatesResponse.ok()).toBeTruthy();
        const { data: mailTemplates } = await mailTemplatesResponse.json();
        await ShopAdmin.expects(mailTemplates).toHaveLength(1);
        const mailTemplateName = mailTemplates[0].mailTemplateType.translated.name;
        await ShopAdmin.expects(mailTemplateName).toBeTruthy();
        const testConfig = {
            name: flowName,
            description: 'This flow is being created to test the creation of flows.',
            priority: '1',
            active: true,
            triggerSearchTerm: Translate('administration:flowBuilder:triggers.placed'),
            triggerLabel: [
                Translate('administration:flowBuilder:triggers.checkout'),
                Translate('administration:flowBuilder:triggers.order'),
                Translate('administration:flowBuilder:triggers.placed'),
            ].join(' / '),
            condition: 'Shopping cart / Order with digital products',
            trueAction: Translate('administration:flowBuilder:actions.mailSend'),
            trueActionIdentifier: mailTemplateName,
            falseAction: Translate('administration:flowBuilder:actions.addTag'),
            falseActionIdentifier: tagName,
        };

        await test.step('Create a screenshot of a flow general tab.', async () => {
            await ShopAdmin.goesTo(AdminFlowBuilderListing.url());
            await ShopAdmin.attemptsTo(CreateFlow(testConfig as FlowConfig));
            await ShopAdmin.expects(AdminFlowBuilderDetail.skeletonLoader.first()).not.toBeVisible();
            await ShopAdmin.expects(AdminFlowBuilderDetail.successMessage).toBeVisible();
            await AdminFlowBuilderDetail.messageClose.click();
            await setViewport(AdminFlowBuilderDetail.page, {
                waitForSelector: AdminFlowBuilderDetail.activeSwitch,
            });
            await replaceElements(AdminFlowBuilderDetail.page, [AdminFlowBuilderDetail.header]);
            await hideElements(AdminFlowBuilderDetail.page, [AdminFlowBuilderDetail.nameField]);
            await assertScreenshot(AdminFlowBuilderDetail.page, 'Flow-Builder-Detail-General-Tab.png');
        });

        await test.step('Create a screenshot of the actual flow.', async () => {
            await AdminFlowBuilderDetail.flowTab.click();
            await ShopAdmin.expects(AdminFlowBuilderDetail.triggerSelectField).toBeVisible();
            await setViewport(AdminFlowBuilderDetail.page, {
                waitForSelector: AdminFlowBuilderDetail.falseBlock,
            });
            const actionTag = AdminFlowBuilderDetail.page
                .locator('.sw-flow-sequence-action__action-description')
                .getByText(tagName, { exact: true });
            await ShopAdmin.expects(actionTag).toHaveCount(1);
            await replaceElements(AdminFlowBuilderDetail.page, [
                AdminFlowBuilderDetail.header,
                actionTag,
            ]);
            await assertScreenshot(AdminFlowBuilderDetail.page, 'Flow-Builder-Detail-Flow-Tab.png');
        });
    },
);
