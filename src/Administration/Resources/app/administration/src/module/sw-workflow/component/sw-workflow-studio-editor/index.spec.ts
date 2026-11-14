/** @sw-package framework */
import '../../service/extension/approval-workflow.registration';
import StructuredWorkflowEditingStrategy from '../../service/editing/structured-workflow-editing.strategy';
import '../../service';
import WorkflowNodeEditingService from '../../service/workflow-node-editing.service';
import WorkflowExtensionService from '../../service/workflow-extension.service';
import type { WorkflowExtensionContext } from 'src/core/workflow/workflow-extension.types';
import { mount } from '@vue/test-utils';
import { createStructuredWorkflowDraft as createWorkflowDraft } from 'src/module/sw-workflow/util/workflow-template.util';
import type { WorkflowDocument, WorkflowNodeTypeSpecification } from 'src/core/workflow/workflow.types';
import WorkflowNodePresentationService from '../../service/workflow-node-presentation.service';

const approval: WorkflowNodeTypeSpecification = {
    name: 'core:approval',
    version: 1,
    kind: 'activity',
    inputPorts: ['in'],
    outputPorts: ['approved', 'rejected'],
};
async function createWrapper(
    allowEdit = true,
    editing = new WorkflowNodeEditingService(),
    extensions = new WorkflowExtensionService(),
) {
    return mount(await wrapTestComponent('sw-workflow-studio-editor', { sync: true }), {
        props: { document: createWorkflowDraft(), nodeTypes: [approval], allowEdit },
        global: {
            provide: {
                workflowExtensionService: extensions,
                workflowStudioService: Shopwell.Service('workflowStudioService'),
                workflowTemplateService: Shopwell.Service('workflowTemplateService'),
                workflowNodeEditingService: editing,
                workflowNodePresentationService: new WorkflowNodePresentationService(),
            },
            stubs: {
                'sw-workflow-studio-toolbar': await wrapTestComponent('sw-workflow-studio-toolbar', { sync: true }),
                'sw-workflow-studio-node-picker': await wrapTestComponent('sw-workflow-studio-node-picker', { sync: true }),
                'sw-workflow-studio-template-picker': {
                    props: ['labels', 'nodeTypes', 'allowEdit'],
                    emits: ['apply'],
                    template: '<button class="apply-template" @click="$emit(\'apply\', \'core:approval\')">Apply</button>',
                },
                'sw-canvas-contributions': await wrapTestComponent('sw-canvas-contributions', {
                    sync: true,
                }),
                'example-workflow-panel': { template: '<section class="example-workflow-panel">Plugin panel</section>' },
                'sw-modal': {
                    template: '<section role="dialog"><slot/><footer><slot name="modal-footer"/></footer></section>',
                },
                'sw-workflow-studio-node-settings': {
                    emits: ['update-config'],
                    template:
                        '<button class="patch" @click="$emit(\'update-config\', { label: \'Reviewed\' })">Edit</button>',
                },
                'sw-workflow-studio-canvas': {
                    props: ['document'],
                    emits: ['select-node'],
                    template:
                        '<div><div v-for="node in document.nodes" :key="node.id"><button :data-node-id="node.id" @click="$emit(\'select-node\', node.id)">{{ node.type }}</button><slot name="node-tools" :node-id="node.id" /></div></div>',
                },
            },
        },
    });
}

