/**
 * @sw-package discovery
 */
import { flushPromises, shallowMount } from '@vue/test-utils';

describe('Experience Studio layout labels', () => {
    it('shows a localized built-in name and keeps custom layout names unchanged', async () => {
        const layouts = Object.assign(
            [
                { id: '91ddf2e3fd20050a47409eb9ebbdfff5', name: 'Default blog article' },
                { id: 'merchant-layout', name: 'My article design' },
            ],
            { total: 2 },
        );
        const wrapper = shallowMount(await wrapTestComponent('sw-experience-studio-list', { sync: true }), {
            global: {
                provide: {
                    searchRankingService: {},
                    acl: { can: () => true },
                    repositoryFactory: { create: () => ({ search: () => Promise.resolve(layouts) }) },
                },
                mocks: { $t: (key: string) => key, $createTitle: () => 'Experience Studio' },
                stubs: {
                    'sw-page': { template: '<div><slot name="content" /></div>' },
                    'sw-entity-listing': {
                        props: ['dataSource'],
                        template: '<div><slot v-for="item in dataSource" name="column-name" :item="item" /></div>',
                    },
                    'router-link': { template: '<a><slot /></a>' },
                },
            },
        });
        try {
            await flushPromises();
            expect(wrapper.text()).toContain('sw-experience-studio.builtinLayouts.defaultBlogPost');
            expect(wrapper.text()).toContain('My article design');
        } finally {
            wrapper.unmount();
        }
    });
});
