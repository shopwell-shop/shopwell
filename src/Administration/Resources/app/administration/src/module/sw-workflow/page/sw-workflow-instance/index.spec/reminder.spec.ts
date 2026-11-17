/** @sw-package framework */
import { flushPromises, type VueWrapper } from '@vue/test-utils';
import { repository, createWrapper, instance } from './create-wrapper';

describe('workflow initiator reminders', () => {
    it('shows current reminder receipts to the initiator without requiring an approval action', async () => {
        const wrapper = await createWrapper({
            ...instance,
            canCancel: true,
            canRemind: true,
            tasks: [
                {
                    ...instance.tasks![0],
                    actionable: false,
                    assignee: { id: 'recipient', name: 'Bob' },
                    reminder: {
                        dueAt: '2026-11-17T10:00:00Z',
                        overdue: false,
                        lastRemindedAt: null,
                        source: null,
                        count: 0,
                        canRemind: true,
                        deliveryPending: false,
                    },
                },
            ],
        });
        expect(wrapper.find('.sw-workflow-instance__reminders').exists()).toBe(true);
        expect(wrapper.find('[data-reminder-task="task"]').text()).toContain('Bob');
        wrapper.unmount();
    });

    it('reminds through the server action and reloads eligibility after success', async () => {
        const wrapper = await createWrapper({ ...instance, canCancel: true, canRemind: true });
        repository.remindInstance.mockResolvedValueOnce(undefined);
        repository.getInstance.mockResolvedValueOnce({ ...instance, canCancel: true, canRemind: false });
        await wrapper.get('.sw-workflow-instance__remind').trigger('click');
        await flushPromises();
        expect(repository.remindInstance).toHaveBeenCalledWith('instance');
        expect(wrapper.get('.sw-workflow-instance__remind').attributes('disabled')).toBeDefined();
        wrapper.unmount();
    });

    it('retains eligibility on a failed request and never submits during cooldown', async () => {
        const wrapper = await createWrapper({ ...instance, canCancel: true, canRemind: true });
        repository.remindInstance.mockRejectedValueOnce(new Error('Channel unavailable'));
        await wrapper.get('.sw-workflow-instance__remind').trigger('click');
        await flushPromises();
        expect(wrapper.get('.sw-workflow-instance__remind').attributes('disabled')).toBeUndefined();
        wrapper.unmount();
        repository.remindInstance.mockClear();
        const coolingDown = await createWrapper({ ...instance, canCancel: true, canRemind: false });
        await coolingDown.get('.sw-workflow-instance__remind').trigger('click');
        expect(repository.remindInstance).not.toHaveBeenCalled();
        coolingDown.unmount();
    });
    it('refreshes server eligibility when cooldown ends and clears its timer on unmount', async () => {
        jest.useFakeTimers({ doNotFake: ['setImmediate'] });
        try {
            const nextRemindAt = new Date(Date.now() + 600_000).toISOString();
            const coolingDown = {
                ...instance,
                canCancel: true,
                canRemind: false,
                tasks: [
                    {
                        ...instance.tasks![0],
                        reminder: {
                            dueAt: null,
                            overdue: false,
                            lastRemindedAt: new Date().toISOString(),
                            source: 'manual' as const,
                            deliveryPending: false,
                            count: 1,
                            canRemind: false,
                            nextRemindAt,
                        },
                    },
                ],
            };
            const wrapper = await createWrapper(coolingDown);
            repository.getInstance.mockClear();
            repository.getInstance.mockResolvedValueOnce({ ...coolingDown, canRemind: true });
            await jest.advanceTimersByTimeAsync(599_999);
            expect(repository.getInstance).not.toHaveBeenCalled();
            await jest.advanceTimersByTimeAsync(1);
            await flushPromises();
            expect(repository.getInstance).toHaveBeenCalledWith('instance');
            expect(wrapper.get('.sw-workflow-instance__remind').attributes('disabled')).toBeUndefined();
            wrapper.unmount();

            const pending = await createWrapper(coolingDown);
            repository.getInstance.mockClear();
            pending.unmount();
            await jest.advanceTimersByTimeAsync(600_000);
            expect(repository.getInstance).not.toHaveBeenCalled();
        } finally {
            jest.useRealTimers();
        }
    });
    it('keeps an unsaved form patch when the reminder refresh is due', async () => {
        jest.useFakeTimers({ doNotFake: ['setImmediate'] });
        try {
            const wrapper = await createWrapper({
                ...instance,
                canCancel: true,
                canRemind: false,
                formVersion: 0,
                formPermissions: { reason: { access: 'editable', required: false } },
                tasks: [
                    {
                        ...instance.tasks![0],
                        reminder: {
                            dueAt: null,
                            overdue: false,
                            lastRemindedAt: null,
                            source: null,
                            count: 0,
                            canRemind: false,
                            deliveryPending: false,
                            nextRemindAt: new Date(Date.now() + 1000).toISOString(),
                        },
                    },
                ],
            });
            repository.getInstance.mockClear();
            const editor = wrapper.findComponent({ name: 'sw-workflow-approval-form' }) as VueWrapper;
            editor.vm.$emit('update-patch', { reason: 'Unsaved' });
            await flushPromises();

            await jest.advanceTimersByTimeAsync(1000);
            await flushPromises();

            expect(repository.getInstance).not.toHaveBeenCalled();
            wrapper.unmount();
        } finally {
            jest.useRealTimers();
        }
    });

    it('continues bounded refreshes after cooldown while delivery is pending', async () => {
        jest.useFakeTimers({ doNotFake: ['setImmediate'] });
        try {
            const nextRemindAt = new Date(Date.now() + 600_000).toISOString();
            const pending = {
                ...instance,
                canCancel: true,
                canRemind: false,
                tasks: [
                    {
                        ...instance.tasks![0],
                        reminder: {
                            dueAt: null,
                            overdue: false,
                            lastRemindedAt: new Date().toISOString(),
                            source: 'manual' as const,
                            count: 1,
                            canRemind: false,
                            nextRemindAt,
                            deliveryPending: true,
                        },
                    },
                ],
            };
            const wrapper = await createWrapper(pending);
            repository.getInstance.mockClear();
            repository.getInstance.mockResolvedValue(pending);
            await jest.advanceTimersByTimeAsync(600_000);
            await flushPromises();
            expect(wrapper.get('.sw-workflow-instance__remind').attributes('disabled')).toBeDefined();
            repository.getInstance.mockClear();
            repository.getInstance.mockResolvedValueOnce({ ...pending, canRemind: true });
            await jest.advanceTimersByTimeAsync(60_000);
            await flushPromises();
            expect(repository.getInstance).toHaveBeenCalledWith('instance');
            expect(wrapper.get('.sw-workflow-instance__remind').attributes('disabled')).toBeUndefined();
            wrapper.unmount();
        } finally {
            jest.useRealTimers();
        }
    });
});
