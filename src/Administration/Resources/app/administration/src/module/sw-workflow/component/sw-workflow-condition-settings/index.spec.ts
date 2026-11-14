/** @sw-package framework */
import { conditionBranches } from '../../util/workflow-condition.util';
import type { ComponentPublicInstance } from 'vue';
import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import '../../service';
import { createApprovalWorkflow } from '../../util/approval-workflow-template.util';
import type { WorkflowDocument, WorkflowNode, WorkflowNodeTypeSpecification } from 'src/core/workflow/workflow.types';

const type: WorkflowNodeTypeSpecification = {
    name: 'core:condition',
    version: 1,
    kind: 'activity',
    inputPorts: ['in'],
    outputPorts: ['branch-1', 'fallback'],
};
async function createWrapper(allowEdit = true) {
    const draft = createApprovalWorkflow([
        {
            name: 'core:approval',
            version: 1,
            kind: 'activity',
            inputPorts: ['in'],
            outputPorts: ['approved', 'rejected'],
        },
    ]);
    draft.nodes[0].config = {
        inputType: 'core:approval-request',
        formFields: [
            { key: 'reason', label: 'Reason', type: 'text', required: false },
            {
                key: 'priority',
                label: 'Priority',
                type: 'select',
                required: false,
                options: [
                    { value: 'normal', label: 'Normal' },
                    { value: 'urgent', label: 'Urgent' },
                ],
            },
            { key: 'amount', label: 'Amount', type: 'amount', required: true },
            { key: 'quantity', label: 'Quantity', type: 'number', required: false },
            { key: 'date', label: 'Date', type: 'date', required: true },
        ],
    };
    const strategy = Shopwell.Service('workflowNodeEditingService').get(type.name, 1);
    let document = strategy.insert(draft, type, draft.connections.find((edge) => edge.source === 'start')!.id, 'condition');
    const node = () => document.nodes.find((item) => item.id === 'condition') as WorkflowNode;
    const wrapper: VueWrapper = mount(await wrapTestComponent('sw-workflow-condition-settings', { sync: true }), {
        props: { document, node: node(), allowEdit },
        global: { provide: { workflowFieldService: Shopwell.Service('workflowFieldService') } },
    });
    async function applyChange() {
        const edits = wrapper.emitted('update-config') ?? [];
        const patch = edits[edits.length - 1][0] as Record<string, unknown>;
        document = strategy.updateConfig!(document, 'condition', patch);
        await wrapper.setProps({ document, node: node() });
    }
    return { wrapper, applyChange, getDocument: (): WorkflowDocument => document };
}

type SelectWrapper = VueWrapper<ComponentPublicInstance<{ options: unknown[] }>>;
function findSelect(wrapper: VueWrapper, selector: string): SelectWrapper {
    return wrapper.findComponent(selector) as SelectWrapper;
}

