/** @sw-package framework */
import { mount } from '@vue/test-utils';
import WorkflowNodePresentationService from '../../service/workflow-node-presentation.service';

describe('sw-workflow-studio-node-settings', () => {
    it('stages settings while typing without mutating the definition', async () => {
        const node = { id: 'a', type: 'example:task', typeVersion: 1, config: { label: 'Before', custom: 42 } };
        const wrapper = mount(await wrapTestComponent('sw-workflow-studio-node-settings', { sync: true }), {
            props: {
                node,
                document: { schemaVersion: 1, policy: 'core:structured', nodes: [node], connections: [] },
                allowEdit: true,
            },
            global: { provide: { workflowNodePresentationService: new WorkflowNodePresentationService() } },
        });
        await wrapper.find('input').setValue('After');
        expect(wrapper.emitted('update-config')?.[0][0]).toEqual({ label: 'After', description: '' });
        expect(node.config).toEqual({ label: 'Before', custom: 42 });
        wrapper.unmount();
        const readOnly = mount(await wrapTestComponent('sw-workflow-studio-node-settings', { sync: true }), {
            props: {
                node,
                document: { schemaVersion: 1, policy: 'core:structured', nodes: [node], connections: [] },
                allowEdit: false,
            },
            global: { provide: { workflowNodePresentationService: new WorkflowNodePresentationService() } },
        });
        expect(readOnly.find('input').attributes('disabled')).toBeDefined();
        readOnly.unmount();
    });
});