describe('sw-workflow-studio-editor', () => {
    it('opens settings from the node toolbar without changing the document', async () => {
        const wrapper = await createWrapper();

        await wrapper.get('[data-edit-node="start"]').trigger('click');

        expect(wrapper.find('[role="dialog"]').exists()).toBe(true);
        expect(wrapper.emitted('update:document')).toBeUndefined();
        wrapper.unmount();
    });

    it('hides editing tools in read-only mode while keeping node details available', async () => {
        const wrapper = await createWrapper(false);

        expect(wrapper.find('[data-edit-node]').exists()).toBe(false);
        await wrapper.get('[data-node-id="start"]').trigger('click');

        expect(wrapper.find('[role="dialog"]').exists()).toBe(true);
        expect(wrapper.find('.sw-workflow-studio-editor__save').exists()).toBe(false);
        wrapper.unmount();
    });

    it('passes the toolbar node to a contributed action without opening node settings', async () => {
        const extensions = new WorkflowExtensionService();
        let receivedPayload: unknown;
        extensions.register({
            name: 'example:node-tools',
            setup(context) {
                context.commands.register({
                    name: 'example:inspect-node',
                    mutatesDocument: false,
                    execute(document, payload) {
                        receivedPayload = payload;
                        return document;
                    },
                });
                context.ui.registerAction({
                    name: 'example:inspect-node',
                    command: 'example:inspect-node',
                    position: 'node-menu',
                    labelKey: 'global.default.view',
                    payload: (_state, target) => target,
                });
            },
        });
        const wrapper = await createWrapper(true, new WorkflowNodeEditingService(), extensions);

        await wrapper.findAll('[data-canvas-action="example:inspect-node"]')[0].trigger('click');

        expect(receivedPayload).toEqual({ nodeId: 'start' });
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
        expect(wrapper.emitted('update:document')).toBeUndefined();
        wrapper.unmount();
    });

    it('opens node editing on demand and discards changes when canceled', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
        expect(wrapper.find('.sw-workflow-studio-editor__sidebar').exists()).toBe(false);
        await wrapper.find('[data-node-id="start"]').trigger('click');
        await wrapper.find('.patch').trigger('click');
        expect(wrapper.emitted('update:document')).toBeUndefined();
        await wrapper.find('.sw-workflow-studio-editor__cancel').trigger('click');
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
        expect(wrapper.emitted('update:document')).toBeUndefined();
        wrapper.unmount();
    });

    it('hosts an independent toolbar extension, routes its command through undo and disposes it on unmount', async () => {
        const extensions = new WorkflowExtensionService();
        let retained: WorkflowExtensionContext | undefined;
        const cleanup = jest.fn();
        extensions.register({
            name: 'example:tools',
            setup(context) {
                retained = context;
                context.commands.register({
                    name: 'example:rename',
                    execute(document) {
                        document.nodes[0].config = { label: 'Plugin change' };
                        return document;
                    },
                });
                context.ui.registerAction({
                    name: 'example:rename',
                    command: 'example:rename',
                    position: 'toolbar',
                    labelKey: 'example.rename',
                });
                context.events.subscribe('node.click', (event) => event.preventDefault());
                return cleanup;
            },
        });
        const wrapper = await createWrapper(true, new WorkflowNodeEditingService(), extensions);
        await wrapper.find('[data-node-id="start"]').trigger('click');
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
        await wrapper.find('[data-canvas-action="example:rename"]').trigger('click');
        expect((wrapper.emitted('update:document')?.[0][0] as WorkflowDocument).nodes[0].config).toEqual({
            label: 'Plugin change',
        });
        await wrapper.find('.sw-workflow-studio-toolbar__history button').trigger('click');
        expect(wrapper.emitted('update:document')?.[1][0]).toEqual(createWorkflowDraft());
        wrapper.unmount();
        expect(cleanup).toHaveBeenCalledTimes(1);
        expect(() => retained?.getState()).toThrow();
    });

    it('routes settings through an extension strategy as one undoable document edit', async () => {
        const editing = new WorkflowNodeEditingService();
        editing.register(
            'core:approval',
            1,
            Object.assign(new StructuredWorkflowEditingStrategy(), {
                downstreamLabel: 'Updated downstream',
                updateConfig(document: WorkflowDocument, id: string, patch: Record<string, unknown>) {
                    return {
                        ...document,
                        nodes: document.nodes.map((node) =>
                            node.id === id
                                ? { ...node, config: patch }
                                : node.id === 'end'
                                  ? { ...node, config: { label: this.downstreamLabel } }
                                  : node,
                        ),
                    };
                },
            }),
        );
        const wrapper = await createWrapper(true, editing);
        await wrapper.find('.sw-workflow-studio-toolbar__templates').trigger('click');
        await wrapper.find('.apply-template').trigger('click');
        await wrapper.find('[data-node-id="approval"]').trigger('click');
        await wrapper.find('.patch').trigger('click');
        expect(wrapper.emitted('update:document')).toHaveLength(1);
        await wrapper.find('.sw-workflow-studio-editor__save').trigger('click');
        const edited = wrapper.emitted('update:document')?.[1][0] as WorkflowDocument;
        expect(edited.nodes.find((node) => node.id === 'approval')?.config).toEqual({ label: 'Reviewed' });
        expect(edited.nodes.find((node) => node.id === 'end')?.config).toEqual({ label: 'Updated downstream' });
        await wrapper.find('.sw-workflow-studio-toolbar__history button').trigger('click');
        expect(wrapper.emitted('update:document')?.[2][0]).toEqual(wrapper.emitted('update:document')?.[0][0]);
        wrapper.unmount();
    });

    it('loads a template and undoes it as one operation', async () => {
        const wrapper = await createWrapper();
        await wrapper.find('.sw-workflow-studio-toolbar__templates').trigger('click');
        await wrapper.find('.apply-template').trigger('click');
        const document = wrapper.emitted('update:document')?.[0][0] as WorkflowDocument;
        expect(document.nodes.map((node) => node.type)).toContain('core:approval');
        await wrapper.find('.sw-workflow-studio-toolbar__history button').trigger('click');
        expect(wrapper.emitted('update:document')?.[1][0]).toEqual(createWorkflowDraft());
        wrapper.unmount();
    });

    it('prevents template replacement in a read-only session', async () => {
        const wrapper = await createWrapper(false);
        const button = wrapper.find('.sw-workflow-studio-toolbar__templates');
        expect(button.attributes('disabled')).toBeDefined();
        await button.trigger('click');
        expect(wrapper.emitted('update:document')).toBeUndefined();
        wrapper.unmount();
    });

    it('exports the same document shown in the editor', async () => {
        const wrapper = await createWrapper();
        await wrapper.find('.sw-workflow-studio-toolbar__templates').trigger('click');
        await wrapper.find('.apply-template').trigger('click');
        const document = wrapper.emitted('update:document')?.[0][0];
        const url = wrapper.find('.sw-workflow-studio-toolbar__export').attributes('href') ?? '';
        expect(JSON.parse(decodeURIComponent(url.split(',')[1]))).toEqual(document);
        wrapper.unmount();
    });
    it('discards a staged node edit when an extension changes selection', async () => {
        const extensions = new WorkflowExtensionService();
        let context: WorkflowExtensionContext | undefined;
        extensions.register({
            name: 'example:selection',
            setup(value) {
                context = value;
            },
        });
        const wrapper = await createWrapper(true, new WorkflowNodeEditingService(), extensions);
        await wrapper.find('[data-node-id="start"]').trigger('click');
        await wrapper.find('.patch').trigger('click');
        context?.selection.selectNode('end');
        await flushPromises();
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
        expect(wrapper.emitted('update:document')).toBeUndefined();
        wrapper.unmount();
    });
});

