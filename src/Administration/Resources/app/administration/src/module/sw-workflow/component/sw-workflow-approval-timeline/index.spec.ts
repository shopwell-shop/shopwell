/** @sw-package framework */
import { shallowMount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';

describe('workflow approval history', () => {
    it('orders completed decisions chronologically and hides pending after completion', async () => {
        const wrapper: VueWrapper = shallowMount(await wrapTestComponent('sw-workflow-approval-timeline', { sync: true }), {
            props: {
                history: [
                    {
                        id: '2',
                        completedAt: '2026-11-13T11:00:00Z',
                        assignee: { name: 'Bob' },
                        decision: 'reject',
                        comment: 'Incorrect',
                    },
                    { id: '1', completedAt: '2026-11-13T10:00:00Z', assignee: { name: 'Alice' }, decision: 'approve' },
                ],
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
