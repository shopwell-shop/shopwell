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
});
