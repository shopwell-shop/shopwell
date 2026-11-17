/** @sw-package framework */
import { shallowMount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import type { WorkflowNode } from 'src/core/workflow/workflow.types';
import '../../service/extension/approval-workflow.registration';

const node: WorkflowNode = {
    id: 'copy',
    type: 'core:copy',
    typeVersion: 1,
    config: { label: 'Finance copy', userIds: ['first'] },
};

describe('copy node extension', () => {
    it('registers presentation and inserts or removes the node through existing structured editing', () => {
        const type = { name: 'core:copy', version: 1, kind: 'activity' as const, inputPorts: ['in'], outputPorts: ['out'] };
        const document = {
            schemaVersion: 1 as const,
            policy: 'core:structured',
            nodes: [
                { id: 'start', type: 'core:start', typeVersion: 1, config: {} },
                { id: 'end', type: 'core:end', typeVersion: 1, config: {} },
            ],
            connections: [{ id: 'begin', source: 'start', sourcePort: 'out', target: 'end', targetPort: 'in' }],
        };
        const strategy = Shopwell.Service('workflowNodeEditingService').get(type.name, type.version);
        const inserted = strategy.insert(document, type, 'begin', 'copy');

        expect(Shopwell.Service('workflowNodePresentationService').get('core:copy', 1)).toMatchObject({
            settingsComponent: 'sw-workflow-copy-settings',
            nodeComponent: 'sw-workflow-copy-node',
        });
        expect(inserted.connections.find((edge) => edge.id === 'begin')?.target).toBe('copy');
        expect(strategy.remove(inserted, 'copy')).toEqual(document);
    });

    it('preserves authored labels while updating recipients and prevents read-only changes', async () => {
        const wrapper: VueWrapper = shallowMount(await wrapTestComponent('sw-workflow-copy-settings', { sync: true }), {
            props: { node },
            global: {
                provide: { workflowRequestFormService: { getFields: () => [] } },
                stubs: {
                    'mt-tabs': {
                        props: ['items'],
                        template:
                            '<div><button v-for="item in items" :key="item.name" :data-tab="item.name" @click="$emit(\'new-item-active\', item.name)">{{ item.label }}</button></div>',
                    },
                    'sw-workflow-users-assignee-select': {
                        props: [
                            'config',
                            'allowEdit',
                            'labelKey',
                            'placeholderKey',
                        ],
                        emits: ['update-config'],
                        template:
                            '<button class="select-copy" :aria-label="labelKey" @click="$emit(\'update-config\', { userIds: [\'second\'] })">Select</button>',
                    },
                },
            },
        });
        await wrapper.get('.select-copy').trigger('click');
        expect(wrapper.emitted('update-config')?.[0]).toEqual([{ label: 'Finance copy', userIds: ['second'] }]);
        await wrapper.setProps({ allowEdit: false });
        await wrapper.get('.select-copy').trigger('click');
        expect(wrapper.emitted('update-config')).toHaveLength(1);
        expect(wrapper.get('.select-copy').attributes('aria-label')).toBe('sw-workflow.copy.recipients');
        wrapper.unmount();
    });
});
