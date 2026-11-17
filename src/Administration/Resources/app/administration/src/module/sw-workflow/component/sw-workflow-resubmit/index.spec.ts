/** @sw-package framework */
import { flushPromises, shallowMount, type VueWrapper } from '@vue/test-utils';
import type { RuntimeInstance, WorkflowFormField } from 'src/core/workflow/workflow-runtime.types';

const resubmitInstance = jest.fn();
const getFormOptions = jest.fn();
const wrappers: VueWrapper[] = [];

async function createWrapper(readOnlyInputKeys: string[] = [], instance: Partial<RuntimeInstance> = {}) {
    const wrapper = shallowMount(await wrapTestComponent('sw-workflow-resubmit', { sync: true }), {
        props: {
            instance: {
                id: 'instance',
                title: 'Request',
                status: 'RETURNED',
                round: 1,
                formVersion: 0,
                canResubmit: true,
                formData: { reason: 'Original' },
                formSchema: [{ key: 'reason', label: 'Reason', type: 'text', required: true }],
                readOnlyInputKeys,
                ...instance,
            },
        },
        global: {
            provide: { workflowRuntimeApiService: { resubmitInstance }, workflowService: { getFormOptions } },
            stubs: {
                'mt-button': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
                'sw-modal': { template: '<section><slot/><slot name="modal-footer"/></section>' },
                'mt-textarea': {
                    props: ['modelValue', 'disabled'],
                    template:
                        '<textarea :disabled="disabled" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)"/>',
                },
                'mt-select': {
                    props: ['modelValue', 'options', 'disabled'],
                    template:
                        '<select :value="modelValue" :disabled="disabled"><option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option></select>',
                },
            },
        },
    });
    wrappers.push(wrapper);

    return wrapper;
}

describe('resubmitting a returned request', () => {
    beforeEach(() => {
        resubmitInstance.mockReset().mockResolvedValue(undefined);
        getFormOptions.mockReset();
    });

    afterEach(() => {
        wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
    });

    it('edits saved inputs and submits the expected round with a separate explanation', async () => {
        const wrapper = await createWrapper();
        await wrapper.get('[data-resubmit-open]').trigger('click');
        await flushPromises();
        await wrapper.findAll('textarea')[0].setValue('Revised');
        await wrapper.findAll('textarea')[1].setValue('Invoice supplied');
        await wrapper.get('[data-resubmit-confirm]').trigger('click');
        await flushPromises();
        expect(resubmitInstance).toHaveBeenCalledWith('instance', {
            round: 1,
            formVersion: 0,
            formData: { reason: 'Revised' },
            comment: 'Invoice supplied',
        });
        expect(wrapper.emitted('resubmitted')).toHaveLength(1);
    });

    it('keeps financial inputs disabled and still permits an explanation', async () => {
        const wrapper = await createWrapper(['reason']);
        await wrapper.get('[data-resubmit-open]').trigger('click');
        await flushPromises();
        expect(wrapper.findAll('textarea')[0].attributes('disabled')).toBeDefined();
        expect(wrapper.findAll('textarea')[1].attributes('disabled')).toBeUndefined();
        await wrapper.get('[data-resubmit-confirm]').trigger('click');
        await flushPromises();
        expect(resubmitInstance).toHaveBeenCalledWith('instance', expect.objectContaining({ formData: {}, formVersion: 0 }));
    });

    it('does not submit an empty required field', async () => {
        const wrapper = await createWrapper();
        await wrapper.get('[data-resubmit-open]').trigger('click');
        await flushPromises();
        await wrapper.findAll('textarea')[0].setValue('');
        await wrapper.get('[data-resubmit-confirm]').trigger('click');
        expect(resubmitInstance).not.toHaveBeenCalled();
    });

    it.each<{ scenario: string; field: WorkflowFormField; expectedLabel: string }>([
        {
            scenario: 'a read-only data source has no loaded options',
            field: { key: 'currency', label: 'Currency', type: 'text', required: true, dataSource: 'core:currency' },
            expectedLabel: 'CNY',
        },
        {
            scenario: 'a saved value is absent from the selection options',
            field: {
                key: 'currency',
                label: 'Currency',
                type: 'select',
                required: true,
                options: [{ value: 'USD', label: 'US dollar' }],
            },
            expectedLabel: 'CNY',
        },
        {
            scenario: 'a saved selection already has a display label',
            field: {
                key: 'currency',
                label: 'Currency',
                type: 'select',
                required: true,
                options: [{ value: 'CNY', label: 'Chinese yuan' }],
            },
            expectedLabel: 'Chinese yuan',
        },
    ])('shows immutable evidence when $scenario', async ({ field, expectedLabel }) => {
        const wrapper = await createWrapper(['currency'], {
            formData: { currency: 'CNY' },
            formSchema: [field],
        });
        await wrapper.get('[data-resubmit-open]').trigger('click');
        await flushPromises();

        const select = wrapper.get('select').element as HTMLSelectElement;
        expect(select.disabled).toBe(true);
        expect(select.selectedOptions[0]?.textContent).toBe(expectedLabel);
        expect(getFormOptions).not.toHaveBeenCalled();
        await wrapper.get('[data-resubmit-confirm]').trigger('click');
        await flushPromises();
        expect(resubmitInstance).toHaveBeenCalledWith('instance', { round: 1, formVersion: 0, formData: {}, comment: '' });
    });
});
