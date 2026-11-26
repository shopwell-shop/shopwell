/** @sw-package framework */
import { flushPromises, shallowMount } from '@vue/test-utils';

async function createWrapper(
    decide = jest.fn().mockResolvedValue({ aggregateVersion: 4 }),
    action = 'approved',
    returnTask = jest.fn(),
    returnTargets = jest.fn(),
) {
    const wrapper = shallowMount(await wrapTestComponent('sw-approval-flow-decision-modal', { sync: true }), {
        props: { instanceId: 'instance', taskId: 'task', expectedVersion: 3, action },
        global: {
            provide: { approvalFlowApiService: { decide, returnTask, returnTargets } },
            stubs: {
                'sw-modal': { template: '<section><slot/><slot name="modal-footer"/></section>' },
                'mt-button': { props: ['disabled'], template: '<button :disabled="disabled"><slot/></button>' },
                'mt-banner': { template: '<div role="alert"><slot/></div>' },
                'mt-select': {
                    props: ['modelValue', 'options', 'disabled'],
                    template: `<select :value="modelValue" :disabled="disabled" @change="$emit('update:modelValue', $event.target.value || null)"><option value=""/><option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option></select>`,
                },
                'mt-textarea': {
                    props: ['modelValue', 'disabled'],
                    template: `<textarea :value="modelValue" :disabled="disabled" @input="$emit('update:modelValue', $event.target.value)"/>`,
                },
            },
        },
    });
    return { wrapper, decide };
}

