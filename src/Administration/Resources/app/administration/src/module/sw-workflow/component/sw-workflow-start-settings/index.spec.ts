/** @sw-package framework */
import { mount } from '@vue/test-utils';
import type { ComponentPublicInstance } from 'vue';
import type { VueWrapper } from '@vue/test-utils';
import type { WorkflowNode } from 'src/core/workflow/workflow.types';
import type { WorkflowFormField } from '../../util/workflow-form.util';

async function createWrapper(allowEdit = true) {
    let node: WorkflowNode = {
        id: 'start',
        type: 'core:start',
        typeVersion: 1,
        config: { inputType: 'core:approval-request' },
    };
    const wrapper: VueWrapper = mount(await wrapTestComponent('sw-workflow-start-settings', { sync: true }), {
        props: { node, allowEdit },
        global: {
            provide: {
                workflowRequestFormService: {
                    getDataSources: () => [{ name: 'core:currency', labelKey: 'sw-workflow.form.currencySource' }],
                },
            },
        },
    });
    const fields = () => (wrapper.emitted('update-config')!.at(-1)![0] as { formFields: WorkflowFormField[] }).formFields;
    async function applyChange() {
        node = { ...node, config: { inputType: 'core:approval-request', formFields: fields() } };
        await wrapper.setProps({ node });
    }
    return { wrapper, fields, applyChange };
}

describe('request form settings', () => {
    it('adds, renames, reorders and removes fields while preserving stable keys', async () => {
        const { wrapper, fields, applyChange } = await createWrapper();
        await wrapper.find('.sw-workflow-start-settings__add').trigger('click');
        await applyChange();
        const key = fields()[1].key;
        expect(key).toMatch(/^field_[a-zA-Z0-9_]+$/);
        await wrapper.find('.sw-workflow-start-settings__label input').setValue('Expense amount');
        await applyChange();
        expect(fields()[1]).toMatchObject({ key, label: 'Expense amount' });
        await wrapper.findAll('.sw-workflow-start-settings__heading')[1].findAll('button')[1].trigger('click');
        await applyChange();
        expect(fields()[0].key).toBe(key);
        await wrapper.find('.sw-workflow-start-settings__remove').trigger('click');
        await applyChange();
        expect(fields().map((field) => field.key)).toEqual(['reason']);
        wrapper.unmount();
    });
    it('supports all five field types, required fields and stable option values', async () => {
        const { wrapper, fields, applyChange } = await createWrapper();
        for (const type of [
            'amount',
            'number',
            'date',
            'select',
            'text',
        ]) {
            (wrapper.findComponent('.sw-workflow-start-settings__type') as VueWrapper<ComponentPublicInstance>).vm.$emit(
                'update:modelValue',
                type,
            );
            await applyChange();
            expect(fields()[0].type).toBe(type);
        }
        (wrapper.findComponent('.sw-workflow-start-settings__type') as VueWrapper<ComponentPublicInstance>).vm.$emit(
            'update:modelValue',
            'select',
        );
        await applyChange();
        await wrapper.find('.sw-workflow-start-settings__add-option').trigger('click');
        await applyChange();
        const value = fields()[0].options![0].value;
        await wrapper.find('.sw-workflow-start-settings__option input').setValue('Travel');
        await applyChange();
        expect(fields()[0].options).toEqual([{ value, label: 'Travel' }]);
        await wrapper.find('input[type="checkbox"]').setValue(true);
        await applyChange();
        expect(fields()[0].required).toBe(true);
        (wrapper.findComponent('.sw-workflow-start-settings__type') as VueWrapper<ComponentPublicInstance>).vm.$emit(
            'update:modelValue',
            'text',
        );
        await applyChange();
        expect(fields()[0]).not.toHaveProperty('options');
        (wrapper.findComponent('.sw-workflow-start-settings__type') as VueWrapper<ComponentPublicInstance>).vm.$emit(
            'update:modelValue',
            'select',
        );
        await applyChange();
        expect(fields()[0].options).toEqual([]);
        wrapper.unmount();
    });
    it('configures a registered option source and removes it when changing field type', async () => {
        const { wrapper, fields, applyChange } = await createWrapper();
        const source = wrapper.findComponent(
            '.sw-workflow-start-settings__data-source',
        ) as VueWrapper<ComponentPublicInstance>;
        source.vm.$emit('update:modelValue', 'core:currency');
        await applyChange();
        expect(fields()[0]).toMatchObject({ type: 'text', dataSource: 'core:currency' });
        (wrapper.findComponent('.sw-workflow-start-settings__type') as VueWrapper<ComponentPublicInstance>).vm.$emit(
            'update:modelValue',
            'number',
        );
        await applyChange();
        expect(fields()[0]).not.toHaveProperty('dataSource');
        wrapper.unmount();
    });
    it('disables edits for readers', async () => {
        const { wrapper } = await createWrapper(false);
        await wrapper.find('.sw-workflow-start-settings__add').trigger('click');
        (wrapper.findComponent('.sw-workflow-start-settings__type') as VueWrapper<ComponentPublicInstance>).vm.$emit(
            'update:modelValue',
            'amount',
        );
        expect(wrapper.emitted('update-config')).toBeUndefined();
        wrapper.unmount();
    });
});

describe('workflow extension scenarios', () => {
    it('edits an automatic workflow request schema without an approval input type', async () => {
        const { wrapper } = await createWrapper();
        await wrapper.setProps({ node: { id: 'start', type: 'core:start', typeVersion: 1, config: { formFields: [] } } });
        await wrapper.get('.sw-workflow-start-settings__add').trigger('click');

        const patch = wrapper.emitted('update-config')!.at(-1)![0] as { formFields: WorkflowFormField[] };
        expect(patch.formFields).toHaveLength(1);
        expect(patch.formFields[0].type).toBe('text');
        wrapper.unmount();
    });
});
