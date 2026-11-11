/**
 * @sw-package fundamentals@framework
 */
import { createWrapper, menuStore, selectFirstChild, treeStub, typeTitle } from './create-wrapper';

describe('module/sw-permissions/page/sw-permissions-menu-list (editing)', () => {
    let wrapper;

    beforeEach(() => {
        Shopwell.Context.api.languageId = 'language-en';
    });

    afterEach(() => {
        if (wrapper) {
            wrapper.unmount();
            wrapper = null;
        }

        jest.restoreAllMocks();
    });

    it('shows the form of the selected entry with its stored label', async () => {
        wrapper = await createWrapper();

        await selectFirstChild(wrapper);

        expect(treeStub(wrapper).props('selectedId')).toBe('products');
        expect(wrapper.find('.sw-permissions-menu-list__form').exists()).toBe(true);
        expect(wrapper.find('.sw-permissions-menu-list__field-title').element.value).toBe(
            'sw-product.general.mainMenuItemOverview',
        );
        expect(wrapper.find('.sw-permissions-menu-list__button-save').exists()).toBe(true);
    });

    it('cancelling restores the stored state of the selected entry', async () => {
        wrapper = await createWrapper();

        await selectFirstChild(wrapper);
        await typeTitle(wrapper, 'Renamed');
        await wrapper.find('.sw-permissions-menu-list__button-cancel').trigger('click');
        await flushPromises();

        expect(treeStub(wrapper).props('selectedId')).toBe('products');
        expect(wrapper.find('.sw-permissions-menu-list__field-title').element.value).toBe(
            'sw-product.general.mainMenuItemOverview',
        );
    });

    it('saves the labels for the displayed language instead of the client language', async () => {
        wrapper = await createWrapper();

        wrapper.findComponent('.sw-language-switch-stub').vm.$emit('on-change', 'language-zh');
        await flushPromises();

        await selectFirstChild(wrapper);
        await typeTitle(wrapper, 'Produkte');
        await wrapper.find('.sw-permissions-menu-list__button-save').trigger('click');
        await flushPromises();

        expect(menuStore().saveItem).toHaveBeenCalledWith(
            expect.objectContaining({
                groupName: 'general',
                translations: [expect.objectContaining({ languageId: 'language-zh', title: 'Produkte' })],
            }),
        );
    });

    it('keeps the entry selected after saving it', async () => {
        wrapper = await createWrapper();

        await selectFirstChild(wrapper);
        await typeTitle(wrapper, 'Renamed');
        await wrapper.find('.sw-permissions-menu-list__button-save').trigger('click');
        await flushPromises();

        expect(treeStub(wrapper).props('selectedId')).toBe('products');
        expect(wrapper.find('.sw-permissions-menu-list__field-title').element.value).toBe(
            'sw-product.general.mainMenuItemOverview',
        );
    });

    it('places a renamed entry only when its level changed', async () => {
        wrapper = await createWrapper();

        await selectFirstChild(wrapper);
        await typeTitle(wrapper, 'Renamed');
        await wrapper.find('.sw-permissions-menu-list__button-save').trigger('click');
        await flushPromises();

        expect(menuStore().moveItem).not.toHaveBeenCalled();
    });

    it('creates a new entry at the top level and places it', async () => {
        wrapper = await createWrapper();
        menuStore().saveItem.mockResolvedValue('new-id');

        await wrapper.find('.sw-permissions-menu-list__button-create').trigger('click');
        await typeTitle(wrapper, 'New entry');
        await wrapper.find('.sw-permissions-menu-list__field-target').find('option[value="sw.product.index"]').setSelected();
        await wrapper.find('.sw-permissions-menu-list__button-save').trigger('click');
        await flushPromises();

        expect(menuStore().saveItem).toHaveBeenCalledWith(expect.not.objectContaining({ id: expect.anything() }));
        expect(menuStore().moveItem).toHaveBeenCalledWith({
            id: 'new-id',
            parentId: null,
            afterMenuItemId: null,
        });
        expect(treeStub(wrapper).props('selectedId')).toBe('new-id');
    });

    it('prefills the placement when creating from a row', async () => {
        wrapper = await createWrapper();

        await wrapper.find('.tree-add-child').trigger('click');
        expect(wrapper.find('.sw-permissions-menu-list__field-parent').element.value).toBe('catalog');

        await wrapper.find('.sw-permissions-menu-list__button-cancel').trigger('click');
        await flushPromises();

        await wrapper.find('.tree-create-below').trigger('click');

        expect(treeStub(wrapper).props('selectedId')).toBeNull();
        expect(wrapper.find('.sw-permissions-menu-list__field-parent').element.value).toBe('catalog');
        expect(wrapper.find('.sw-permissions-menu-list__form').exists()).toBe(true);
    });

    it('re-reads the draft in the new language when the language switches', async () => {
        wrapper = await createWrapper();

        await selectFirstChild(wrapper);
        wrapper.findComponent('.sw-language-switch-stub').vm.$emit('on-change', 'language-zh');
        await flushPromises();

        expect(wrapper.find('.sw-permissions-menu-list__field-title').element.value).toBe('产品目录');
    });

    it('creates a group as a node instead of editing per-child group metadata', async () => {
        wrapper = await createWrapper();

        await wrapper.find('.tree-select-root').trigger('click');

        expect(wrapper.find('.sw-permissions-menu-list__field-type option[value="group"]').exists()).toBe(true);
        expect(wrapper.find('.sw-permissions-menu-list__field-group').exists()).toBe(false);
        expect(wrapper.find('.sw-permissions-menu-list__field-group-title').exists()).toBe(false);

        await wrapper.find('.sw-permissions-menu-list__button-cancel').trigger('click');
        await flushPromises();
        await selectFirstChild(wrapper);

        expect(wrapper.find('.sw-permissions-menu-list__field-group').exists()).toBe(false);
        expect(wrapper.find('.sw-permissions-menu-list__field-group-title').exists()).toBe(false);
    });

    it('refuses to delete a locked entry', async () => {
        wrapper = await createWrapper();

        await wrapper.find('.tree-remove-locked').trigger('click');

        expect(wrapper.find('.sw-modal-stub').exists()).toBe(false);
    });

    it('deletes an entry after the confirmation and drops its selection', async () => {
        wrapper = await createWrapper();

        await selectFirstChild(wrapper);
        await wrapper.find('.tree-remove-child').trigger('click');

        expect(wrapper.find('.sw-modal-stub').exists()).toBe(true);

        await wrapper.find('.sw-permissions-menu-list__confirm-delete-button').trigger('click');
        await flushPromises();

        expect(menuStore().deleteItem).toHaveBeenCalledWith('products');
        expect(wrapper.find('.sw-modal-stub').exists()).toBe(false);
        expect(treeStub(wrapper).props('selectedId')).toBeNull();
        expect(wrapper.find('.sw-permissions-menu-list__form').exists()).toBe(false);
        expect(wrapper.find('.mt-empty-state-stub').exists()).toBe(true);
    });

    it('names the subtree size in the delete confirmation', async () => {
        wrapper = await createWrapper();

        // a folder an administrator created himself: it may go, and its children go with it
        menuStore().items[0].sysLocked = false;
        await flushPromises();

        await wrapper.find('.tree-remove-locked').trigger('click');

        expect(wrapper.find('.sw-modal-stub').text()).toContain('sw-permissions.menu.deleteConfirmMessageWithChildren');
    });

    it('shows the empty state until an entry is selected', async () => {
        wrapper = await createWrapper();

        expect(wrapper.find('.mt-empty-state-stub').exists()).toBe(true);
        expect(wrapper.find('.sw-permissions-menu-list__form').exists()).toBe(false);
    });
});
