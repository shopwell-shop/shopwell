/** @sw-package framework */
import { mount, type VueWrapper } from '@vue/test-utils';

const wrappers: VueWrapper[] = [];
async function createWrapper(copy = false) {
    const wrapper: VueWrapper = mount(await wrapTestComponent('sw-workflow-form-permissions', { sync: true }), {
        props: {
            fields: [{ key: 'note', label: 'Review note', type: 'text', required: false }],
            permissions: {},
            allowEdit: true,
            copy,
        },
        global: {
            stubs: {
                'mt-select': {
                    props: ['modelValue', 'options', 'disabled'],
                    template:
                        '<select :disabled="disabled" :value="modelValue" @change="$emit(\'update:modelValue\', $event.target.value)"><option v-for="option in options" :value="option.value">{{ option.label }}</option></select>',
                },
                'mt-switch': {
                    props: ['modelValue', 'disabled'],
                    template:
                        '<input type="checkbox" :disabled="disabled" :checked="modelValue" @change="$emit(\'update:modelValue\', $event.target.checked)" />',
                },
            },
        },
    });
    wrappers.push(wrapper);
    return wrapper;
}

describe('sw-workflow-form-permissions', () => {
    afterEach(() => wrappers.splice(0).forEach((wrapper) => wrapper.unmount()));

    it('defaults fields to read-only and requires edit access before marking them required', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.get('select').element.value).toBe('readonly');
        expect(wrapper.get('input').attributes('disabled')).toBeDefined();
        await wrapper.get('select').setValue('editable');
        expect(wrapper.emitted('update-permissions')?.[0]).toEqual([{ note: { access: 'editable', required: false } }]);
        await wrapper.setProps({ permissions: { note: { access: 'editable', required: false } } });
        await wrapper.get('input').setValue(true);
        expect(wrapper.emitted('update-permissions')?.at(-1)).toEqual([{ note: { access: 'editable', required: true } }]);
    });

    it('clears a node requirement when a field becomes hidden', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({ permissions: { note: { access: 'editable', required: true } } });
        await wrapper.get('select').setValue('hidden');
        expect(wrapper.emitted('update-permissions')?.at(-1)).toEqual([{ note: { access: 'hidden', required: false } }]);
    });

    it('offers no editing option for a copy node', async () => {
        const wrapper = await createWrapper(true);
        expect(wrapper.find('option[value="editable"]').exists()).toBe(false);
        expect(wrapper.find('input').exists()).toBe(false);
    });
});