describe('workflow extension scenarios', () => {
    it('renders and removes plugin diagnostics in an open editor without mutating its document', async () => {
        const wrapper = await createWrapper();
        const studio = Shopwell.Service('workflowStudioService');
        const unregister = studio.register({
            name: 'example:review',
            diagnostics: [
                {
                    name: 'example:missing-input',
                    check(document) {
                        document.nodes[0].config = { label: 'Diagnostic must not write this' };
                        return [{ code: 'example_missing_input', nodeId: 'start', path: '/nodes/0' }];
                    },
                },
            ],
        });
        await flushPromises();

        expect(wrapper.get('.sw-workflow-studio-editor__diagnostics').findAll('button')).toHaveLength(1);
        expect(wrapper.emitted('update:document')).toBeUndefined();

        unregister();
        await flushPromises();
        expect(wrapper.get('.sw-workflow-studio-editor__diagnostics').findAll('button')).toHaveLength(0);
        wrapper.unmount();
    });

    it('opens a contributed panel only while its extension is installed', async () => {
        const wrapper = await createWrapper(
            true,
            new WorkflowNodeEditingService(),
            Shopwell.Service('workflowExtensionService'),
        );
        const unregister = Shopwell.Service('workflowStudioService').register({
            name: 'example:panel-tools',
            editor: {
                name: 'example:panel-editor',
                setup(context) {
                    context.ui.registerComponent({
                        name: 'example:panel',
                        position: 'panel',
                        component: 'example-workflow-panel',
                    });
                },
            },
        });
        await flushPromises();

        expect(wrapper.get('.sw-workflow-studio-editor__extension-panel').text()).toContain('Plugin panel');

        unregister();
        await flushPromises();
        expect(wrapper.find('.sw-workflow-studio-editor__extension-panel').exists()).toBe(false);
        wrapper.unmount();
    });
});
