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

async function createWrapper(load = jest.fn().mockResolvedValue(detail)) {
    const wrapper = shallowMount(await wrapTestComponent('sw-approval-flow-detail', { sync: true }), {
        props: { requestId: 'request-one' },
        global: {
            provide: { approvalFlowApiService: { detail: load }, acl: { can: () => true } },
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
});
