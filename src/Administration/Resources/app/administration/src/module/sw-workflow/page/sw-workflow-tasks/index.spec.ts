/** @sw-package framework */
import { mount, shallowMount, flushPromises } from '@vue/test-utils';

const repository = {
    listTodo: jest.fn(),
    listDone: jest.fn(),
    listInitiated: jest.fn(),
    listCopies: jest.fn(),
    markCopyRead: jest.fn(),
};

describe('runtime task presenter contract', () => {
    it('updates the header count for the active queue and hides it after a load failure', async () => {
        jest.mocked(repository.listTodo).mockResolvedValue({ data: [], meta: { total: 3 } });
        jest.mocked(repository.listDone).mockResolvedValue({ data: [], meta: { total: 0 } });
        jest.mocked(repository.listInitiated).mockRejectedValueOnce(new Error('Unavailable'));
        const wrapper = shallowMount(await wrapTestComponent('sw-workflow-tasks', { sync: true }), {
            global: {
                provide: { workflowRuntimeApiService: repository, acl: { can: () => false } },
                stubs: {
                    'sw-page': { template: '<div><slot name="smart-bar-header" /><slot name="content" /></div>' },
                    'mt-tabs': {
                        props: ['items'],
                        template:
                            '<div><button v-for="item in items" :key="item.name" role="tab" @click="item.onClick()">{{ item.label }}</button></div>',
                    },
                },
            },
        });
        await flushPromises();
        expect(wrapper.get('.sw-page__smart-bar-amount').text()).toBe('(3)');
        await wrapper.findAll('[role="tab"]')[1].trigger('click');
        await flushPromises();
        expect(repository.listDone).toHaveBeenCalledWith({ limit: 25, offset: 0 });
        expect(wrapper.get('.sw-page__smart-bar-amount').text()).toBe('(0)');
        await wrapper.findAll('[role="tab"]')[2].trigger('click');
        await flushPromises();
        expect(wrapper.find('.sw-page__smart-bar-amount').exists()).toBe(false);
        expect(wrapper.get('[role="alert"]').text()).toContain('sw-workflow.runtime.todo.loadError');
        wrapper.unmount();
    });

    it('refreshes a cached queue when returning from a decision', async () => {
        jest.mocked(repository.listTodo)
            .mockResolvedValueOnce({
                data: [
                    {
                        id: 'task',
                        nodeId: 'review',
                        nodeName: 'Review',
                        round: 1,
                        countersignType: 'single',
                        state: 'todo',
                        assignee: { id: 'me' },
                    },
                ],
                meta: { total: 1 },
            })
            .mockResolvedValueOnce({ data: [], meta: { total: 0 } });
        const list = await wrapTestComponent('sw-workflow-tasks', { sync: true });
        const wrapper = mount(
            {
                props: { visible: { type: Boolean, default: true } },
                components: { ApprovalList: list },
                template: '<keep-alive><approval-list v-if="visible" /></keep-alive>',
            },
            {
                global: {
                    provide: { workflowRuntimeApiService: repository, acl: { can: () => true } },
                    stubs: {
                        'mt-tabs': {
                            props: ['items'],
                            template:
                                '<div role="tablist"><button v-for="item in items" :key="item.name" role="tab" @click="item.onClick()">{{ item.label }}</button></div>',
                        },
                        'sw-page': { template: '<div />' },
                    },
                },
            },
        );
        await flushPromises();
        expect(repository.listTodo).toHaveBeenCalledTimes(1);
        await wrapper.setProps({ visible: false });
        await wrapper.setProps({ visible: true });
        await flushPromises();
        expect(repository.listTodo).toHaveBeenCalledTimes(2);
        wrapper.unmount();
    });

    it('opens the presented task ID and displays its creation time', async () => {
        const row = {
            id: 'task',
            nodeId: 'review',
            nodeName: 'Review',
            round: 1,
            countersignType: 'single',
            assignee: { id: 'me' },
            instanceId: 'instance',
            state: 'todo',
            actionable: true,
            createdAt: '2026-11-13T00:00:00Z',
        };
        jest.mocked(repository.listTodo).mockResolvedValue({ data: [row], meta: { total: 1 } });
        const push = jest.fn();
        const wrapper = shallowMount(await wrapTestComponent('sw-workflow-tasks', { sync: true }), {
            global: {
                provide: { workflowRuntimeApiService: repository, acl: { can: () => true } },
                mocks: { $router: { push } },
                stubs: {
                    'mt-tabs': {
                        props: ['items'],
                        template:
                            '<div role="tablist"><button v-for="item in items" :key="item.name" role="tab" @click="item.onClick()">{{ item.label }}</button></div>',
                    },
                    'sw-page': { template: '<div><slot name="content" /></div>' },
                    'sw-data-grid': {
                        props: ['dataSource'],
                        template:
                            '<div v-if="dataSource.length"><slot name="column-title" :item="dataSource[0]"/><span class="time"><slot name="column-startedAt" :item="dataSource[0]"/></span></div>',
                    },
                },
            },
        });
        await flushPromises();
        expect(wrapper.find('.time').text()).not.toBe('—');
        await wrapper.find('.sw-workflow-tasks__link').trigger('click');
        expect(push).toHaveBeenCalledWith({
            name: 'sw.workflow.instance',
            params: { id: 'instance' },
            query: { taskId: 'task' },
        });
        wrapper.unmount();
    });
    it('lists requests started by the current user and opens the instance without a task query', async () => {
        jest.mocked(repository.listTodo).mockResolvedValue({ data: [], meta: { total: 0 } });
        jest.mocked(repository.listInitiated).mockResolvedValue({
            data: [{ id: 'mine', title: 'My request', status: 'RUNNING' }],
            meta: { total: 1 },
        });
        const push = jest.fn();
        const wrapper = shallowMount(await wrapTestComponent('sw-workflow-tasks', { sync: true }), {
            global: {
                provide: { workflowRuntimeApiService: repository, acl: { can: () => false } },
                mocks: { $router: { push } },
                stubs: {
                    'mt-tabs': {
                        props: ['items'],
                        template:
                            '<div role="tablist"><button v-for="item in items" :key="item.name" role="tab" @click="item.onClick()">{{ item.label }}</button></div>',
                    },
                    'sw-page': { template: '<div><slot name="content" /></div>' },
                    'sw-data-grid': {
                        props: ['dataSource'],
                        template:
                            '<section><div v-for="item in dataSource" :key="item.id"><slot name="column-title" :item="item" /></div></section>',
                    },
                },
            },
        });
        await flushPromises();
        await wrapper.findAll('[role="tab"]')[2].trigger('click');
        await flushPromises();
        expect(repository.listInitiated).toHaveBeenCalledWith({ limit: 25, offset: 0 });
        await wrapper.get('.sw-workflow-tasks__link').trigger('click');
        expect(push).toHaveBeenCalledWith({ name: 'sw.workflow.instance', params: { id: 'mine' }, query: {} });
        wrapper.unmount();
    });
});

