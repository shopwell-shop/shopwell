/** @sw-package framework */
import { shallowMount, flushPromises, type VueWrapper } from '@vue/test-utils';
import { reactive, type ComponentPublicInstance } from 'vue';
import type { ApprovalPage, ApprovalView } from '../../service/approval-flow.api.service';

const request = {
    id: 'request-one',
    title: 'Transfer approval',
    businessType: 'payment:transfer',
    businessId: 'transfer-one',
    submissionRound: 1,
    instanceId: 'instance-one',
    initiatorId: 'author',
    initiatorName: 'Alice',
    createdAt: '2026-11-24T08:00:00+00:00',
    status: 'running',
    outcome: null,
    aggregateVersion: 2,
} as const;

async function createWrapper(search = jest.fn().mockResolvedValue({ data: [request], meta: { total: 26 } })) {
    const route = reactive({ name: 'sw.approval.flow.todo', query: {} as Record<string, string> });
    const push = jest.fn();
    const wrapper = shallowMount(await wrapTestComponent('sw-approval-flow-list', { sync: true }), {
        props: { view: 'todo' },
        global: {
            provide: { approvalFlowApiService: { search }, acl: { can: () => true } },
            mocks: { $route: route, $router: { push } },
            stubs: {
                'sw-page': {
                    template:
                        '<div><slot name="smart-bar-header"/><slot name="smart-bar-actions"/><slot name="content"/></div>',
                },
                'sw-data-grid': {
                    props: ['dataSource'],
                    template:
                        '<div><div v-for="item in dataSource" :key="item.id"><slot name="column-title" :item="item"/><slot name="column-initiatorName" :item="item"/></div><slot name="pagination"/></div>',
                },
                'router-link': { props: ['to'], template: '<a :data-route="to.name"><slot/></a>' },
                'mt-button': { template: '<button><slot/></button>' },
                'mt-banner': { template: '<section role="alert"><slot/></section>' },
                'mt-empty-state': { props: ['headline'], template: '<p class="empty">{{ headline }}</p>' },
                'mt-tabs': {
                    template: `<button class="change-view" @click="$emit('new-item-active', 'started')">My requests</button>`,
                },
                'mt-select': {
                    props: ['modelValue', 'options'],
                    template: `<select :value="modelValue" @change="$emit('update:modelValue', $event.target.value)"><option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option></select>`,
                },
                'sw-pagination': await wrapTestComponent('sw-pagination', { sync: true }),
                'sw-approval-flow-status-badge': true,
            },
        },
    });
    await flushPromises();
    return {
        wrapper: wrapper as unknown as VueWrapper<ComponentPublicInstance<{ view: ApprovalView }>>,
        search,
        route,
        push,
    };
}

describe('personal approval list', () => {
    it('loads the selected inbox and links readable summaries to their details', async () => {
        const { wrapper, search } = await createWrapper();

        expect(search).toHaveBeenCalledWith({ view: 'todo', limit: 25, offset: 0 });
        expect(wrapper.text()).toContain('Transfer approval');
        expect(wrapper.text()).toContain('Alice');
        expect(wrapper.find('a').attributes('data-route')).toBe('sw.approval.flow.detail');
        expect(wrapper.text()).not.toContain('instance-one');
    });

    it('reads page and status from the route and resets them when changing inbox', async () => {
        const { wrapper, search, route, push } = await createWrapper();
        route.query = { page: '3', limit: '10', status: 'completed' };
        await wrapper.setProps({ view: 'done' });
        await flushPromises();

        expect(search).toHaveBeenLastCalledWith({ view: 'done', limit: 10, offset: 20, status: 'completed' });
        await wrapper.find('.change-view').trigger('click');
        await flushPromises();
        expect(push).toHaveBeenLastCalledWith({ name: 'sw.approval.flow.started', query: { limit: '10' } });
    });

    it('keeps pagination available when the selected page is past the last result', async () => {
        const { wrapper } = await createWrapper(jest.fn().mockResolvedValue({ data: [], meta: { total: 1 } }));

        expect(wrapper.findComponent('.sw-pagination').exists()).toBe(true);
        expect(wrapper.find('.empty').exists()).toBe(false);
    });

    it('shows a load error with retry instead of presenting failure as an empty inbox', async () => {
        const search = jest
            .fn()
            .mockRejectedValueOnce(new Error('offline'))
            .mockResolvedValueOnce({ data: [], meta: { total: 0 } });
        const { wrapper } = await createWrapper(search);

        expect(wrapper.find('[role="alert"]').exists()).toBe(true);
        expect(wrapper.find('.empty').exists()).toBe(false);
        await wrapper.find('[role="alert"] button').trigger('click');
        await flushPromises();
        expect(wrapper.find('[role="alert"]').exists()).toBe(false);
        expect(wrapper.find('.empty').exists()).toBe(true);
    });

    it('discards a slower response from the previous inbox', async () => {
        let resolveFirst!: (page: ApprovalPage) => void;
        const first = new Promise<ApprovalPage>((resolve) => {
            resolveFirst = resolve;
        });
        const search = jest
            .fn()
            .mockReturnValueOnce(first)
            .mockResolvedValueOnce({ data: [{ ...request, id: 'new', title: 'Latest inbox' }], meta: { total: 1 } });
        const { wrapper } = await createWrapper(search);
        await wrapper.setProps({ view: 'started' });
        await flushPromises();
        resolveFirst({ data: [{ ...request, title: 'Stale inbox' }], meta: { total: 1 } });
        await flushPromises();

        expect(wrapper.text()).toContain('Latest inbox');
        expect(wrapper.text()).not.toContain('Stale inbox');
    });

    it('keeps the real paginator on the requested page during a delayed reload', async () => {
        let resolvePage!: (page: ApprovalPage) => void;
        const search = jest
            .fn()
            .mockResolvedValueOnce({ data: [request], meta: { total: 75 } })
            .mockReturnValueOnce(
                new Promise<ApprovalPage>((resolve) => {
                    resolvePage = resolve;
                }),
            );
        const { wrapper, route } = await createWrapper(search);
        route.query = { page: '2' };
        await flushPromises();
        resolvePage({ data: [request], meta: { total: 75 } });
        await flushPromises();

        expect(wrapper.findComponent('.sw-pagination').find('.is-active').text()).toBe('2');
    });

    it('keeps filters in the URL while paging and returns to the first page after filtering', async () => {
        const { wrapper, route, push } = await createWrapper(
            jest.fn().mockResolvedValue({ data: [request], meta: { total: 75 } }),
        );
        route.query = { page: '2', limit: '25', status: 'running' };
        await flushPromises();
        await wrapper.find('.sw-pagination__page-button-next').trigger('click');
        expect(push).toHaveBeenLastCalledWith({
            name: 'sw.approval.flow.todo',
            query: { page: '3', limit: '25', status: 'running' },
        });
        await wrapper.find('select.sw-approval-flow-list__status').setValue('suspended');
        expect(push).toHaveBeenLastCalledWith({
            name: 'sw.approval.flow.todo',
            query: { limit: '25', status: 'suspended' },
        });
    });
});
