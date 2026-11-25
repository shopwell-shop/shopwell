/** @sw-package framework */
import { flushPromises, shallowMount } from '@vue/test-utils';

async function createWrapper(decide = jest.fn().mockResolvedValue({ aggregateVersion: 4 }), outcome = 'approved') {
    const wrapper = shallowMount(await wrapTestComponent('sw-approval-flow-decision-modal', { sync: true }), {
        props: { instanceId: 'instance', taskId: 'task', expectedVersion: 3, outcome },
        global: {
            provide: { approvalFlowApiService: { decide } },
            stubs: {
                'sw-modal': { template: '<section><slot/><slot name="modal-footer"/></section>' },
                'mt-button': { props: ['disabled'], template: '<button :disabled="disabled"><slot/></button>' },
                'mt-banner': { template: '<div role="alert"><slot/></div>' },
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
