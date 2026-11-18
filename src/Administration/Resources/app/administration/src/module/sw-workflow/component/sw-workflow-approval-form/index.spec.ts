/** @sw-package framework */
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';

const wrappers: VueWrapper[] = [];
const getFormOptions = jest.fn();
async function createWrapper() {
    const wrapper: VueWrapper = mount(await wrapTestComponent('sw-workflow-approval-form', { sync: true }), {
        props: {
            instance: {
                id: 'instance',
                round: 1,
                status: 'RUNNING',
                title: 'Request',
                formVersion: 3,
                formData: { reason: 'Original', note: '' },
                formSchema: [
                    { key: 'reason', label: 'Reason', type: 'text', required: true },
                    { key: 'note', label: 'Review note', type: 'text', required: false },
                ],
                formPermissions: {
                    reason: { access: 'readonly', required: false },
                    note: { access: 'editable', required: true },
                },
            },
        },
        global: {
            provide: { workflowService: { getFormOptions } },
            stubs: {
                'mt-textarea': {
                    props: ['modelValue', 'label', 'disabled'],
                    template:
                        '<label>{{ label }}<textarea :disabled="disabled" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" /></label>',
                },
            },
        },
    });
    wrappers.push(wrapper);
    await flushPromises();
    return wrapper;
}

describe('sw-workflow-approval-form', () => {
    afterEach(() => {
        wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
        jest.clearAllMocks();
    });

    it('renders only editable fields and emits only changed values', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.findAll('textarea')).toHaveLength(1);
        expect(wrapper.emitted('validity-change')?.at(-1)).toEqual([false]);
        await wrapper.get('textarea').setValue('Verified');
        expect(wrapper.emitted('update-patch')?.at(-1)).toEqual([{ note: 'Verified' }]);
        expect(wrapper.emitted('validity-change')?.at(-1)).toEqual([true]);
        await wrapper.get('textarea').setValue('');
        expect(wrapper.emitted('update-patch')?.at(-1)).toEqual([{}]);
    });

    it('prevents edits while the decision is being submitted', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({ disabled: true });
        expect(wrapper.get('textarea').attributes('disabled')).toBeDefined();
    });
});

describe('typed approval form changes', () => {
    it('preserves ordered user lists without emitting a phantom edit', async () => {
        const wrapper = await createWrapper();
        const id = '0123456789abcdef0123456789abcdef';
        await wrapper.setProps({
            instance: {
                id: 'instance',
                round: 1,
                status: 'RUNNING',
                title: 'People request',
                formVersion: 3,
                formData: { people: [id] },
                formSchema: [{ key: 'people', label: 'People', type: 'users', required: true }],
                formPermissions: { people: { access: 'editable', required: true } },
            },
        });
        await flushPromises();
        expect(wrapper.emitted('update-patch')?.at(-1)).toEqual([{}]);
        expect(wrapper.emitted('validity-change')?.at(-1)).toEqual([true]);
        await wrapper.setData({ values: { people: [] } });
        expect(wrapper.emitted('update-patch')?.at(-1)).toEqual([{ people: [] }]);
        expect(wrapper.emitted('validity-change')?.at(-1)).toEqual([false]);
        wrapper.unmount();
    });
});
