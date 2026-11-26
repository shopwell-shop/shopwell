/** @sw-package framework */
import { shallowMount, flushPromises, type VueWrapper } from '@vue/test-utils';
import type { ComponentPublicInstance } from 'vue';
import type { ApprovalDetail } from '../../service/approval-flow.api.service';

const detail: ApprovalDetail = {
    request: {
        id: 'request-one',
        title: 'Transfer approval',
        businessType: 'payment:transfer',
        businessId: 'transfer',
        submissionRound: 1,
        instanceId: 'instance',
        initiatorId: 'author',
        initiatorName: 'Alice',
        createdAt: '2026-11-24T08:00:00+00:00',
        status: 'running',
        outcome: null,
        aggregateVersion: 3,
    },
    form: { amount: 100, note: '<img src=x onerror=alert(1)>' },
    formLabels: {
        'form.amount': { title: 'Amount (minor units)', labelSnippet: null },
        'form.note': { title: 'Note', labelSnippet: null },
    },
    participantNames: { author: 'Alice', reviewer: 'Li' },
    executions: [{ id: 'execution', nodeId: 'review', sequence: 2, status: 'awaiting_approval', outcome: null }],
    tasks: [{ id: 'task', executionId: 'execution', assigneeId: 'reviewer', status: 'approved' }],
    returns: [],
    decisions: [
        {
            taskId: 'task',
            nodeId: 'review',
            assigneeId: 'reviewer',
            decision: 'approved',
            reason: 'Reviewed the amount.',
            decidedAt: '2026-11-24T09:00:00+00:00',
            aggregateVersion: 3,
        },
    ],
};

async function createWrapper(load = jest.fn().mockResolvedValue(detail), canDecide = true) {
    Shopwell.Store.get('session').currentUser = { id: 'reviewer' } as Entity<'user'>;
    const wrapper = shallowMount(await wrapTestComponent('sw-approval-flow-detail', { sync: true }), {
        props: { requestId: 'request-one' },
        global: {
            provide: {
                approvalFlowApiService: { detail: load },
                acl: { can: (privilege: string) => privilege !== 'approval_flow.editor' || canDecide },
            },
            mocks: { $route: { query: { view: 'done', page: '2' } }, $te: () => false },
            stubs: {
                'sw-page': {
                    template:
                        '<div><slot name="smart-bar-back"/><slot name="smart-bar-header"/><slot name="smart-bar-actions"/><slot name="content"/></div>',
                },
                'sw-card-view': { template: '<div><slot/></div>' },
                'mt-card': { props: ['title'], template: '<section><h3>{{ title }}</h3><slot/></section>' },
                'router-link': { props: ['to'], template: '<a :data-route="to.name"><slot/></a>' },
                'mt-button': { template: '<button><slot/></button>' },
                'mt-banner': { template: '<section role="alert"><slot/></section>' },
                'sw-loader': true,
                'sw-approval-flow-status-badge': true,
                'sw-approval-flow-decision-modal': {
                    props: ['taskId', 'action'],
                    template: `<div class="decision-dialog"><span>{{ taskId }}:{{ action }}</span><button class="commit-decision" @click="$emit('completed')">Confirm</button></div>`,
                },
            },
        },
    });
    await flushPromises();
    return { wrapper: wrapper as unknown as VueWrapper<ComponentPublicInstance<{ requestId: string }>>, load };
}

