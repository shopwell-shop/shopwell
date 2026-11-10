/**
 * @sw-package framework
 */
import type { VueWrapper } from '@vue/test-utils';
import { createWrapper, router, saveSpy, deleteSpy, searchSpy } from './sw-settings-organization-detail.spec/create-wrapper';

describe('module/sw-settings-organization/page/sw-settings-organization-detail', () => {
    let wrapper: VueWrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
        await flushPromises();
    });

    afterEach(() => wrapper.unmount());

    it.each(['/list', '/organization/another-org'])('keeps the draft and cancels navigation to %s', async (destination) => {
        wrapper.unmount();
        wrapper = await createWrapper(['organization.editor']);
        await flushPromises();
        await wrapper.get('.sw-settings-organization-detail__organization-name').setValue('Unsaved organization');
        const navigation = router.push(destination);
        await flushPromises();
        await wrapper.get('.keep-editing').trigger('click');
        await navigation;

        expect(router.currentRoute.value.path).toBe('/organization/org-id');
        expect((wrapper.get('.sw-settings-organization-detail__organization-name').element as HTMLInputElement).value).toBe(
            'Unsaved organization',
        );
    });

    it('loads every page of an organization with more than 500 units', async () => {
        searchSpy.mockReset();
        for (let page = 0; page < 6; page += 1) {
            searchSpy.mockResolvedValueOnce(
                Array.from({ length: 100 }, (_, index) => ({
                    id: `unit-${page * 100 + index}`,
                    parentId: null,
                    organizationId: 'large-org',
                    name: `Department ${page * 100 + index}`,
                    active: true,
                })),
            );
        }
        searchSpy.mockResolvedValueOnce([]);
        await router.push('/organization/large-org');
        await flushPromises();
        expect(wrapper.findAll('.tree-unit[data-organization="large-org"]')).toHaveLength(600);
        expect(searchSpy).toHaveBeenCalledTimes(7);
    });

    it('preserves a draft when changing the selected node', async () => {
        wrapper.unmount();
        wrapper = await createWrapper(['organization.editor']);
        await flushPromises();
        await wrapper.findAll('.tree-unit')[0].trigger('click');
        await flushPromises();
        await wrapper.get('.sw-settings-organization-detail__unit-name').setValue('Unsaved name');
        await wrapper.findAll('.tree-unit')[1].trigger('click');
        await flushPromises();
        expect((wrapper.get('.sw-settings-organization-detail__unit-name').element as HTMLInputElement).value).toBe(
            'Unsaved name',
        );
        expect(wrapper.findComponent({ name: 'sw-discard-changes-modal' }).exists()).toBe(true);
        expect(saveSpy).not.toHaveBeenCalled();
    });

    it('should load the organization and its units', () => {
        expect(wrapper.get('h2').text()).toContain('Shopwell HQ');
        expect(wrapper.findAll('.tree-unit')).toHaveLength(3);
    });

    it('shows organization settings before selecting a unit', () => {
        expect(wrapper.find('.sw-settings-organization-detail__organization-name').exists()).toBe(true);
    });

    it('should load the unit form when a unit is selected', async () => {
        await wrapper.findAll('.tree-unit')[0].trigger('click');
        await flushPromises();

        expect((wrapper.get('.sw-settings-organization-detail__unit-name').element as HTMLInputElement).value).toBe(
            'Headquarters',
        );
    });

    it('should exclude the unit and its descendants from the parent options (circular parent protection)', async () => {
        await wrapper.findAll('.tree-unit')[1].trigger('click');
        await flushPromises();

        const optionIds = wrapper
            .findAll('.sw-settings-organization-detail__unit-parent option')
            .map((option) => option.attributes('value'));

        expect(optionIds).toContain('unit-root');
        expect(optionIds).not.toContain('unit-child');
        expect(optionIds).not.toContain('unit-leaf');
    });

    it('prevents choosing a descendant parent before saving', async () => {
        wrapper.unmount();
        wrapper = await createWrapper(['organization.editor']);
        await flushPromises();

        await wrapper.findAll('.tree-unit')[1].trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-settings-organization-detail__unit-parent option[value="unit-leaf"]').exists()).toBe(false);
        expect((wrapper.get('.sw-settings-organization-detail__unit-parent').element as HTMLSelectElement).value).toBe(
            'unit-root',
        );
        expect(saveSpy).not.toHaveBeenCalled();
    });

    it('saves edits only after the save action', async () => {
        wrapper.unmount();
        wrapper = await createWrapper(['organization.editor']);
        await flushPromises();

        await wrapper.findAll('.tree-unit')[0].trigger('click');
        await flushPromises();

        await wrapper.get('.sw-settings-organization-detail__unit-name').setValue('HQ renamed');
        await flushPromises();

        expect(saveSpy).not.toHaveBeenCalled();
        await wrapper.get('.sw-settings-organization-detail__save').trigger('click');
        await flushPromises();

        expect(saveSpy).toHaveBeenCalledWith(
            expect.objectContaining({
                name: 'HQ renamed',
            }),
        );
    });

    it('keeps a failed edit available for retry', async () => {
        wrapper.unmount();
        wrapper = await createWrapper(['organization.editor']);
        await flushPromises();
        await wrapper.findAll('.tree-unit')[0].trigger('click');
        await flushPromises();
        await wrapper.get('.sw-settings-organization-detail__unit-name').setValue('Retry name');
        saveSpy.mockRejectedValueOnce(new Error('Save failed'));

        await wrapper.get('.sw-settings-organization-detail__save').trigger('click');
        await flushPromises();

        expect((wrapper.get('.sw-settings-organization-detail__unit-name').element as HTMLInputElement).value).toBe(
            'Retry name',
        );
        expect(wrapper.get('.sw-settings-organization-detail__save').attributes('disabled')).toBeUndefined();
    });

    it('offers a root parent when moving a department', async () => {
        await wrapper.findAll('.tree-unit')[1].trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-settings-organization-detail__unit-parent option[value=""]').exists()).toBe(true);
    });

    it('opens a blank department editor and saves its full form only on Save', async () => {
        wrapper.unmount();
        wrapper = await createWrapper(['organization.creator', 'organization.editor']);
        await flushPromises();
        await wrapper.findAll('.tree-add-child')[0].trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-settings-organization-detail__create-unit-modal').exists()).toBe(false);
        expect(wrapper.find('.sw-settings-organization-detail__unit-name').exists()).toBe(true);
        expect((wrapper.get('.sw-settings-organization-detail__unit-parent').element as HTMLSelectElement).value).toBe(
            'unit-root',
        );
        expect(saveSpy).not.toHaveBeenCalled();
        await wrapper.get('.sw-settings-organization-detail__unit-name').setValue('Support');
        await wrapper.get('.sw-settings-organization-detail__save').trigger('click');
        await flushPromises();

        expect(saveSpy).toHaveBeenCalledWith(
            expect.objectContaining({ organizationId: 'org-id', parentId: 'unit-root', name: 'Support' }),
        );
    });

    it('cancels a new department without writing it and returns to its parent', async () => {
        wrapper.unmount();
        wrapper = await createWrapper(['organization.creator', 'organization.editor']);
        await flushPromises();
        await wrapper.findAll('.tree-add-child')[0].trigger('click');
        await flushPromises();
        await wrapper.get('.sw-settings-organization-detail__unit-name').setValue('Temporary department');
        await wrapper.get('.sw-settings-organization-detail__cancel').trigger('click');
        await flushPromises();

        expect(saveSpy).not.toHaveBeenCalled();
        expect(wrapper.findAll('.tree-unit')).toHaveLength(3);
        expect((wrapper.get('.sw-settings-organization-detail__unit-name').element as HTMLInputElement).value).toBe(
            'Headquarters',
        );
    });

    it('opens the organization create form without requiring a name first', async () => {
        wrapper.unmount();
        wrapper = await createWrapper(['organization.creator']);
        await flushPromises();
        await router.push('/create');
        await flushPromises();

        expect((wrapper.get('.sw-settings-organization-detail__organization-name').element as HTMLInputElement).value).toBe(
            '',
        );
        expect(wrapper.get('h2').text()).toContain('sw-settings-organization.list.modalTitleCreate');
        expect(saveSpy).not.toHaveBeenCalled();
        await wrapper.get('.sw-settings-organization-detail__organization-name').setValue('New organization');
        await wrapper.get('.sw-settings-organization-detail__save').trigger('click');
        await flushPromises();

        expect(saveSpy).toHaveBeenCalledWith(expect.objectContaining({ name: 'New organization', active: true }));
        expect(router.currentRoute.value.name).toBe('sw.settings.organization.detail');
    });

    it('cancels a new organization and returns to the list without saving', async () => {
        wrapper.unmount();
        wrapper = await createWrapper(['organization.creator']);
        await flushPromises();
        await router.push('/create');
        await flushPromises();
        await wrapper.get('.sw-settings-organization-detail__organization-name').setValue('Temporary organization');
        await wrapper.get('.sw-settings-organization-detail__cancel').trigger('click');
        await flushPromises();

        expect(router.currentRoute.value.name).toBe('sw.settings.organization.index');
        expect(saveSpy).not.toHaveBeenCalled();
    });

    it('keeps a failed new department available with its parent for retry', async () => {
        wrapper.unmount();
        wrapper = await createWrapper(['organization.creator']);
        await flushPromises();
        await wrapper.findAll('.tree-add-child')[0].trigger('click');
        await flushPromises();
        await wrapper.get('.sw-settings-organization-detail__unit-name').setValue('Support');
        saveSpy.mockRejectedValueOnce(new Error('Save failed'));
        await wrapper.get('.sw-settings-organization-detail__save').trigger('click');
        await flushPromises();

        expect(wrapper.findAll('.tree-unit')).toHaveLength(4);
        expect((wrapper.get('.sw-settings-organization-detail__unit-name').element as HTMLInputElement).value).toBe(
            'Support',
        );
        expect((wrapper.get('.sw-settings-organization-detail__unit-parent').element as HTMLSelectElement).value).toBe(
            'unit-root',
        );
        await wrapper.get('.sw-settings-organization-detail__save').trigger('click');
        await flushPromises();
        expect(saveSpy).toHaveBeenCalledTimes(2);
    });

    it('does not create children under an unsaved department', async () => {
        wrapper.unmount();
        wrapper = await createWrapper(['organization.creator']);
        await flushPromises();
        await wrapper.get('.sw-settings-organization-detail__add-root-unit').trigger('click');
        await flushPromises();
        await wrapper.findAll('.tree-add-child')[3].trigger('click');
        await flushPromises();

        expect(wrapper.find('.discard-modal').exists()).toBe(false);
        expect(wrapper.findAll('.tree-unit')).toHaveLength(4);
        expect(saveSpy).not.toHaveBeenCalled();
    });

    it('cancels edits using the entity repository and restores the tree name', async () => {
        wrapper.unmount();
        wrapper = await createWrapper(['organization.editor']);
        await flushPromises();
        await wrapper.findAll('.tree-unit')[0].trigger('click');
        await flushPromises();
        await wrapper.get('.sw-settings-organization-detail__unit-name').setValue('Discard this');
        await wrapper.get('.sw-settings-organization-detail__cancel').trigger('click');
        await wrapper.findAll('.tree-unit')[0].trigger('click');
        await flushPromises();

        expect((wrapper.get('.sw-settings-organization-detail__unit-name').element as HTMLInputElement).value).toBe(
            'Headquarters',
        );
        expect(saveSpy).not.toHaveBeenCalled();
    });

    it('opens an unsaved root department with no parent and validates it on save', async () => {
        wrapper.unmount();
        wrapper = await createWrapper(['organization.creator']);
        await flushPromises();
        await wrapper.get('.sw-settings-organization-detail__add-root-unit').trigger('click');
        await flushPromises();
        expect((wrapper.get('.sw-settings-organization-detail__unit-parent').element as HTMLSelectElement).value).toBe('');
        await wrapper.get('.sw-settings-organization-detail__save').trigger('click');
        await flushPromises();

        expect(saveSpy).not.toHaveBeenCalled();
        expect(wrapper.find('.sw-settings-organization-detail__unit-name').exists()).toBe(true);
        expect(wrapper.find('.sw-settings-organization-detail__delete-unit').exists()).toBe(false);
    });

    it('keeps the unit deletion dialog available when deletion fails', async () => {
        wrapper.unmount();
        wrapper = await createWrapper(['organization.deleter']);
        await flushPromises();
        await wrapper.findAll('.tree-unit')[2].trigger('click');
        await flushPromises();
        await wrapper.findAll('.tree-delete-unit')[2].trigger('click');
        deleteSpy.mockRejectedValueOnce(new Error('Delete failed'));
        await wrapper.findAll('.sw-settings-organization-detail__delete-unit-modal button')[1].trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-settings-organization-detail__delete-unit-modal').exists()).toBe(true);
        expect(wrapper.findAll('.tree-unit')).toHaveLength(3);
    });

    it('should confirm the delete of the selected unit', async () => {
        wrapper.unmount();
        wrapper = await createWrapper(['organization.deleter']);
        await flushPromises();

        await wrapper.findAll('.tree-unit')[2].trigger('click');
        await flushPromises();

        await wrapper.findAll('.tree-delete-unit')[2].trigger('click');
        expect(wrapper.find('.sw-settings-organization-detail__delete-unit-modal').exists()).toBe(true);
        expect(wrapper.get('.sw-modal').attributes('variant')).toBe('small');
        expect(deleteSpy).not.toHaveBeenCalled();
        await wrapper.findAll('.sw-settings-organization-detail__delete-unit-modal button')[1].trigger('click');
        await flushPromises();

        expect(deleteSpy).toHaveBeenCalledWith('unit-leaf');
    });
});