describe('condition settings', () => {
    it('adds a branch, edits its condition, changes priority and keeps fallback outside editable branches', async () => {
        const { wrapper, applyChange, getDocument } = await createWrapper();
        await wrapper.find('.sw-workflow-condition-settings__add').trigger('click');
        await applyChange();
        expect(wrapper.findAll('.sw-workflow-condition-settings__branch')).toHaveLength(2);
        const fields = wrapper.findAll('.sw-workflow-condition-settings__fields input');
        await fields[0].setValue('Supplies request');
        await fields[0].trigger('blur');
        await applyChange();
        findSelect(wrapper, '.sw-workflow-condition-settings__field').vm.$emit('update:modelValue', 'request.reason');
        await applyChange();
        await wrapper.find('.sw-workflow-condition-settings__value input').setValue('Need supplies');
        await wrapper.find('.sw-workflow-condition-settings__value input').trigger('blur');
        await applyChange();
        const moveUp = wrapper.findAll('.sw-workflow-condition-settings__heading')[1].findAll('button')[1];
        await moveUp.trigger('click');
        await applyChange();
        const node = getDocument().nodes.find((item) => item.id === 'condition')!;
        expect(node.config).toMatchObject({
            branches: [
                expect.objectContaining({ label: 'Supplies request', field: 'request.reason', value: 'Need supplies' }),
                expect.anything(),
            ],
        });
        expect(wrapper.find('.sw-workflow-condition-settings__fallback').exists()).toBe(true);
        wrapper.unmount();
    });

    it('uses choice labels and resets values when changing fields or selecting an empty check', async () => {
        const { wrapper, applyChange, getDocument } = await createWrapper();
        const field = () => findSelect(wrapper, '.sw-workflow-condition-settings__field');
        expect(wrapper.find('.sw-workflow-condition-settings__value').exists()).toBe(false);
        field().vm.$emit('update:modelValue', 'request.priority');
        await applyChange();
        const value = findSelect(wrapper, '.sw-workflow-condition-settings__value');
        expect(value.props('options')).toEqual([
            expect.objectContaining({ value: 'normal' }),
            expect.objectContaining({ value: 'urgent' }),
        ]);
        value.vm.$emit('update:modelValue', 'urgent');
        await applyChange();
        expect(getDocument().nodes.find((node) => node.id === 'condition')!.config).toMatchObject({
            branches: [expect.objectContaining({ field: 'request.priority', value: 'urgent' })],
        });
        field().vm.$emit('update:modelValue', 'request.reason');
        await applyChange();
        expect(wrapper.find('.sw-workflow-condition-settings__value input').element).toHaveProperty('value', '');
        await wrapper.find('.sw-workflow-condition-settings__value input').setValue('Reason');
        await wrapper.find('.sw-workflow-condition-settings__value input').trigger('blur');
        await applyChange();
        findSelect(wrapper, '.sw-workflow-condition-settings__operator').vm.$emit('update:modelValue', 'isEmpty');
        await applyChange();
        expect(wrapper.find('.sw-workflow-condition-settings__value').exists()).toBe(false);
        expect(getDocument().nodes.find((node) => node.id === 'condition')!.config).toMatchObject({
            branches: [expect.objectContaining({ operator: 'isEmpty', value: '' })],
        });
        wrapper.unmount();
    });

    it('preserves unknown fields and values and explains missing field sources', async () => {
        const { wrapper, getDocument } = await createWrapper();
        const document = JSON.parse(JSON.stringify(getDocument())) as WorkflowDocument;
        document.nodes.find((node: WorkflowNode) => node.type === 'core:start')!.config = {};
        const node = document.nodes.find((item: WorkflowNode) => item.id === 'condition')!;
        node.config = { branches: [{ ...conditionBranches(node)[0], field: 'external.code', value: 'keep-me' }] };
        await wrapper.setProps({ document, node });
        expect(wrapper.find('.sw-workflow-condition-settings__unavailable').exists()).toBe(true);
        expect(wrapper.find('.sw-workflow-condition-settings__no-fields').exists()).toBe(true);
        expect(wrapper.find('.sw-workflow-condition-settings__value').exists()).toBe(false);
        expect(wrapper.emitted('update-config')).toBeUndefined();
        const input = wrapper.find('.sw-workflow-condition-settings__fields input');
        await input.setValue('Rename only');
        await input.trigger('blur');
        expect(wrapper.emitted('update-config')![0][0]).toMatchObject({
            branches: [expect.objectContaining({ field: 'external.code', value: 'keep-me' })],
        });
        wrapper.unmount();
    });

    it('shows removed choices without overwriting their stored values', async () => {
        const { wrapper, getDocument } = await createWrapper();
        const document = JSON.parse(JSON.stringify(getDocument())) as WorkflowDocument;
        const node = document.nodes.find((item: WorkflowNode) => item.id === 'condition')!;
        node.config = { branches: [{ ...conditionBranches(node)[0], field: 'request.priority', value: 'retired' }] };
        await wrapper.setProps({ document, node });
        expect(wrapper.find('.sw-workflow-condition-settings__unavailable-value').exists()).toBe(true);
        expect(findSelect(wrapper, '.sw-workflow-condition-settings__value').props('options')).toContainEqual(
            expect.objectContaining({ value: 'retired', disabled: true }),
        );
        expect(wrapper.emitted('update-config')).toBeUndefined();
        wrapper.unmount();
    });

    it.each(['amount', 'quantity', 'date'])(
        'offers comparisons for %s and preserves incompatible operators after a type change',
        async (key) => {
            const { wrapper, applyChange, getDocument } = await createWrapper();
            findSelect(wrapper, '.sw-workflow-condition-settings__field').vm.$emit('update:modelValue', `request.${key}`);
            await applyChange();
            const operator = () => findSelect(wrapper, '.sw-workflow-condition-settings__operator');
            expect(operator().props('options')).toEqual(
                expect.arrayContaining([
                    expect.objectContaining({ value: 'greaterThan' }),
                    expect.objectContaining({ value: 'lessThanOrEqual' }),
                ]),
            );
            operator().vm.$emit('update:modelValue', 'greaterThan');
            await applyChange();
            const document = JSON.parse(JSON.stringify(getDocument())) as WorkflowDocument;
            document.nodes.find((node) => node.type === 'core:start')!.config = {
                inputType: 'core:approval-request',
                formFields: [{ key, label: 'Changed', type: 'text', required: false }],
            };
            const node = document.nodes.find((item) => item.id === 'condition')!;
            await wrapper.setProps({ document, node });
            expect(wrapper.find('.sw-workflow-condition-settings__unavailable-operator').exists()).toBe(true);
            expect(operator().props('options')).toContainEqual(
                expect.objectContaining({ value: 'greaterThan', disabled: true }),
            );
            const input = wrapper.find('.sw-workflow-condition-settings__fields input');
            await input.setValue('Preserved rule');
            await input.trigger('blur');
            expect(wrapper.emitted('update-config')!.at(-1)![0]).toMatchObject({
                branches: [expect.objectContaining({ operator: 'greaterThan', field: `request.${key}` })],
            });
            wrapper.unmount();
        },
    );

    it.each([
        ['amount', 'amount', 'number'],
        ['reason', 'text', 'amount'],
        ['reason', '', 'text'],
    ])('requires explicit rebinding for %s from %s to %s', async (key, originalType, currentType) => {
        const { wrapper, getDocument } = await createWrapper();
        const document = JSON.parse(JSON.stringify(getDocument())) as WorkflowDocument;
        document.nodes.find((node) => node.type === 'core:start')!.config = {
            inputType: 'core:approval-request',
            formFields: [{ key, label: 'Current field', type: currentType, required: true }],
        };
        const node = document.nodes.find((item) => item.id === 'condition')!;
        node.config = {
            branches: [
                {
                    ...conditionBranches(node)[0],
                    field: `request.${key}`,
                    fieldType: originalType,
                    operator: 'equals',
                    value: '5',
                },
            ],
        };
        await wrapper.setProps({ document, node });
        expect(wrapper.find('.sw-workflow-condition-settings__stale-type').exists()).toBe(true);
        expect(wrapper.find('.sw-workflow-condition-settings__value input').element).toHaveProperty('value', '5');
        expect(wrapper.emitted('update-config')).toBeUndefined();
        const label = wrapper.find('.sw-workflow-condition-settings__fields input');
        await label.setValue('Rename only');
        await label.trigger('blur');
        expect(wrapper.emitted('update-config')!.at(-1)![0]).toMatchObject({
            branches: [expect.objectContaining({ fieldType: originalType, value: '5' })],
        });
        await wrapper.find('.sw-workflow-condition-settings__rebind').trigger('click');
        expect(wrapper.emitted('update-config')!.at(-1)![0]).toMatchObject({
            branches: [
                expect.objectContaining({ field: `request.${key}`, fieldType: currentType, operator: 'equals', value: '' }),
            ],
        });
        expect(wrapper.find('.sw-workflow-condition-settings__stale-type').exists()).toBe(false);
        wrapper.unmount();
    });

    it('prevents branch changes in read-only mode', async () => {
        const { wrapper } = await createWrapper(false);
        await wrapper.find('.sw-workflow-condition-settings__add').trigger('click');
        findSelect(wrapper, '.sw-workflow-condition-settings__field').vm.$emit('update:modelValue', 'request.priority');
        expect(wrapper.emitted('update-config')).toBeUndefined();
        expect(wrapper.find('input').attributes('disabled')).toBeDefined();
        wrapper.unmount();
    });
});
