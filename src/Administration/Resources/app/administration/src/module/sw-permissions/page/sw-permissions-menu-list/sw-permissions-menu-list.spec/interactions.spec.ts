/**
 * @sw-package fundamentals@framework
 */
/* eslint-disable @typescript-eslint/unbound-method -- Store actions are replaced with Jest mocks in the wrapper. */
import { createWrapper, menuStore, selectFirstChild, typeTitle } from './create-wrapper';

describe('menu editing safeguards', () => {
    let wrapper: Awaited<ReturnType<typeof createWrapper>>;

    beforeEach(() => {
        Shopwell.Context.api.languageId = 'language-en' as typeof Shopwell.Context.api.languageId;
    });

    afterEach(() => {
        wrapper?.unmount();
        jest.restoreAllMocks();
    });

    it('opens menu edits in a Meteor modal with save and cancel in its footer', async () => {
        wrapper = await createWrapper();
        await selectFirstChild(wrapper);
        expect(wrapper.find('.sw-permissions-menu-list__edit-modal .sw-permissions-menu-list__button-save').exists()).toBe(
            true,
        );
        expect(wrapper.find('.sw-permissions-menu-list__edit-modal .sw-permissions-menu-list__button-cancel').exists()).toBe(
            true,
        );
        expect(wrapper.find('.sw-permissions-menu-list__button-create').exists()).toBe(true);
        expect(wrapper.find('.sw-permissions-menu-list__navigation').exists()).toBe(false);
    });

    it('returns to the table after saving and can reopen the same menu', async () => {
        wrapper = await createWrapper();
        await selectFirstChild(wrapper);
        await typeTitle(wrapper, 'My products');
        await wrapper.get('.sw-permissions-menu-list__button-save').trigger('click');
        await flushPromises();
        expect(wrapper.find('.sw-permissions-menu-list__edit-modal').exists()).toBe(false);
        await selectFirstChild(wrapper);
        expect(wrapper.find('.sw-permissions-menu-list__edit-modal').exists()).toBe(true);
    });

    it('keeps unsaved modal edits until closing is confirmed', async () => {
        wrapper = await createWrapper();
        await selectFirstChild(wrapper);
        await typeTitle(wrapper, 'My products');
        await wrapper.get('.sw-permissions-menu-list__button-cancel').trigger('click');
        expect(wrapper.find('.sw-permissions-menu-list__unsaved-modal').exists()).toBe(true);
        expect(menuStore().saveItem).not.toHaveBeenCalled();
        await wrapper.get('.sw-permissions-menu-list__discard-changes').trigger('click');
        expect(wrapper.find('.sw-permissions-menu-list__edit-modal').exists()).toBe(false);
        expect(menuStore().saveItem).not.toHaveBeenCalled();
    });

    it('preserves an edited draft until switching is explicitly confirmed', async () => {
        wrapper = await createWrapper();
        await selectFirstChild(wrapper);
        await typeTitle(wrapper, 'My products');
        await wrapper.get('.tree-select-root').trigger('click');
        expect((wrapper.get('.sw-permissions-menu-list__field-title').element as HTMLInputElement).value).toBe(
            'My products',
        );
        expect(wrapper.find('.sw-permissions-menu-list__unsaved-modal').exists()).toBe(true);
        await wrapper.get('.sw-permissions-menu-list__discard-changes').trigger('click');
        expect((wrapper.get('.sw-permissions-menu-list__field-title').element as HTMLInputElement).value).toBe(
            'sw-catalogue.general.mainMenuItemProducts',
        );
    });

    it('rejects a language-switch save when the write fails', async () => {
        wrapper = await createWrapper();
        await selectFirstChild(wrapper);
        await typeTitle(wrapper, 'My products');
        jest.mocked(menuStore().saveItem).mockRejectedValue(new Error('write failed'));
        const save = wrapper
            .getComponent({ name: 'sw-language-switch' })
            .props('saveChangesFunction') as () => Promise<void>;
        await expect(save()).rejects.toThrow();
        expect((wrapper.get('.sw-permissions-menu-list__field-title').element as HTMLInputElement).value).toBe(
            'My products',
        );
    });

    it('keeps the created identity when positioning fails so retry cannot create a duplicate', async () => {
        wrapper = await createWrapper();
        jest.mocked(menuStore().saveItem).mockResolvedValue('created-id');
        jest.mocked(menuStore().moveItem).mockRejectedValue(new Error('move failed'));
        await wrapper.get('.sw-permissions-menu-list__button-create').trigger('click');
        await typeTitle(wrapper, 'Resources');
        await wrapper.get('.sw-permissions-menu-list__field-type').setValue('folder');
        await wrapper.get('.sw-permissions-menu-list__button-save').trigger('click');
        await flushPromises();
        await wrapper.get('.sw-permissions-menu-list__button-save').trigger('click');
        await flushPromises();
        expect(menuStore().saveItem).toHaveBeenLastCalledWith(expect.objectContaining({ id: 'created-id' }));
    });

    it('offers independent group nodes below settings', async () => {
        wrapper = await createWrapper();
        menuStore().items[0].internalKey = 'sw-settings';
        await selectFirstChild(wrapper);
        expect(wrapper.find('.sw-permissions-menu-list__field-type option[value="group"]').exists()).toBe(true);
        expect(wrapper.find('.sw-permissions-menu-list__field-group').exists()).toBe(false);
    });
    it('keeps the draft when the user cancels a pending selection change', async () => {
        wrapper = await createWrapper();
        await selectFirstChild(wrapper);
        await typeTitle(wrapper, 'My products');
        await wrapper.get('.tree-select-root').trigger('click');
        await wrapper.get('.sw-permissions-menu-list__unsaved-modal button').trigger('click');
        expect((wrapper.get('.sw-permissions-menu-list__field-title').element as HTMLInputElement).value).toBe(
            'My products',
        );
        expect(wrapper.find('.sw-permissions-menu-list__unsaved-modal').exists()).toBe(false);
    });
    it('disables language switching for the whole save operation', async () => {
        wrapper = await createWrapper();
        await selectFirstChild(wrapper);
        await typeTitle(wrapper, 'My products');
        let finish: (id: string) => void = () => {};
        jest.mocked(menuStore().saveItem).mockImplementation(
            () =>
                new Promise<string>((resolve) => {
                    finish = resolve;
                }),
        );
        await wrapper.get('.sw-permissions-menu-list__button-save').trigger('click');
        expect(wrapper.getComponent({ name: 'sw-language-switch' }).props('disabled')).toBe(true);
        finish('products');
        await flushPromises();
        expect(wrapper.getComponent({ name: 'sw-language-switch' }).props('disabled')).toBe(false);
    });

    it('keeps the settings container selectable as a parent', async () => {
        wrapper = await createWrapper();
        menuStore().items[0].internalKey = 'sw-settings';
        menuStore().items[0].type = 'menu';
        await selectFirstChild(wrapper);
        expect((wrapper.get('.sw-permissions-menu-list__field-parent').element as HTMLSelectElement).value).toBe('catalog');
    });
});
