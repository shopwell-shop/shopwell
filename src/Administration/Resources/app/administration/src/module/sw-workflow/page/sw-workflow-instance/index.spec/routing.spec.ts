/** @sw-package framework */
import { reactive } from 'vue';
import { repository, createWrapper, task, instance } from './create-wrapper';
describe('workflow instance contract', () => {
    it('shows a loading indicator without inventing an instance status or empty form', async () => {
        let resolve!: (value: typeof instance) => void;
        const pending = new Promise<typeof instance>((done) => {
            resolve = done;
        });
        const wrapper = await createWrapper(pending);
        expect(wrapper.find('.approval-loading').exists()).toBe(true);
        expect(wrapper.find('.sw-workflow-instance__status').exists()).toBe(false);
        expect(wrapper.find('.sw-workflow-instance__form').exists()).toBe(false);
        resolve(instance);
        await flushPromises();
        expect(wrapper.find('.approval-loading').exists()).toBe(false);
        expect(wrapper.text()).toContain('Equipment');
        wrapper.unmount();
    });

    it('clears the previous instance and loads the next route without remounting', async () => {
        const route = reactive({
            name: 'sw.workflow.instance',
            params: { id: 'instance' },
            query: {},
        });
        const wrapper = await createWrapper({ ...instance, status: 'AGREE', tasks: [] }, route);
        expect(repository.getInstance).toHaveBeenCalledTimes(1);
        let resolveNext!: (value: typeof instance) => void;
        jest.mocked(repository.getInstance).mockReturnValueOnce(
            new Promise((resolve) => {
                resolveNext = resolve;
            }),
        );
        route.params.id = 'next';
        await flushPromises();
        expect(repository.getInstance).toHaveBeenLastCalledWith('next');
        expect(wrapper.text()).not.toContain('Equipment');
        expect(wrapper.find('.approval-actions').exists()).toBe(false);
        resolveNext({ ...instance, id: 'next', title: 'Next request' });
        await flushPromises();
        expect(wrapper.text()).toContain('Next request');
        expect(wrapper.find('.approval-actions').exists()).toBe(true);
        wrapper.unmount();
    });

    it.each(['resolve', 'reject'])('ignores an old load that finishes with %s after navigation', async (outcome) => {
        let resolveOld!: (value: typeof instance) => void;
        let rejectOld!: (error: Error) => void;
        const oldLoad = new Promise<typeof instance>((resolve, reject) => {
            resolveOld = resolve;
            rejectOld = reject;
        });
        const route = reactive({
            name: 'sw.workflow.instance',
            params: { id: 'instance' },
            query: {},
        });
        const wrapper = await createWrapper(oldLoad, route);
        jest.mocked(repository.getInstance).mockResolvedValueOnce({
            ...instance,
            id: 'next',
            title: 'Next request',
        });
        route.params.id = 'next';
        await flushPromises();
        if (outcome === 'resolve') resolveOld({ ...instance, title: 'Old request' });
        else rejectOld(new Error('Old request failed'));
        await flushPromises();
        expect(wrapper.text()).toContain('Next request');
        expect(wrapper.text()).not.toContain('Old request');
        expect(wrapper.find('.approval-actions').exists()).toBe(true);
        wrapper.unmount();
    });

    it('does not fetch another route id when leaving the detail', async () => {
        const route = reactive({
            name: 'sw.workflow.instance',
            params: { id: 'instance' },
            query: {},
        });
        const wrapper = await createWrapper(instance, route);
        route.name = 'sw.product.detail';
        route.params.id = 'product';
        await flushPromises();
        expect(repository.getInstance).toHaveBeenCalledTimes(1);
        expect(wrapper.find('.approval-actions').exists()).toBe(false);
        wrapper.unmount();
    });

    it('keeps the next route loading when the previous load completes first', async () => {
        let resolveOld!: (value: typeof instance) => void;
        let resolveNext!: (value: typeof instance) => void;
        const oldLoad = new Promise<typeof instance>((resolve) => {
            resolveOld = resolve;
        });
        const route = reactive({
            name: 'sw.workflow.instance',
            params: { id: 'instance' },
            query: {},
        });
        const wrapper = await createWrapper(oldLoad, route);
        jest.mocked(repository.getInstance).mockReturnValueOnce(
            new Promise((resolve) => {
                resolveNext = resolve;
            }),
        );
        route.params.id = 'next';
        await flushPromises();
        resolveOld({ ...instance, title: 'Old request' });
        await flushPromises();
        expect(wrapper.text()).not.toContain('Old request');
        expect(wrapper.find('.approval-loading').exists()).toBe(true);
        expect(wrapper.find('.approval-actions').exists()).toBe(false);
        resolveNext({ ...instance, id: 'next', title: 'Next request' });
        await flushPromises();
        expect(wrapper.find('.approval-loading').exists()).toBe(false);
        expect(wrapper.text()).toContain('Next request');
        wrapper.unmount();
    });

    it('keeps a new decision in flight when an earlier route decision completes', async () => {
        const route = reactive({
            name: 'sw.workflow.instance',
            params: { id: 'instance' },
            query: {},
        });
        const wrapper = await createWrapper(instance, route);
        let resolveAction!: (value?: void) => void;
        jest.mocked(repository.approveTask).mockReturnValueOnce(
            new Promise((resolve) => {
                resolveAction = resolve;
            }),
        );
        await wrapper.get('.approval-actions button').trigger('click');
        jest.mocked(repository.getInstance).mockResolvedValueOnce({
            ...instance,
            id: 'next',
            title: 'Next request',
        });
        route.params.id = 'next';
        await flushPromises();
        expect(wrapper.find('.approval-actions').exists()).toBe(true);
        let resolveNextAction!: (value?: void) => void;
        jest.mocked(repository.approveTask).mockReturnValueOnce(
            new Promise((resolve) => {
                resolveNextAction = resolve;
            }),
        );
        await wrapper.get('.approval-actions button').trigger('click');
        await flushPromises();
        resolveAction();
        await flushPromises();
        expect(repository.getInstance).toHaveBeenCalledTimes(2);
        expect(wrapper.text()).toContain('Next request');
        expect(wrapper.find('.approval-actions').exists()).toBe(false);
        jest.mocked(repository.getInstance).mockResolvedValueOnce({
            ...instance,
            id: 'next',
            title: 'Next request',
            status: 'AGREE',
            tasks: [],
        });
        resolveNextAction();
        await flushPromises();
        expect(repository.getInstance).toHaveBeenCalledTimes(3);
        wrapper.unmount();
    });

    it('shows authorized form labels without fetching the definition and allows the assigned todo task', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.text()).toContain('Request reason');
        expect(wrapper.text()).toContain('Equipment');
        expect(wrapper.find('.approval-actions').exists()).toBe(true);
        wrapper.unmount();
    });

    it('uses the full task snapshot for participants after approval ends', async () => {
        const approvalTasks = [
            { ...task, state: 'agreed', actionable: false },
            {
                ...task,
                id: 'other',
                state: 'canceled',
                actionable: false,
                assignee: { id: 'other' },
            },
        ];
        const wrapper = await createWrapper({
            ...instance,
            status: 'AGREE',
            tasks: [],
            approvalTasks,
        });
        expect(wrapper.findComponent({ name: 'sw-workflow-runtime-viewer' }).props('tasks')).toEqual(approvalTasks);
        expect(wrapper.find('.approval-actions').exists()).toBe(false);
        wrapper.unmount();
    });

    it('reloads a stale task after a conflicting decision and removes its controls', async () => {
        const wrapper = await createWrapper();
        jest.mocked(repository.approveTask).mockRejectedValue(new Error('Task already handled'));
        jest.mocked(repository.getInstance).mockResolvedValue({ ...instance, status: 'REJECT', tasks: [] });
        await wrapper.get('.approval-actions button').trigger('click');
        await flushPromises();
        expect(repository.getInstance).toHaveBeenCalledTimes(2);
        expect(wrapper.find('.approval-actions').exists()).toBe(false);
        wrapper.unmount();
    });

    it('submits only the current user task, then shows the refreshed participant state', async () => {
        const otherTask = { ...task, id: 'other', assignee: { id: 'other' } };
        const wrapper = await createWrapper({
            ...instance,
            tasks: [otherTask, task],
        });
        jest.mocked(repository.approveTask).mockResolvedValue(undefined);
        jest.mocked(repository.getInstance).mockResolvedValue({ ...instance, tasks: [otherTask] });
        await wrapper.get('.approval-actions button').trigger('click');
        await flushPromises();
        expect(repository.approveTask).toHaveBeenCalledWith('task', '');
        expect(wrapper.find('.approval-actions').exists()).toBe(false);
        wrapper.unmount();
    });

    it.each([
        ['AGREE', 'approved'],
        ['REJECT', 'rejected'],
        ['CANCEL', 'canceled'],
    ])('renders terminal %s as translated %s and hides decisions', async (status, snippet) => {
        const wrapper = await createWrapper({ ...instance, status });
        expect(wrapper.find('.sw-workflow-instance__status').text()).toBe(`sw-workflow.runtime.status.${snippet}`);
        expect(wrapper.find('.approval-actions').exists()).toBe(false);
        wrapper.unmount();
    });

    it('does not offer decisions for another assignee or a completed task', async () => {
        const wrapper = await createWrapper({
            ...instance,
            tasks: [
                { ...task, assignee: { id: 'other' } },
                { ...task, id: 'done', actionable: false, state: 'agreed' },
            ],
        });
        expect(wrapper.find('.approval-actions').exists()).toBe(false);
        wrapper.unmount();
    });
});
