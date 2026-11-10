/**
 * @sw-package discovery
 */
import { flushPromises } from '@vue/test-utils';
import { createWrapper, unmountActiveWrapper } from './create-wrapper';

describe('Blog detail system interactions', () => {
    afterEach(() => {
        unmountActiveWrapper();
        jest.restoreAllMocks();
    });

    it('locks editing and late media callbacks while saving and unlocks after failure', async () => {
        const { wrapper, saveBlog } = await createWrapper();
        await flushPromises();
        let rejectSave: (error: Error) => void = () => {};
        saveBlog.mockImplementationOnce(
            () =>
                new Promise((resolve, reject) => {
                    rejectSave = reject;
                }),
        );
        await wrapper.get('.sw-blog-detail__title').setValue('Keep this draft');
        await wrapper.get('.sw-blog-detail__action-save').trigger('click');
        expect(wrapper.get('.sw-blog-detail__title').attributes('disabled')).toBeDefined();
        expect(wrapper.findComponent({ name: 'sw-language-switch' }).props('disabled')).toBe(true);
        await wrapper.get('.select-cover').trigger('click');
        expect(wrapper.get('.cover-source').text()).toBe('');
        rejectSave(new Error('Save failed'));
        await flushPromises();
        expect(wrapper.get('.sw-blog-detail__title').attributes('disabled')).toBeUndefined();
        expect((wrapper.get('.sw-blog-detail__title').element as HTMLInputElement).value).toBe('Keep this draft');
        expect(wrapper.findComponent({ name: 'sw-language-switch' }).props('disabled')).toBe(false);
    });

    it('uses the system discard modal to keep editing or leave the page', async () => {
        const { wrapper, router } = await createWrapper({ realRouter: true, hasChanges: true });
        await flushPromises();
        await wrapper.get('.sw-blog-detail__title').setValue('Unsaved title');
        await router!.push('/outside');
        await flushPromises();
        expect(router!.currentRoute.value.path).toBe('/article');
        const modal = wrapper.find('.modal');
        expect(modal.exists()).toBe(true);
        await modal.findAll('button')[0].trigger('click');
        expect(wrapper.find('.modal').exists()).toBe(false);
        expect((wrapper.get('.sw-blog-detail__title').element as HTMLInputElement).value).toBe('Unsaved title');
        await router!.push('/outside');
        await flushPromises();
        await wrapper.find('.modal').findAll('button')[1].trigger('click');
        await flushPromises();
        expect(wrapper.get('.outside').text()).toBe('Outside');
    });
    it('rejects a language change when the translation cannot be saved', async () => {
        const { wrapper, saveBlog, getBlog } = await createWrapper();
        await flushPromises();
        saveBlog.mockRejectedValueOnce(new Error('Save failed'));
        await wrapper.get('.language-save').trigger('click');
        await flushPromises();
        expect(wrapper.get('.language-save-failed').text()).toBe('true');
        expect(getBlog).toHaveBeenCalledTimes(1);
    });
});
