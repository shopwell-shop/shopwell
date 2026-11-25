/** @sw-package framework */
import { shallowMount, flushPromises, type VueWrapper } from '@vue/test-utils';
import { reactive, ref, type ComponentPublicInstance } from 'vue';
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
                'mt-data-table': {
                    props: [
                        'dataSource',
                        'currentPage',
                        'paginationTotalItems',
                        'isLoading',
                        'searchValue',
                    ],
                    template: `
                        <div>
                            <input class="table-search" :value="searchValue" @input="$emit('search-value-change', $event.target.value)"/>
                            <slot name="toolbar"/>
                            <div v-for="data in dataSource" :key="data.id">
                                <slot name="column-title" :data="data"/>
                                <slot name="column-initiatorName" :data="data"/>
                                <slot name="column-actions" :data="data"/>
                            </div>
                            <slot v-if="!isLoading && dataSource.length === 0" name="empty-state"/>
                            <output class="table-page">{{ currentPage }}</output>
                            <button v-if="paginationTotalItems" class="next-page" @click="$emit('pagination-current-page-change', currentPage + 1)">Next</button>
                        </div>`,
                },
                'router-link': { props: ['to'], template: '<a :data-route="to.name"><slot/></a>' },
                'mt-button': {
                    props: ['is', 'to'],
                    template:
                        '<router-link v-if="is === \'router-link\'" :to="to"><slot/></router-link><button v-else><slot/></button>',
                },
                'mt-popover': {
                    setup() {
                        const open = ref(false);
                        const toggle = () => {
                            open.value = !open.value;
                        };

                        return { open, toggle };
                    },
                    template: `
                        <div>
                            <slot name="trigger" :toggleFloatingUi="toggle" :isOpened="open"/>
                            <slot v-if="open" name="popover-items__base" :toggleFloatingUi="toggle"/>
                        </div>`,
                },
                'mt-popover-item': {
                    props: ['label', 'onLabelClick'],
                    template: '<button class="status-option" :data-label="label" @click="onLabelClick">{{ label }}</button>',
                },
                'mt-icon': true,
                'mt-banner': { template: '<section role="alert"><slot/></section>' },
                'mt-empty-state': { props: ['headline'], template: '<p class="empty">{{ headline }}</p>' },
                'mt-tabs': {
                    template: `<button class="change-view" @click="$emit('new-item-active', 'started')">My requests</button>`,
                },
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
        expect(wrapper.findAll('a').map((link) => link.text())).toContain('global.default.view');
        expect(wrapper.find('.status-option').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('instance-one');
    });

    it('reads page and status from the route and resets them when changing inbox', async () => {
        const { wrapper, search, route, push } = await createWrapper();
        route.query = { page: '3', limit: '10', status: 'completed' };
        await wrapper.setProps({ view: 'done' });
        await flushPromises();

        expect(search).toHaveBeenLastCalledWith({ view: 'done', limit: 10, offset: 20, status: 'completed' });
        expect(wrapper.find('.status-option').exists()).toBe(false);
        await wrapper.get('button[aria-expanded="false"]').trigger('click');
        expect(wrapper.findAll('.status-option').map((option) => option.text())).toContain(
            'sw-approval-flow.status.completed',
        );
        await wrapper.find('.change-view').trigger('click');
        await flushPromises();
        expect(push).toHaveBeenLastCalledWith({ name: 'sw.approval.flow.started', query: { limit: '10' } });
    });

    it('uses the table search and returns to the first page of filtered results', async () => {
        const { wrapper, route, search, push } = await createWrapper();

        route.query = { term: 'Transfer', status: 'running', page: '3', limit: '10' };
        await flushPromises();

        expect(wrapper.get<HTMLInputElement>('.table-search').element.value).toBe('Transfer');
        expect(search).toHaveBeenLastCalledWith({
            view: 'todo',
            limit: 10,
            offset: 20,
            status: 'running',
            term: 'Transfer',
        });

        await wrapper.get('.table-search').setValue(' bank transfer ');
        expect(push).toHaveBeenLastCalledWith({
            name: 'sw.approval.flow.todo',
            query: { limit: '10', status: 'running', term: 'bank transfer' },
        });

        await wrapper.get('.table-search').setValue('');
        expect(push).toHaveBeenLastCalledWith({
            name: 'sw.approval.flow.todo',
            query: { limit: '10', status: 'running' },
        });
    });

    it('keeps pagination available when the selected page is past the last result', async () => {
        const { wrapper } = await createWrapper(jest.fn().mockResolvedValue({ data: [], meta: { total: 1 } }));

        expect(wrapper.get('.next-page').isVisible()).toBe(true);
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

    it('keeps the table on the requested page during a delayed reload', async () => {
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

        expect(wrapper.get('.table-page').text()).toBe('2');
    });

    it('keeps filters in the URL while paging and returns to the first page after filtering', async () => {
        const { wrapper, route, push } = await createWrapper(
            jest.fn().mockResolvedValue({ data: [request], meta: { total: 75 } }),
        );
        route.query = { page: '2', limit: '25', status: 'running' };
        await flushPromises();
        await wrapper.get('.next-page').trigger('click');
        expect(push).toHaveBeenLastCalledWith({
            name: 'sw.approval.flow.todo',
            query: { page: '3', limit: '25', status: 'running' },
        });
        await wrapper.get('button[aria-expanded="false"]').trigger('click');
        await wrapper.get('button[data-label="sw-approval-flow.status.suspended"]').trigger('click');
        expect(wrapper.find('.status-option').exists()).toBe(false);
        expect(push).toHaveBeenLastCalledWith({
            name: 'sw.approval.flow.todo',
            query: { limit: '25', status: 'suspended' },
        });
    });
});
