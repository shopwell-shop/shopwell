/** @sw-package framework */
import type { RuntimeApprovalReminder } from 'src/core/workflow/workflow-runtime.types';
import { shallowMount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';

describe('approval deadline and reminder receipt', () => {
    it('shows the server overdue state and reminder source, and hides an empty receipt', async () => {
        const reminder: RuntimeApprovalReminder = {
            dueAt: '2026-11-17T10:00:00Z',
            overdue: true,
            lastRemindedAt: '2026-11-17T10:05:00Z',
            source: 'overdue',
            deliveryPending: false,
            count: 1,
            canRemind: false,
        };
        const wrapper: VueWrapper = shallowMount(
            await wrapTestComponent('sw-workflow-approval-reminder-status', { sync: true }),
            {
                props: {
                    reminder,
                },
            },
        );
        expect(wrapper.text()).toContain('sw-workflow.reminder.overdue');
        expect(wrapper.text()).toContain('sw-workflow.reminder.automaticAt');
        await wrapper.setProps({ reminder: { ...reminder, overdue: false, source: 'manual' } });
        expect(wrapper.text()).not.toContain('sw-workflow.reminder.overdue');
        expect(wrapper.text()).toContain('sw-workflow.reminder.remindedAt');
        await wrapper.setProps({ reminder: null });
        expect(wrapper.find('.sw-workflow-approval-reminder-status').exists()).toBe(false);
        wrapper.unmount();
    });
});
