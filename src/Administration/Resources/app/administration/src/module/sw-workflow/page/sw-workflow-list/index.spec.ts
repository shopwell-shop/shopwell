/** @sw-package framework */
import { shallowMount, flushPromises } from '@vue/test-utils';
import { AxiosError } from 'axios';

async function createWrapper(
    deleteDraft = jest.fn().mockResolvedValue(undefined),
    allowed = true,
    routeName = 'sw.workflow.index',
) {
    const rows = Object.assign([{ id: 'draft', name: 'Review', revision: 4, createdAt: '2026-01-01', updatedAt: null }], {
        total: 1,
    });
    const search = jest.fn().mockResolvedValue(rows);
    const push = jest.fn();
    const wrapper = shallowMount(await wrapTestComponent('sw-workflow-list', { sync: true }), {
        global: {
            mocks: { $router: { push }, $route: { name: routeName } },
            provide: {
                repositoryFactory: { create: () => ({ search }) },
                workflowService: { deleteDraft },
                acl: { can: () => allowed },
            },
            stubs: {
                'sw-search-bar': {
                    name: 'sw-search-bar',
                    props: ['initialSearchType'],
                    template: '<input @input="$emit(\'search\', $event.target.value)" />',
                },
                'mt-tabs': {
                    props: ['items'],
                    template:
                        '<div role="tablist"><button v-for="item in items" :key="item.name" role="tab" @click="item.onClick()">{{ item.label }}</button></div>',
                },
                'sw-page': {
                    template:
                        '<div><slot name="search-bar" /><slot name="smart-bar-header" /><slot name="smart-bar-actions" /><slot name="content" /></div>',
                },
                'sw-data-grid': {
                    props: ['dataSource'],
                    template:
                        '<div><div v-for="item in dataSource" :key="item.id"><slot name="column-name" :item="item" /><slot name="actions" :item="item" /></div><slot name="pagination" /></div>',
                },
                'sw-context-menu-item': {
                    props: ['disabled'],
                    template: '<button class="delete" :disabled="disabled"><slot /></button>',
                },
                'sw-pagination': {
                    template:
                        '<button class="next-page" @click="$emit(\'page-change\', { page: 2, limit: 25 })">Next</button>',
                },
                'sw-modal': { template: '<div role="dialog"><slot /><slot name="modal-footer" /></div>' },
                'mt-button': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
            },
        },
    });
    await flushPromises();
    return { wrapper, deleteDraft, search, push };
}

describe('workflow list', () => {
    it('searches workflow drafts from the standard page search instead of global search', async () => {
        const { wrapper, search } = await createWrapper();
        const bar = wrapper.findComponent({ name: 'sw-search-bar' });
        expect(bar.props('initialSearchType')).toBe('workflow_draft');
        await bar.find('input').setValue('Expense');
        await flushPromises();
        expect(search).toHaveBeenLastCalledWith(expect.objectContaining({ term: 'Expense', page: 1 }), Shopwell.Context.api);
        wrapper.unmount();
    });

    it('opens the workflow designer from the standard create action', async () => {
        const { wrapper, push } = await createWrapper();
        await wrapper.find('.sw-workflow-list__create').trigger('click');
        expect(push).toHaveBeenCalledWith({ name: 'sw.workflow.create' });
        expect(wrapper.find('.sw-page__smart-bar-amount').text()).toBe('(1)');
        wrapper.unmount();
    });

    it('keeps publication history free from a stale draft count and draft requests', async () => {
        const { wrapper, search } = await createWrapper(undefined, true, 'sw.workflow.releases');
        expect(wrapper.find('.sw-page__smart-bar-amount').exists()).toBe(false);
        expect(search).not.toHaveBeenCalled();
        wrapper.unmount();
    });

    it('returns to an available page when another user empties the last page', async () => {
        const { wrapper, search } = await createWrapper();
        search.mockResolvedValueOnce(Object.assign([], { total: 25 }));
        await wrapper.find('.next-page').trigger('click');
        await flushPromises();
        expect(search).toHaveBeenCalledTimes(3);
        expect(search.mock.calls[2]).toEqual([expect.objectContaining({ page: 1 }), Shopwell.Context.api]);
        expect(wrapper.find('.delete').exists()).toBe(true);
        wrapper.unmount();
    });

    it('deletes only after confirmation and supplies the displayed revision', async () => {
        const { wrapper, deleteDraft, search } = await createWrapper();
        await wrapper.find('.delete').trigger('click');
        expect(deleteDraft).not.toHaveBeenCalled();
        await wrapper.findAll('[role="dialog"] button')[1].trigger('click');
        await flushPromises();
        expect(deleteDraft).toHaveBeenCalledWith('draft', 4);
        expect(search).toHaveBeenCalledTimes(2);
        wrapper.unmount();
    });

    it('preserves the row and reports a stale delete', async () => {
        const error = new AxiosError('Conflict');
        Object.assign(error, { response: { status: 409 } });
        const { wrapper, search } = await createWrapper(jest.fn().mockRejectedValue(error));
        await wrapper.find('.delete').trigger('click');
        await wrapper.findAll('[role="dialog"] button')[1].trigger('click');
        await flushPromises();
        expect(wrapper.find('[role="alert"]').exists()).toBe(true);
        expect(wrapper.find('.delete').exists()).toBe(true);
        expect(search).toHaveBeenCalledTimes(1);
        wrapper.unmount();
    });

    it('does not offer create or delete to a reader', async () => {
        const { wrapper, push } = await createWrapper(undefined, false);
        await wrapper.find('.sw-workflow-list__create').trigger('click');
        expect(push).not.toHaveBeenCalled();
        expect(wrapper.find('.sw-workflow-list__create').attributes('disabled')).toBeDefined();
        expect(wrapper.find('.delete').attributes('disabled')).toBeDefined();
        wrapper.unmount();
    });
});
