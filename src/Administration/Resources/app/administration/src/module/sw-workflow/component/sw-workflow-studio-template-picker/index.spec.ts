/** @sw-package framework */
import '../../service/extension/approval-workflow.registration';
import { mount } from '@vue/test-utils';
import '../../service';

async function createWrapper(allowEdit = true) {
    return mount(await wrapTestComponent('sw-workflow-studio-template-picker', { sync: true }), {
        props: {
            nodeTypes: [
                {
                    name: 'core:approval',
                    version: 1,
                    kind: 'activity',
                    inputPorts: ['in'],
                    outputPorts: ['approved', 'rejected'],
                },
            ],
            labels: {},
            allowEdit,
        },
        global: {
            provide: { workflowTemplateService: Shopwell.Service('workflowTemplateService') },
            stubs: {
                'sw-modal': { template: '<section><slot/><footer><slot name="modal-footer"/></footer></section>' },
                'sw-workflow-studio-canvas': {
                    props: ['document', 'labels', 'allowEdit'],
                    template: '<div class="preview" :data-editable="allowEdit">{{ document.nodes.length }}</div>',
                },
            },
        },
    });
}

describe('workflow template picker', () => {
    it('previews a template without applying it and requires an explicit action', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.get('.sw-workflow-studio-template-picker__apply').attributes('disabled')).toBeDefined();
        await wrapper.get('[data-workflow-template="core:approval"]').trigger('click');
        expect(wrapper.emitted('apply')).toBeUndefined();
        expect(wrapper.get('.preview').attributes('data-editable')).toBe('false');
        expect(wrapper.get('.preview').text()).toBe('4');
        await wrapper.get('.sw-workflow-studio-template-picker__apply').trigger('click');
        expect(wrapper.emitted('apply')).toEqual([['core:approval']]);
        wrapper.unmount();
    });
    it('keeps the apply action unavailable in read-only mode', async () => {
        const wrapper = await createWrapper(false);
        await wrapper.get('[data-workflow-template="core:approval"]').trigger('click');
        await wrapper.get('.sw-workflow-studio-template-picker__apply').trigger('click');
        expect(wrapper.emitted('apply')).toBeUndefined();
        wrapper.unmount();
    });
});
