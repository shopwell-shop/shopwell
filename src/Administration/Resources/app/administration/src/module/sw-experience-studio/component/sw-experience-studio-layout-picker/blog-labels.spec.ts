/**
 * @sw-package discovery
 */
import { flushPromises, shallowMount } from '@vue/test-utils';

describe('Experience Studio layout labels', () => {
    it('shows a localized built-in name and keeps custom layout names unchanged', async () => {
        const [picker, grid] = await Promise.all([
            wrapTestComponent('sw-experience-studio-layout-picker', { sync: true }),
            wrapTestComponent('sw-data-grid', { sync: true }),
        ]);
        const layouts = Object.assign(
            [
                { id: '91ddf2e3fd20050a47409eb9ebbdfff5', name: 'Default blog article' },
                { id: 'merchant-layout', name: 'My article design' },
            ],
            { total: 2 },
        );
        const wrapper = shallowMount(picker, {
            global: {
                provide: {
                    acl: { can: () => false },
                    repositoryFactory: { create: () => ({ search: () => Promise.resolve(layouts) }) },
                },
                mocks: { $t: (key: string) => key },
                stubs: {
                    'sw-modal': { template: '<div><slot /><footer><slot name="modal-footer" /></footer></div>' },
                    'sw-data-grid': grid,
                    'sw-provide': { inheritAttrs: false, template: '<slot />' },
                },
            },
        });
        try {
            await flushPromises();
            expect(wrapper.text()).toContain('sw-experience-studio.builtinLayouts.defaultBlogPost');
            expect(wrapper.text()).toContain('My article design');
            await wrapper.get('.sw-experience-studio-layout-picker__layout-name').trigger('click');
            expect(wrapper.emitted('select')).toEqual([['91ddf2e3fd20050a47409eb9ebbdfff5']]);
        } finally {
            wrapper.unmount();
        }
    });
});