describe('queue endpoint projections', () => {
    it.each([
        [
            'todo',
            {
                id: 'task',
                instanceId: 'instance',
                nodeId: 'review',
                nodeName: 'Review',
                round: 1,
                countersignType: 'single',
                state: 'todo',
                assignee: { id: 'me' },
                createdAt: null,
            },
            { taskId: 'task' },
        ],
        [
            'done',
            {
                id: 'history',
                taskId: 'task',
                instanceId: 'instance',
                nodeId: 'review',
                nodeName: 'Review',
                decision: 'agree',
                comment: null,
                completedAt: null,
            },
            { taskId: 'task' },
        ],
    ])('renders the %s endpoint projection without an optional instance summary', async (queue, row, query) => {
        repository.listTodo.mockResolvedValue({ data: queue === 'todo' ? [row] : [], meta: { total: 1 } });
        repository.listDone.mockResolvedValue({ data: [row], meta: { total: 1 } });
        const push = jest.fn();
        const wrapper = shallowMount(await wrapTestComponent('sw-workflow-tasks', { sync: true }), {
            global: {
                provide: { workflowRuntimeApiService: repository, acl: { can: () => true } },
                mocks: { $router: { push } },
                stubs: {
                    'sw-page': { template: '<div><slot name="content" /></div>' },
                    'mt-tabs': {
                        props: ['items'],
                        template:
                            '<div><button v-for="item in items" :key="item.name" role="tab" @click="item.onClick()">{{ item.label }}</button></div>',
                    },
                    'sw-data-grid': {
                        props: ['dataSource'],
                        template:
                            '<div><section v-for="item in dataSource" :key="item.id"><slot name="column-title" :item="item" /><span class="initiator"><slot name="column-initiator" :item="item" /></span><span class="time"><slot name="column-startedAt" :item="item" /></span></section></div>',
                    },
                },
            },
        });
        await flushPromises();
        if (queue === 'done') {
            await wrapper.findAll('[role="tab"]')[1].trigger('click');
            await flushPromises();
        }
        expect(wrapper.get('.initiator').text()).toBe('—');
        expect(wrapper.get('.time').text()).toBe('—');
        expect(wrapper.get('.sw-workflow-tasks__link').text()).toBe('instance');
        await wrapper.get('.sw-workflow-tasks__link').trigger('click');
        expect(push).toHaveBeenCalledWith({ name: 'sw.workflow.instance', params: { id: 'instance' }, query });
        wrapper.unmount();
    });
});

