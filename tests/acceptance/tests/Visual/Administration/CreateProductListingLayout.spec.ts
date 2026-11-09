import { test, expect, setViewport, assertScreenshot } from '@fixtures/AcceptanceTest';

test(
    'Visual: Create category content layout flow',
    { tag: '@Visual' },
    async ({ ShopAdmin, AdminExperienceStudio, TestDataService, AdminApiContext }) => {
        test.slow();
        const layoutName = 'Visual category layout';
        let layoutId: string;

        await test.step('List content layouts in Experience Studio.', async () => {
            await ShopAdmin.goesTo(AdminExperienceStudio.url());
            await setViewport(AdminExperienceStudio.page, {
                waitForSelector: AdminExperienceStudio.createNewLayoutButton,
            });
            await assertScreenshot(AdminExperienceStudio.page, 'Layout-Listing.png');
        });

        await test.step('Create a category layout through the name and type wizard.', async () => {
            await AdminExperienceStudio.createNewLayoutButton.click();
            await ShopAdmin.expects(AdminExperienceStudio.layoutTypeOption('category')).toBeVisible();
            await setViewport(AdminExperienceStudio.page, {
                waitForSelector: AdminExperienceStudio.cancelCreateButton,
            });
            await assertScreenshot(AdminExperienceStudio.page, 'Layout-Create-Page-Types.png');

            await AdminExperienceStudio.layoutNameInput.fill(layoutName);
            await AdminExperienceStudio.layoutTypeOption('category').click();
            await ShopAdmin.expects(AdminExperienceStudio.layoutTypeOption('category')).toHaveAttribute(
                'aria-checked',
                'true',
            );
            await ShopAdmin.expects(AdminExperienceStudio.createLayoutButton).toBeEnabled();
            await assertScreenshot(AdminExperienceStudio.page, 'Layout-Create-Layout-Name.png');
            await AdminExperienceStudio.createLayoutButton.click();
            await ShopAdmin.expects(AdminExperienceStudio.layoutTitle).toHaveText(layoutName);
            await ShopAdmin.expects(AdminExperienceStudio.layoutStructure).toBeVisible();

            await AdminExperienceStudio.saveButton.click();
            await ShopAdmin.expects(AdminExperienceStudio.page).toHaveURL(/\/detail\/[0-9a-f]{32}$/);
            const match = AdminExperienceStudio.page.url().match(/\/detail\/([0-9a-f]{32})$/);
            expect(match, 'The saved editor must identify the persisted content layout.').not.toBeNull();
            layoutId = match![1];
            TestDataService.addCreatedRecord('content_layout', layoutId);

            await expect
                .poll(async () => {
                    const response = await AdminApiContext.get(`content-layout/${layoutId}`);
                    if (!response.ok()) return null;
                    return (await response.json()).data;
                })
                .toMatchObject({ id: layoutId, name: layoutName, rootSource: 'category' });
            await ShopAdmin.goesTo(AdminExperienceStudio.url(layoutId));
            await ShopAdmin.expects(AdminExperienceStudio.layoutTitle).toHaveText(layoutName);
            await ShopAdmin.expects(AdminExperienceStudio.assignmentsButton).toBeVisible();
            await assertScreenshot(AdminExperienceStudio.page, 'Layout-Editor.png');
        });

        await test.step('Verify the saved layout is listed and opens the persisted editor.', async () => {
            await ShopAdmin.goesTo(AdminExperienceStudio.url());
            await AdminExperienceStudio.searchLayoutInput.fill(layoutName);
            await ShopAdmin.expects(AdminExperienceStudio.layoutLink(layoutName)).toBeVisible();
            await AdminExperienceStudio.layoutLink(layoutName).click();
            await ShopAdmin.expects(AdminExperienceStudio.layoutTitle).toHaveText(layoutName);
            await ShopAdmin.expects(AdminExperienceStudio.page).toHaveURL(new RegExp(`/detail/${layoutId}`));
        });
    },
);
