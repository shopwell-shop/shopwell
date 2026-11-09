import { test } from '@fixtures/AcceptanceTest';

test(
    'Shop administrator should be able to create a landing page.',
    { tag: '@Categories' },
    async ({
        ShopAdmin,
        IdProvider,
        TestDataService,
        AdminCategories,
        CreateLandingPage,
        AdminLandingPageDetail,
        AdminExperienceStudio,
    }) => {
        const layoutUuid = IdProvider.getIdPair().uuid;
        const layoutName = `00_addlandingpage_${layoutUuid}`;
        const layoutType = 'landing_page';
        const landingPageData = {
            name: `00_addlandingpage_${IdProvider.getIdPair().uuid}`,
            salesChannel: 'Storefront',
            seoUrl: `00_addlandingpage_${IdProvider.getIdPair().id}`,
            status: true,
        };

        await test.step('Create a landing page layout via API.', async () => {
            await TestDataService.createBasicPageLayout(layoutType, {
                name: layoutName,
                id: layoutUuid,
            });
        });

        await test.step('Create a new landing page and assign layout.', async () => {
            await ShopAdmin.goesTo(AdminCategories.url());
            await ShopAdmin.attemptsTo(CreateLandingPage(layoutName, landingPageData));
        });

        await test.step('Verify a new landing page created and assigned layout.', async () => {
            const landingPage = await AdminCategories.getLandingPageByName(landingPageData.name);
            await landingPage.click();

            // Verify general tab detail
            await ShopAdmin.expects(AdminLandingPageDetail.nameInput).toHaveValue(landingPageData.name);
            await ShopAdmin.expects(AdminLandingPageDetail.landingPageStatus).toBeChecked({
                checked: landingPageData.status,
            });
            await ShopAdmin.expects(AdminLandingPageDetail.salesChannelSelectionList).toHaveText(
                landingPageData.salesChannel,
            );
            await ShopAdmin.expects(AdminLandingPageDetail.seoUrlInput).toHaveValue(landingPageData.seoUrl);
            await AdminLandingPageDetail.layoutTab.click();
            await ShopAdmin.expects(AdminLandingPageDetail.experienceStudioButton).toBeVisible();
            await AdminLandingPageDetail.experienceStudioButton.click();
            await AdminExperienceStudio.layoutLink(layoutName).click();
            await ShopAdmin.expects(AdminExperienceStudio.layoutTitle).toHaveText(layoutName);
            await ShopAdmin.expects(AdminExperienceStudio.saveButton).toBeVisible();
            await AdminExperienceStudio.assignmentsButton.click();
            const assignedPage = AdminExperienceStudio.assignmentModal
                .locator('.sw-select-selection-list__item-holder')
                .filter({ hasText: landingPageData.name });
            await ShopAdmin.expects(assignedPage).toBeVisible();

            await assignedPage.hover();
            await assignedPage.locator('.sw-label__dismiss').click();
            await AdminExperienceStudio.assignmentSaveButton.click();
            await ShopAdmin.expects(AdminExperienceStudio.assignmentModal).not.toBeVisible();
            await AdminExperienceStudio.assignmentsButton.click();
            await ShopAdmin.expects(assignedPage).not.toBeVisible();
        });
    },
);

test(
    'Storefront should display the landing page name as a heading.',
    { tag: '@Categories' },
    async ({ ShopCustomer, IdProvider, TestDataService, StorefrontPage }) => {
        const layoutUuid = IdProvider.getIdPair().uuid;
        const layoutName = `00_landingPageLayout_${layoutUuid}`;
        const landingPageName = `00_landingPage_${layoutUuid}`;
        const layout = await TestDataService.createBasicPageLayout('landing_page', {
            id: layoutUuid,
            name: layoutName,
            layout: [
                {
                    id: IdProvider.getIdPair().uuid,
                    component: 'Sw:Content:Text',
                    properties: { text: `<h1>${landingPageName}</h1>` },
                },
            ],
        });

        const landingPage = await TestDataService.createLandingPage();

        await TestDataService.assignLandingPageLayout(landingPage.id, layout.id);

        await ShopCustomer.goesTo(landingPage.url);
        await ShopCustomer.expects(
            StorefrontPage.getByRole('heading', {
                name: landingPageName,
                level: 1,
                exact: true,
            }),
        ).toBeVisible();
    },
);