describe('received copies', () => {
    it.each([
        { scenario: 'acknowledges an unread receipt before opening it', readAt: null, readFails: false },
        { scenario: 'opens a read receipt without acknowledging again', readAt: '2026-11-13T00:00:00Z', readFails: false },
        { scenario: 'keeps the queue open when acknowledgement fails', readAt: null, readFails: true },
    ])('$scenario', async ({ readAt, readFails }) => {
        repository.listTodo.mockResolvedValue({ data: [], meta: { total: 0 } });
        repository.listCopies.mockResolvedValue({
            data: [
                {
                    id: 'receipt',
                    instanceId: 'instance',
                    title: 'Copied request',
                    nodeName: 'Notify finance',
                    readAt,
                    createdAt: '2026-11-13T00:00:00Z',
                },
            ],
            meta: { total: 1 },
        });
        if (readFails) {
            repository.markCopyRead.mockRejectedValueOnce(new Error('Unavailable'));
        } else {
            repository.markCopyRead.mockResolvedValue(undefined);
        }
        const push = jest.fn();
        const wrapper = shallowMount(await wrapTestComponent('sw-workflow-tasks', { sync: true }), {
            global: {
                provide: { workflowRuntimeApiService: repository, acl: { can: () => false } },
                mocks: { $router: { push } },
                stubs: {
                    'sw-page': { template: '<div><slot name="content" /></div>' },
                    'mt-tabs': {
                        props: ['items'],
                        template:
                            '<div><button v-for="item in items" :key="item.name" role="tab" @click="item.onClick()">{{ item.label }}</button></div>',
                    },
                    'sw-data-grid': {
                        props: ['dataSource'],
                        template:
                            '<div><div v-for="item in dataSource" :key="item.id"><slot name="column-title" :item="item"/><span class="read"><slot name="column-readAt" :item="item"/></span></div></div>',
                    },
                },
            },
        });
        await flushPromises();
        const copyTab = wrapper.findAll('[role="tab"]').find((tab) => tab.text() === 'sw-workflow.runtime.todo.tabCopies');
        expect(copyTab).toBeDefined();
        await copyTab!.trigger('click');
        await flushPromises();
        expect(repository.listCopies).toHaveBeenCalledWith({ limit: 25, offset: 0 });
        expect(wrapper.get('.read').text()).toBe(
            readAt === null ? 'sw-workflow.runtime.todo.unread' : 'sw-workflow.runtime.todo.read',
        );
        await wrapper.get('.sw-workflow-tasks__link').trigger('click');
        await flushPromises();
        expect(repository.markCopyRead.mock.calls).toEqual(readAt === null ? [['receipt']] : []);
        const expectedNavigation = readFails
            ? []
            : [[{ name: 'sw.workflow.instance', params: { id: 'instance' }, query: { taskId: 'receipt' } }]];
        expect(push.mock.calls).toEqual(expectedNavigation);
        wrapper.unmount();
    });
});