describe('approval detail', () => {
    it('renders only the returned form, participant names and committed opinions as text', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.text()).toContain('Transfer approval');
        expect(wrapper.text()).toContain('Alice');
        expect(wrapper.text()).toContain('Amount (minor units)');
        expect(wrapper.text()).toContain('Reviewed the amount.');
        expect(wrapper.text()).toContain('Li');
        expect(wrapper.text()).toContain('<img src=x onerror=alert(1)>');
        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('payment:transfer');
        expect(wrapper.find('a').attributes('data-route')).toBe('sw.approval.flow.done');
    });

    it('clears the previous request when another detail fails and offers a retry', async () => {
        const load = jest
            .fn()
            .mockResolvedValueOnce(detail)
            .mockRejectedValueOnce({ response: { status: 404 } })
            .mockResolvedValueOnce({ ...detail, request: { ...detail.request, title: 'Reloaded request' } });
        const { wrapper } = await createWrapper(load);
        await wrapper.setProps({ requestId: 'another-request' });
        await flushPromises();

        expect(wrapper.text()).not.toContain('Transfer approval');
        expect(wrapper.text()).toContain('sw-approval-flow.detail.notFound');
        await wrapper.find('[role="alert"] button').trigger('click');
        await flushPromises();
        expect(wrapper.text()).toContain('Reloaded request');
    });

    it('keeps empty or hidden forms empty instead of fetching unfiltered business data', async () => {
        const { wrapper, load } = await createWrapper(
            jest.fn().mockResolvedValue({ ...detail, form: {}, formLabels: {}, decisions: [] }),
        );

        expect(wrapper.text()).toContain('sw-approval-flow.detail.noVisibleFields');
        expect(wrapper.text()).toContain('sw-approval-flow.detail.noDecisions');
        expect(load).toHaveBeenCalledTimes(1);
        expect(wrapper.text()).not.toContain('Amount (minor units)');
    });

    it('ignores an old detail response after the user opens another request', async () => {
        let resolveFirst!: (value: ApprovalDetail) => void;
        const first = new Promise<ApprovalDetail>((resolve) => {
            resolveFirst = resolve;
        });
        const load = jest
            .fn()
            .mockReturnValueOnce(first)
            .mockResolvedValueOnce({ ...detail, request: { ...detail.request, title: 'New request' } });
        const { wrapper } = await createWrapper(load);
        await wrapper.setProps({ requestId: 'new-request' });
        await flushPromises();
        resolveFirst(detail);
        await flushPromises();

        expect(wrapper.text()).toContain('New request');
        expect(wrapper.text()).not.toContain('Transfer approval');
    });
    it('opens a confirmation only for the current user pending task with decision privilege', async () => {
        const pending: ApprovalDetail = { ...detail, tasks: [{ ...detail.tasks[0], status: 'pending' }] };
        const { wrapper } = await createWrapper(jest.fn().mockResolvedValue(pending));
        await wrapper.find('[data-testid="decision-approve"]').trigger('click');
        expect(wrapper.find('.decision-dialog').text()).toContain('task:approved');
    });

    it('refreshes pending tasks after a return and displays it alongside the past opinion', async () => {
        const pending: ApprovalDetail = {
            ...detail,
            tasks: [
                ...detail.tasks,
                { id: 'later', executionId: 'execution', assigneeId: 'reviewer', status: 'pending' },
            ],
        };
        const returned: ApprovalDetail = {
            ...detail,
            tasks: [{ ...pending.tasks[0] }, { ...pending.tasks[1], status: 'cancelled' }],
            returns: [
                {
                    taskId: 'later',
                    targetExecutionId: 'execution',
                    reason: 'Check the payee.',
                    returnedAt: null,
                    aggregateVersion: 4,
                },
            ],
        };
        const { wrapper, load } = await createWrapper(
            jest.fn().mockResolvedValueOnce(pending).mockResolvedValueOnce(returned),
        );
        await wrapper.find('[data-testid="decision-return"]').trigger('click');
        expect(wrapper.find('.decision-dialog').text()).toContain('later:return');
        await wrapper.find('.commit-decision').trigger('click');
        await flushPromises();

        expect(load).toHaveBeenCalledTimes(2);
        expect(wrapper.find('[data-testid="decision-return"]').exists()).toBe(false);
        expect(wrapper.findAll('.sw-approval-flow-detail__decisions li').map((row) => row.find('p').text())).toEqual([
            'Reviewed the amount.',
            'Check the payee.',
        ]);
    });

    it.each([
        'other-user',
        'completed',
        'suspended',
        'viewer',
    ])('does not offer a decision for %s', async (scenario) => {
        const pending: ApprovalDetail = {
            ...detail,
            request: { ...detail.request },
            tasks: [{ ...detail.tasks[0], status: 'pending' }],
        };
        if (scenario === 'other-user') {
            pending.tasks[0].assigneeId = 'someone-else';
        }
        if (scenario === 'completed') {
            pending.tasks[0].status = 'approved';
        }
        if (scenario === 'suspended') {
            pending.request.status = 'suspended';
        }
        const { wrapper } = await createWrapper(jest.fn().mockResolvedValue(pending), scenario !== 'viewer');
        expect(wrapper.find('[data-testid="decision-approve"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="decision-reject"]').exists()).toBe(false);
    });

    it('keeps a committed opinion distinct from a failed detail reload', async () => {
        const pending: ApprovalDetail = { ...detail, tasks: [{ ...detail.tasks[0], status: 'pending' }] };
        const load = jest
            .fn()
            .mockResolvedValueOnce(pending)
            .mockRejectedValueOnce(new Error('reload failed'))
            .mockResolvedValueOnce(detail);
        const { wrapper } = await createWrapper(load);
        await wrapper.find('[data-testid="decision-approve"]').trigger('click');
        await wrapper.find('.commit-decision').trigger('click');
        await flushPromises();
        expect(wrapper.find('.decision-dialog').exists()).toBe(false);
        expect(wrapper.text()).toContain('sw-approval-flow.decision.saved');
        expect(wrapper.text()).toContain('sw-approval-flow.decision.reloadError');
        await wrapper.find('[role="alert"] button').trigger('click');
        await flushPromises();
        expect(wrapper.text()).toContain('Reviewed the amount.');
        expect(wrapper.find('[data-testid="decision-approve"]').exists()).toBe(false);
    });
    it('clears the previous saved notice when starting an opinion on the next assigned task', async () => {
        const pending: ApprovalDetail = { ...detail, tasks: [{ ...detail.tasks[0], status: 'pending' }] };
        const next: ApprovalDetail = {
            ...pending,
            request: { ...pending.request, aggregateVersion: 4 },
            tasks: [{ ...pending.tasks[0], id: 'next-task' }],
        };
        const { wrapper } = await createWrapper(jest.fn().mockResolvedValueOnce(pending).mockResolvedValueOnce(next));
        await wrapper.find('[data-testid="decision-approve"]').trigger('click');
        await wrapper.find('.commit-decision').trigger('click');
        await flushPromises();
        expect(wrapper.text()).toContain('sw-approval-flow.decision.saved');
        await wrapper.find('[data-testid="decision-reject"]').trigger('click');
        expect(wrapper.text()).not.toContain('sw-approval-flow.decision.saved');
        expect(wrapper.find('.decision-dialog').text()).toContain('next-task:rejected');
    });
});
