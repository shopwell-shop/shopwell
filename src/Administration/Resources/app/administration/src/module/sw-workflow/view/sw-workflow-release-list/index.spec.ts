/** @sw-package framework */
import { shallowMount, flushPromises } from '@vue/test-utils';

describe('workflow release catalogue', () => {
    it('preserves publication history without offering a request action', async () => {
        const release = { id: 'release', name: 'Review', revision: 2, publisher: 'removed:plugin', createdAt: '2026-11-13' };
        const getReleases = jest.fn().mockResolvedValue({ data: [release], total: 1 });
        const wrapper = shallowMount(await wrapTestComponent('sw-workflow-release-list', { sync: true }), {
            global: {
                provide: {
                    workflowService: { getReleases },
                },
                mocks: { $route: { query: {} } },
                stubs: {
                    'sw-page': { template: '<div><slot name="content" /></div>' },
                    'sw-data-grid': {
                        name: 'sw-data-grid',
                        props: ['dataSource', 'columns'],
                        template:
                            '<div v-if="dataSource.length"><span v-for="column in columns" :key="column.property">{{ dataSource[0][column.property] }}</span></div>',
                    },
                },
            },
        });
        await flushPromises();
        expect(wrapper.text()).toContain('Review');
        expect(wrapper.text()).not.toContain('removed:plugin');
        expect(wrapper.findComponent({ name: 'sw-data-grid' }).props('dataSource')).toEqual([release]);
        expect(wrapper.find('sw-workflow-start-stub').exists()).toBe(false);
        expect(getReleases).toHaveBeenCalledWith({ draftId: undefined, limit: 25, offset: 0 });
        wrapper.unmount();
    });
});
