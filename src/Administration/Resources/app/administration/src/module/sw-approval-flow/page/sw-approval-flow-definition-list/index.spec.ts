/** @sw-package framework */
import { flushPromises, shallowMount } from '@vue/test-utils';
import { reactive } from 'vue';
import type Criteria from 'src/core/data/criteria.data';

const definition = {
    id: 'flow',
    name: '采购审批',
    createdAt: null,
    updatedAt: null,
    draft: { revision: 3, createdAt: null, updatedAt: null },
};
function page(items = [definition], total = items.length) {
    return Object.assign(items, { total, getIds: () => items.map((item) => item.id) });
}
async function createWrapper(
    search = jest.fn<Promise<ReturnType<typeof page>>, [Criteria, apiContext]>().mockResolvedValue(page()),
) {
    const repositoryFactory = { create: jest.fn().mockReturnValue({ search }) };
    const route = reactive({ query: {} as Record<string, string> });
    const push = jest.fn();
    const wrapper = shallowMount(await wrapTestComponent('sw-approval-flow-definition-list', { sync: true }), {
        global: {
            provide: { repositoryFactory, acl: { can: () => true } },
            mocks: { $route: route, $router: { push } },
            stubs: {
                'sw-page': {
                    template:
                        '<div><slot name="smart-bar-header"/><slot name="smart-bar-actions"/><slot name="content"/></div>',
                },
                'mt-data-table': {
                    name: 'mt-data-table',
                    props: ['dataSource'],
                    template: `<section><input @input="$emit('search-value-change', $event.target.value)"/><slot v-for="item in dataSource" name="column-name" :data="item"/><slot v-if="!dataSource.length" name="empty-state"/></section>`,
                },
                'router-link': { props: ['to'], template: '<a :data-route="to.name"><slot/></a>' },
                'mt-button': { template: '<button><slot/></button>' },
                'mt-text-field': {
                    props: ['modelValue'],
                    template: `<input :value="modelValue" @input="$emit('update:modelValue', $event.target.value)"/>`,
                },
                'mt-banner': { template: '<div role="alert"><slot/></div>' },
                'mt-empty-state': { template: '<div class="empty"/>' },
                'sw-pagination': true,
            },
        },
    });
    await flushPromises();
    return { wrapper, search, repositoryFactory, route, push };
}

describe('approval definition list', () => {
    it('loads real definition summaries and links to their separate design overview', async () => {
        const { wrapper, search } = await createWrapper();
        expect(search.mock.calls[0][0].getLimit()).toBe(25);
        expect(search.mock.calls[0][0].getPage()).toBe(1);
        expect(search.mock.calls[0][0].getAssociation('draft')).toBeDefined();
        expect(wrapper.findComponent({ name: 'mt-data-table' }).props('dataSource')).toEqual([
            expect.objectContaining({ id: 'flow', draftRevision: 3 }),
        ]);
        expect(wrapper.find('a').text()).toBe('采购审批');
        expect(wrapper.find('a').attributes('data-route')).toBe('sw.approval.flow.definition');
    });

    it('applies name search through the route and resets its page', async () => {
        const { wrapper, push, route } = await createWrapper();
        route.query = { page: '3', limit: '10' };
        await flushPromises();
        await wrapper.find('input').setValue('转账');
        expect(push).toHaveBeenLastCalledWith({
            name: 'sw.approval.flow.definitions',
            query: { term: '转账', limit: '10' },
        });
    });

    it('shows failure with a working retry and does not claim the definition list is empty', async () => {
        const { wrapper } = await createWrapper(
            jest
                .fn<Promise<ReturnType<typeof page>>, [Criteria, apiContext]>()
                .mockRejectedValueOnce(new Error('offline'))
                .mockResolvedValueOnce(page([])),
        );
        expect(wrapper.find('[role="alert"]').exists()).toBe(true);
        expect(wrapper.find('.empty').exists()).toBe(false);
        await wrapper.find('[role="alert"] button').trigger('click');
        await flushPromises();
        expect(wrapper.find('.empty').exists()).toBe(true);
    });

    it('ignores a slower response for an old search', async () => {
        let resolve!: (value: ReturnType<typeof page>) => void;
        const search = jest
            .fn<Promise<ReturnType<typeof page>>, [Criteria, apiContext]>()
            .mockReturnValueOnce(
                new Promise((done) => {
                    resolve = done;
                }),
            )
            .mockResolvedValueOnce(page([{ ...definition, name: '新结果' }]));
        const { wrapper, route } = await createWrapper(search);
        route.query = { term: '新' };
        await flushPromises();
        resolve(page([{ ...definition, name: '旧结果' }]));
        await flushPromises();
        expect(wrapper.text()).toContain('新结果');
        expect(wrapper.text()).not.toContain('旧结果');
    });
});
