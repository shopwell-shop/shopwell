/** @sw-package framework */
import { shallowMount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import type { RuntimeInstance } from 'src/core/workflow/workflow-runtime.types';

describe('workflow approval history', () => {
    it('orders completed decisions chronologically and hides pending after completion', async () => {
        const history = [
            {
                id: '2',
                taskId: 'task-2',
                round: 1,
                nodeId: 'review',
                nodeName: 'Review',
                countersignType: 'single',
                completedAt: '2026-11-13T11:00:00Z',
                assignee: { id: 'bob', name: 'Bob' },
                decision: 'reject',
                comment: 'Incorrect',
            },
            {
                id: '1',
                taskId: 'task-1',
                round: 1,
                nodeId: 'review',
                nodeName: 'Review',
                countersignType: 'single',
                completedAt: '2026-11-13T10:00:00Z',
                assignee: { id: 'alice', name: 'Alice' },
                decision: 'approve',
                comment: null,
            },
        ];
        const instance: Pick<RuntimeInstance, 'history'> = { history };
        const wrapper: VueWrapper = shallowMount(await wrapTestComponent('sw-workflow-approval-timeline', { sync: true }), {
            props: {
                history: instance.history,
                currentNode: { id: 'review', name: 'Review', enteredAt: null },
            },
        });
        expect(wrapper.findAll('.sw-workflow-approval-timeline__name').map((item) => item.text())).toEqual([
            'Alice',
            'Bob',
            'Review',
        ]);
        expect(wrapper.find('.is--rejected').text()).toContain('Incorrect');
        await wrapper.setProps({ status: 'REJECT' });
        expect(wrapper.find('.is--pending').exists()).toBe(false);
        wrapper.unmount();
    });
    it('distinguishes automatic approval evidence from a manual decision', async () => {
        const wrapper = shallowMount(await wrapTestComponent('sw-workflow-approval-timeline', { sync: true }), {
            props: {
                history: [
                    {
                        id: 'automatic',
                        taskId: 'task',
                        round: 1,
                        nodeId: 'review',
                        nodeName: 'Review',
                        countersignType: 'single',
                        completedAt: null,
                        assignee: { id: 'alice', name: 'Alice' },
                        decision: 'agree',
                        comment: null,
                        automatic: true,
                        automaticReason: 'initiator',
                    },
                ],
            },
        });
        expect(wrapper.find('.is--agreed').exists()).toBe(true);
        expect(wrapper.text()).toContain('sw-workflow.automation.automaticApproval');
        expect(wrapper.text()).toContain('sw-workflow.automation.reasons.initiator');
        expect(wrapper.text()).not.toContain('sw-workflow.runtime.timeline.agreed');
        wrapper.unmount();
    });

    it.each(['transfer', 'vendor:operation'])('renders %s as an operation instead of an approval', async (decision) => {
        const wrapper: VueWrapper = shallowMount(await wrapTestComponent('sw-workflow-approval-timeline', { sync: true }), {
            props: {
                history: [
                    {
                        id: 'operation',
                        taskId: 'task',
                        round: 1,
                        nodeId: 'review',
                        nodeName: 'Review',
                        countersignType: 'single',
                        assignee: { id: 'actor', name: 'Alice' },
                        target: { id: 'recipient', name: 'Bob' },
                        decision,
                        comment: 'Expert',
                        completedAt: null,
                    },
                ],
            },
        });
        expect(wrapper.find('.is--agreed').exists()).toBe(false);
        expect(wrapper.text()).toContain(decision === 'transfer' ? 'sw-workflow.runtime.timeline.transfer' : decision);
        expect(wrapper.text()).toContain('Bob');
        wrapper.unmount();
    });
    it('shows reminders as audit operations without turning them into approval decisions', async () => {
        const wrapper: VueWrapper = shallowMount(await wrapTestComponent('sw-workflow-approval-timeline', { sync: true }), {
            props: {
                logs: [
                    {
                        id: 'reminder',
                        action: 'remind',
                        operator: { id: 'initiator', name: 'Alice' },
                        createdAt: '2026-11-17T10:00:00Z',
                        comment: null,
                        payload: { round: 1 },
                    },
                ],
                history: [],
            },
        });
        expect(wrapper.findAll('.is--operation')).toHaveLength(1);
        expect(wrapper.find('.is--agreed').exists()).toBe(false);
        expect(wrapper.text()).toContain('Alice');
        expect(wrapper.text()).toContain('sw-workflow.runtime.timeline.remind');
        await wrapper.setProps({
            logs: [
                {
                    id: 'automatic-reminder',
                    action: 'remind',
                    operator: null,
                    createdAt: '2026-11-17T10:00:00Z',
                    comment: null,
                    payload: { round: 1 },
                },
            ],
        });
        expect(wrapper.text()).toContain('sw-workflow.reminder.system');
        wrapper.unmount();
    });
});
