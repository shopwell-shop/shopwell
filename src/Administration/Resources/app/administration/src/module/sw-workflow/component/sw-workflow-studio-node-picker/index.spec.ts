/** @sw-package framework */
import '../../service';
import WorkflowNodeEditingService from '../../service/workflow-node-editing.service';
import { mount } from '@vue/test-utils';
import { createStructuredWorkflowDraft as createWorkflowDraft } from 'src/module/sw-workflow/util/workflow-template.util';
import WorkflowNodePresentationService from '../../service/workflow-node-presentation.service';

describe('sw-workflow-studio-node-picker', () => {
    it('offers compatible plugin nodes and filters out multi-input nodes', async () => {
        const wrapper = mount(await wrapTestComponent('sw-workflow-studio-node-picker', { sync: true }), {
            props: {
                document: createWorkflowDraft(),
                labels: {},
                selectedConnectionId: 'start-end',
                nodeTypes: [
                    { name: 'example:task', version: 1, kind: 'activity', inputPorts: ['in'], outputPorts: ['done'] },
                    {
                        name: 'example:join',
                        version: 1,
                        kind: 'activity',
                        inputPorts: ['left', 'right'],
                        outputPorts: ['done'],
                    },
                ],
            },
            global: {
                provide: {
                    workflowTemplateService: Shopwell.Service('workflowTemplateService'),
                    workflowNodeEditingService: new WorkflowNodeEditingService(),
                    workflowNodePresentationService: new WorkflowNodePresentationService(),
                },
            },
        });
        expect(wrapper.find('.sw-workflow-studio-node-picker__tree-item').exists()).toBe(false);
        expect(wrapper.find('.sw-workflow-studio-node-picker__tabs').exists()).toBe(false);
        const entries = wrapper.findAll('.sw-workflow-studio-node-picker__node-item');
        expect(entries).toHaveLength(1);
        await entries[0].trigger('click');
        expect(wrapper.emitted('insert')?.[0][0]).toEqual(expect.objectContaining({ name: 'example:task' }));
        await wrapper.find('input').setValue('missing');
        expect(wrapper.findAll('.sw-workflow-studio-node-picker__node-item')).toHaveLength(0);
        expect(wrapper.find('.sw-workflow-studio-node-picker__empty[role="status"]').exists()).toBe(true);
        await wrapper.find('input').setValue('');
        expect(wrapper.findAll('.sw-workflow-studio-node-picker__node-item')).toHaveLength(1);
        expect(wrapper.find('.sw-workflow-studio-node-picker__empty').exists()).toBe(false);
        wrapper.unmount();
    });
});