describe('personal approval decision confirmation', () => {
    it.each(['approved', 'rejected'])('submits the confirmed %s opinion with its original version', async (outcome) => {
        const { wrapper, decide } = await createWrapper(undefined, outcome);
        await wrapper.find('textarea').setValue('Reviewed the transfer.');
        await wrapper.find('[data-testid="decision-submit"]').trigger('click');
        await flushPromises();

        expect(decide).toHaveBeenCalledWith('instance', 'task', {
            commandId: expect.stringMatching(/^[a-f0-9]{32}$/) as string,
            expectedVersion: 3,
            outcome,
            reason: 'Reviewed the transfer.',
        });
        expect(wrapper.emitted('completed')).toHaveLength(1);
    });

    it('requires a target and reason and retries the same return command', async () => {
        const returnTask = jest.fn().mockRejectedValueOnce(new Error('response lost')).mockResolvedValueOnce({});
        const returnTargets = jest.fn().mockResolvedValue([{ executionId: 'earlier', nodeId: 'finance', sequence: 2 }]);
        const { wrapper, decide } = await createWrapper(undefined, 'return', returnTask, returnTargets);
        await flushPromises();
        expect(returnTargets).toHaveBeenCalledWith('instance', 'task');

        const submit = wrapper.find('[data-testid="decision-submit"]');
        expect(submit.attributes('disabled')).toBeDefined();
        await wrapper.find('select').setValue('earlier');
        expect(submit.attributes('disabled')).toBeDefined();
        await wrapper.find('textarea').setValue('  Recheck the transfer.  ');
        await wrapper.find('select').setValue('');
        expect(submit.attributes('disabled')).toBeDefined();
        await wrapper.find('select').setValue('earlier');
        await submit.trigger('click');
        await flushPromises();

        expect(wrapper.find('select').attributes('disabled')).toBeDefined();
        expect(wrapper.find('textarea').attributes('disabled')).toBeDefined();
        await submit.trigger('click');
        await flushPromises();
        expect(decide).not.toHaveBeenCalled();
        expect(returnTask).toHaveBeenCalledTimes(2);
        expect(returnTask).toHaveBeenCalledWith('instance', 'task', {
            commandId: expect.stringMatching(/^[a-f0-9]{32}$/) as string,
            expectedVersion: 3,
            targetExecutionId: 'earlier',
            reason: 'Recheck the transfer.',
        });
        expect(returnTask.mock.calls[1]).toEqual(returnTask.mock.calls[0]);
        expect(wrapper.emitted('completed')).toHaveLength(1);
    });

    it('shows an empty state when no earlier node can be reopened', async () => {
        const returnTask = jest.fn();
        const { wrapper } = await createWrapper(undefined, 'return', returnTask, jest.fn().mockResolvedValue([]));
        await flushPromises();

        expect(wrapper.text()).toContain('sw-approval-flow.decision.noReturnTargets');
        expect(wrapper.find('[data-testid="decision-submit"]').exists()).toBe(false);
        expect(returnTask).not.toHaveBeenCalled();
    });

    it('retries an unknown result with the same command and keeps the opinion frozen', async () => {
        const decide = jest
            .fn()
            .mockRejectedValueOnce(new Error('connection lost'))
            .mockResolvedValueOnce({ aggregateVersion: 4 });
        const { wrapper } = await createWrapper(decide);
        await wrapper.find('textarea').setValue('Original opinion.');
        await wrapper.find('[data-testid="decision-submit"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[role="alert"]').text()).toContain('sw-approval-flow.decision.retryError');
        expect(wrapper.find('textarea').attributes('disabled')).toBeDefined();
        await wrapper.find('[data-testid="decision-submit"]').trigger('click');
        await flushPromises();
        expect(decide).toHaveBeenCalledTimes(2);
        expect(decide.mock.calls[1]).toEqual(decide.mock.calls[0]);
        expect(wrapper.emitted('completed')).toHaveLength(1);
    });

    it('offers a reload after a conflict and never silently resubmits at a newer version', async () => {
        const { wrapper, decide } = await createWrapper(jest.fn().mockRejectedValue({ response: { status: 409 } }));
        await wrapper.find('[data-testid="decision-submit"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[role="alert"]').text()).toContain('sw-approval-flow.decision.conflict');
        expect(wrapper.find('[data-testid="decision-submit"]').exists()).toBe(false);
        await wrapper.find('[data-testid="decision-refresh"]').trigger('click');
        expect(wrapper.emitted('refresh')).toHaveLength(1);
        expect(decide).toHaveBeenCalledTimes(1);
    });

    it.each([
        401,
        403,
        400,
        404,
    ])('stops resubmission after a definitive %s rejection', async (status) => {
        const { wrapper, decide } = await createWrapper(jest.fn().mockRejectedValue({ response: { status } }));
        await wrapper.find('[data-testid="decision-submit"]').trigger('click');
        await flushPromises();
        expect(wrapper.find('[data-testid="decision-submit"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="decision-refresh"]').exists()).toBe(true);
        expect(decide).toHaveBeenCalledTimes(1);
    });

    it('reloads after dismissing an uncertain command so a saved opinion is not shown as pending', async () => {
        const { wrapper } = await createWrapper(jest.fn().mockRejectedValue(new Error('response lost')));
        await wrapper.find('[data-testid="decision-submit"]').trigger('click');
        await flushPromises();
        await wrapper.find('button').trigger('click');

        expect(wrapper.emitted('refresh')).toHaveLength(1);
    });

    it('disables duplicate submission while the response is pending', async () => {
        let resolve!: (value: object) => void;
        const { wrapper, decide } = await createWrapper(
            jest.fn().mockReturnValue(
                new Promise((done) => {
                    resolve = done;
                }),
            ),
        );
        await wrapper.find('[data-testid="decision-submit"]').trigger('click');
        expect(wrapper.find('[data-testid="decision-submit"]').attributes('disabled')).toBeDefined();
        await wrapper.find('[data-testid="decision-submit"]').trigger('click');
        expect(decide).toHaveBeenCalledTimes(1);
        wrapper.unmount();
        resolve({ aggregateVersion: 4 });
        await flushPromises();
        expect(wrapper.emitted('completed')).toBeUndefined();
    });
});
