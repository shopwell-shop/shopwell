import { test as base, expect } from '@playwright/test';
import type { FixtureTypes, Task } from '@fixtures/AcceptanceTest';

export const CreateLandingPage = base.extend<{ CreateLandingPage: Task }, FixtureTypes>({
    CreateLandingPage: async (
        {
            ShopAdmin,
            AdminCategories,
            AdminLandingPageCreate,
            AdminLandingPageDetail,
            AdminExperienceStudio,
            TestDataService,
            Translate,
        },
        use,
    ) => {
        const task = (layoutName: string | null, landingPageData) => {
            return async function CreateLandingPage() {
                await AdminCategories.landingPageHeadline.click();
                await AdminCategories.addLandingPageButton.click();
                await ShopAdmin.expects(AdminLandingPageDetail.saveLandingPageButton).toBeVisible();
                await ShopAdmin.expects(AdminLandingPageDetail.saveLandingPageButton).toContainText(
                    Translate('administration:landingPage:general.save'),
                );

                await AdminLandingPageCreate.nameInput.fill(landingPageData.name);
                await AdminLandingPageCreate.landingPageStatus.setChecked(landingPageData.status);
                await AdminLandingPageCreate.salesChannelSelectionList.click();
                await AdminLandingPageCreate.filtersResultPopoverItemList
                    .filter({ hasText: landingPageData.salesChannel })
                    .click();
                await AdminLandingPageCreate.page.keyboard.press('Escape');
                await ShopAdmin.expects(
                    AdminLandingPageCreate.filtersResultPopoverItemList.filter({ hasText: landingPageData.salesChannel }),
                ).not.toBeVisible();
                await ShopAdmin.fillsIn(AdminLandingPageCreate.seoUrlInput, landingPageData.seoUrl);

                const [response] = await Promise.all([
                    AdminLandingPageCreate.page.waitForResponse(
                        (response) =>
                            /\/api\/landing-page(?:\/[a-f0-9]{32})?$/.test(new URL(response.url()).pathname) &&
                            ['POST', 'PATCH'].includes(response.request().method()),
                    ),
                    AdminLandingPageCreate.saveLandingPageButton.click(),
                ]);
                expect(response.ok()).toBeTruthy();
                await AdminLandingPageDetail.page.waitForURL(/\/sw\/category\/landingPage\/[a-f0-9]{32}\/base/);
                const landingPageId = AdminLandingPageDetail.page.url().match(/landingPage\/([a-f0-9]{32})/)[1];
                TestDataService.addCreatedRecord('landing_page', landingPageId);

                if (layoutName) {
                    await ShopAdmin.goesTo(AdminExperienceStudio.url());
                    await AdminExperienceStudio.layoutLink(layoutName).click();
                    await AdminExperienceStudio.assignmentsButton.click();
                    await AdminExperienceStudio.assignmentEntitiesInput.click();
                    await AdminExperienceStudio.assignmentEntitiesInput.fill(landingPageData.name);
                    await AdminExperienceStudio.assignmentOption(landingPageData.name).click();
                    await AdminExperienceStudio.assignmentSaveButton.click();
                    await ShopAdmin.expects(AdminExperienceStudio.assignmentModal).not.toBeVisible();
                    await ShopAdmin.goesTo(AdminLandingPageDetail.url(landingPageId));
                }
            };
        };

        await use(task);
    },
});
