/** @sw-package framework */
import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import type { WorkflowCanvasNodeData } from '../sw-workflow-studio-canvas/workflow-canvas.adapter';

function nodeData(config: Record<string, unknown> | []): WorkflowCanvasNodeData {
    return {
        node: { id: 'copy', type: 'core:copy', typeVersion: 1, config },
        label: 'Finance copy',
        nodeType: 'core:copy',
        typeVersion: 1,
        description: '',
        typeName: 'Copy',
        kind: 'activity',
        inputPorts: ['in'],
        outputPorts: ['out'],
        invalid: false,
    };
}

describe('sw-workflow-copy-node', () => {
    it('shows the recipient summary and flags a missing selection', async () => {
        const wrapper: VueWrapper = mount(await wrapTestComponent('sw-workflow-copy-node', { sync: true }), {
            props: { data: nodeData({ userIds: ['recipient'] }) },
            global: {
                stubs: {
                    'sw-workflow-users-assignee-summary': {
                        props: ['config'],
                        template: '<span class="recipients">{{ config.userIds.join(", ") }}</span>',
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('Finance copy');
        expect(wrapper.get('.recipients').text()).toBe('recipient');
        expect(wrapper.classes()).not.toContain('has-error');

        await wrapper.setProps({ data: nodeData([]) });

        expect(wrapper.classes()).toContain('has-error');
        expect(wrapper.find('.recipients').exists()).toBe(false);
        expect(wrapper.text()).toContain('sw-workflow.copy.empty');
        wrapper.unmount();
    });
});
