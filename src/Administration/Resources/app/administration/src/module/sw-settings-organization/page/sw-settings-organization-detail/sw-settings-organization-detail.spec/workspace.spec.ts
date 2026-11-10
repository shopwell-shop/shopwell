/**
 * @sw-package framework
 */
import type { VueWrapper } from '@vue/test-utils';
import { createWrapper, router, saveSpy, deleteSpy } from './create-wrapper';

describe('organization tree workspace', () => {
    let wrapper: VueWrapper;
    afterEach(() => wrapper?.unmount());

    it('opens the index as a multi-institution workspace without creating an entity', async () => {
        wrapper = await createWrapper(['organization.creator'], '/list');
        await flushPromises();
        expect(wrapper.findAll('.tree-organization').map((node) => node.text())).toEqual([
            'Shopwell HQ',
            'Asia organization',
        ]);
        expect(wrapper.find('.sw-settings-organization-detail__organization-name').exists()).toBe(false);
        expect(wrapper.find('.sw-settings-organization-detail__empty').exists()).toBe(true);
        expect(saveSpy).not.toHaveBeenCalled();
    });

    it('filters unloaded institutions from the top search and preserves the current unsaved form', async () => {
        wrapper = await createWrapper(['organization.editor'], '/organization/org-id?unitId=unit-child', true);
        await flushPromises();
        await wrapper.get('.sw-settings-organization-detail__unit-name').setValue('Unsaved engineering');
        const search = wrapper.get('.organization-top-search');
        await search.setValue('Operations');
        await flushPromises();
        const tree = wrapper.get('.sw-settings-organization-unit-tree');
        expect(tree.text()).toContain('Asia organization');
        expect(tree.text()).toContain('Operations');
        expect(tree.text()).not.toContain('Shopwell HQ');
        expect(tree.find('input').exists()).toBe(false);
        expect(router.currentRoute.value.query.unitId).toBe('unit-child');
        expect((wrapper.get('.sw-settings-organization-detail__unit-name').element as HTMLInputElement).value).toBe(
            'Unsaved engineering',
        );
        expect(wrapper.find('.discard-modal').exists()).toBe(false);

        await search.setValue('no matching organization or unit');
        await flushPromises();
        expect(tree.find('.sw-settings-organization-unit-tree__empty').exists()).toBe(true);
        await search.setValue('');
        await flushPromises();
        expect(tree.text()).toContain('Shopwell HQ');
        expect(tree.text()).toContain('Asia organization');
        expect(tree.get('[data-item-id="unit:unit-child"]').classes()).toContain('is--highlighted');
        expect(saveSpy).not.toHaveBeenCalled();
    });

    it('switches institutions in place and keeps parent choices inside that institution', async () => {
        wrapper = await createWrapper(['organization.editor']);
        await flushPromises();
        await wrapper.findAll('.tree-organization')[1].trigger('click');
        await flushPromises();
        expect(router.currentRoute.value.params.id).toBe('other-org');
        expect((wrapper.get('.sw-settings-organization-detail__organization-name').element as HTMLInputElement).value).toBe(
            'Asia organization',
        );
        await wrapper
            .findAll('.tree-unit')
            .find((unit) => unit.text() === 'Operations')!
            .trigger('click');
        await flushPromises();
        expect(wrapper.find('.sw-settings-organization-detail__unit-parent option[value="unit-root"]').exists()).toBe(false);
        expect((wrapper.get('.sw-settings-organization-detail__unit-name').element as HTMLInputElement).value).toBe(
            'Operations',
        );
    });

    it('creates a root unit under the institution whose context action was used', async () => {
        wrapper = await createWrapper(['organization.creator'], '/list');
        await flushPromises();
        await wrapper.findAll('.tree-add-root')[1].trigger('click');
        await flushPromises();
        await wrapper.get('.sw-settings-organization-detail__unit-name').setValue('Logistics');
        await wrapper.get('.sw-settings-organization-detail__save').trigger('click');
        await flushPromises();
        expect(saveSpy).toHaveBeenCalledWith(
            expect.objectContaining({ organizationId: 'other-org', parentId: null, name: 'Logistics' }),
        );
    });

    it('opens a department from a deep link and restores selection after refresh', async () => {
        wrapper = await createWrapper(['organization.viewer'], '/organization/org-id?unitId=unit-child');
        await flushPromises();
        expect((wrapper.get('.sw-settings-organization-detail__unit-name').element as HTMLInputElement).value).toBe(
            'Engineering',
        );
        expect((wrapper.get('.sw-settings-organization-detail__unit-parent').element as HTMLSelectElement).value).toBe(
            'unit-root',
        );
    });

    it('expands every ancestor in the native tree when entering a unit deep link', async () => {
        wrapper = await createWrapper(['organization.viewer'], '/organization/org-id?unitId=unit-leaf', true);
        await flushPromises();
        expect(wrapper.get('[data-item-id="organization:org-id"]').attributes('aria-expanded')).toBe('true');
        expect(wrapper.get('[data-item-id="unit:unit-root"]').attributes('aria-expanded')).toBe('true');
        expect(wrapper.get('[data-item-id="unit:unit-child"]').attributes('aria-expanded')).toBe('true');
    });

    it('deletes an institution with confirmation and returns to the shared workspace', async () => {
        wrapper = await createWrapper(['organization.deleter']);
        await flushPromises();
        await wrapper.findAll('.tree-delete-organization')[0].trigger('click');
        expect(deleteSpy).not.toHaveBeenCalled();
        await wrapper.findAll('.sw-settings-organization-detail__delete-organization-modal button')[1].trigger('click');
        await flushPromises();
        expect(router.currentRoute.value.name).toBe('sw.settings.organization.index');
        expect(wrapper.findAll('.tree-organization').map((node) => node.text())).toEqual(['Asia organization']);
    });

    it('uses the shared small confirmation modal with a standard footer and cancel action', async () => {
        wrapper = await createWrapper(['organization.deleter'], '/organization/org-id', false, true);
        await flushPromises();
        await wrapper.findAll('.tree-delete-organization')[0].trigger('click');
        await flushPromises();
        const dialog = document.body.querySelector('.sw-modal--small');
        expect(dialog).not.toBeNull();
        expect(dialog?.querySelector('.sw-modal__header')).not.toBeNull();
        const footerButtons = dialog!.querySelectorAll<HTMLButtonElement>('.sw-modal__footer button');
        expect(footerButtons).toHaveLength(2);
        footerButtons[0].click();
        await flushPromises();
        expect(deleteSpy).not.toHaveBeenCalled();
    });

    it('keeps the saved new department selected and clean for the next institution switch', async () => {
        wrapper = await createWrapper(['organization.creator']);
        await flushPromises();
        await wrapper.findAll('.tree-add-root')[0].trigger('click');
        await flushPromises();
        await wrapper.get('.sw-settings-organization-detail__unit-name').setValue('Support');
        await wrapper.get('.sw-settings-organization-detail__save').trigger('click');
        await flushPromises();
        expect((wrapper.get('.sw-settings-organization-detail__unit-name').element as HTMLInputElement).value).toBe(
            'Support',
        );
        expect(router.currentRoute.value.query.unitId).not.toBe('create');
        await wrapper.findAll('.tree-organization')[1].trigger('click');
        await flushPromises();
        expect(wrapper.find('.discard-modal').exists()).toBe(false);
        expect(router.currentRoute.value.params.id).toBe('other-org');
    });

    it('continues to institution creation after discarding a unit edit once', async () => {
        wrapper = await createWrapper(
            ['organization.creator', 'organization.editor'],
            '/organization/org-id?unitId=unit-child',
        );
        await flushPromises();
        await wrapper.get('.sw-settings-organization-detail__unit-name').setValue('Unsaved');
        await wrapper.get('.sw-settings-organization-detail__add-organization').trigger('click');
        await flushPromises();
        expect(wrapper.find('.discard-modal').exists()).toBe(true);
        await wrapper.get('.discard-changes').trigger('click');
        await flushPromises();
        expect(router.currentRoute.value.name).toBe('sw.settings.organization.create');
        expect(wrapper.find('.discard-modal').exists()).toBe(false);
    });
});
