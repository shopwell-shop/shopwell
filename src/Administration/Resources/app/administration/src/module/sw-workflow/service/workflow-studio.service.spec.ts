/** @sw-package framework */
import './index';
import { createStructuredWorkflowDraft as createWorkflowDraft } from '../util/workflow-template.util';
import type { WorkflowNodeTypeSpecification } from 'src/core/workflow/workflow.types';

const automatic: WorkflowNodeTypeSpecification = {
    name: 'example:automatic',
    version: 1,
    kind: 'activity',
    inputPorts: ['in'],
    outputPorts: ['out'],
};

describe('workflow studio extension contract', () => {
    it('installs a complete automatic-node extension and releases its resources from an open editor', () => {
        const studio = Shopwell.Service('workflowStudioService');
        const document = createWorkflowDraft();
        const session = Shopwell.Service('workflowExtensionService').createSession(document);
        const unregister = studio.register({
            name: 'example:automation',
            nodes: [
                {
                    type: automatic.name,
                    version: automatic.version,
                    presentation: { icon: 'regular-cog', tone: 'blue', settingsComponent: 'example-automatic-settings' },
                },
            ],
            templates: [
                {
                    name: 'example:automatic',
                    titleKey: 'example.title',
                    descriptionKey: 'example.description',
                    icon: 'regular-cog',
                    available: () => true,
                    create: () => document,
                },
            ],
            layouts: [
                {
                    name: 'example:horizontal',
                    policies: ['example:automatic'],
                    layout: (graph) =>
                        Object.fromEntries(graph.nodes.map((node, index) => [node.id, { x: index * 300, y: 0 }])),
                    route: (source, target) => ({
                        path: 'M0,0L300,0',
                        points: [source, target],
                        label: source,
                        insert: target,
                    }),
                },
            ],
            fieldProviders: [
                { name: 'example:input', provide: () => [{ path: 'input.orderId', label: 'Order', type: 'text' }] },
            ],
            diagnostics: [
                { name: 'example:required', check: () => [{ code: 'example_missing', nodeId: 'start', path: '/nodes/0' }] },
            ],
            editor: {
                name: 'example:automatic-tools',
                setup(context) {
                    context.commands.register({
                        name: 'example:rename',
                        execute(draft) {
                            draft.nodes[0].config = { label: 'Automatic' };
                            return draft;
                        },
                    });
                    context.ui.registerAction({
                        name: 'example:rename',
                        position: 'node-menu',
                        command: 'example:rename',
                        labelKey: 'example.rename',
                    });
                },
            },
        });

        expect(Shopwell.Service('workflowNodePresentationService').get(automatic.name, 1).settingsComponent).toBe(
            'example-automatic-settings',
        );
        expect(Shopwell.Service('workflowLayoutService').forPolicy('example:automatic')(document, []).end).toEqual({
            x: 300,
            y: 0,
        });
        expect(
            Shopwell.Service('workflowLayoutService').connectionRouter('example:automatic')({ x: 0, y: 0 }, { x: 300, y: 0 })
                .path,
        ).toBe('M0,0L300,0');
        expect(Shopwell.Service('workflowFieldService').getFields(document, document.nodes[0])).toContainEqual({
            path: 'input.orderId',
            label: 'Order',
            type: 'text',
        });
        expect(studio.diagnose(document, [automatic])).toContainEqual({
            code: 'example_missing',
            nodeId: 'start',
            path: '/nodes/0',
        });
        expect(session.runAction('example:rename', { nodeId: 'start' })).toBe(true);
        expect(session.getState().document.nodes[0].config).toEqual({ label: 'Automatic' });
        session.undo();
        expect(session.getState().document).toEqual(document);

        unregister();
        expect(session.canExecute('example:rename')).toBe(false);
        expect(session.actions('node-menu')).toEqual([]);
        expect(Shopwell.Service('workflowNodePresentationService').get(automatic.name, 1).settingsComponent).toBeUndefined();
        expect(Shopwell.Service('workflowTemplateService').get('example:automatic')).toBeUndefined();
        expect(studio.diagnose(document, [automatic])).toEqual([]);
        expect(Shopwell.Service('workflowLayoutService').forPolicy('example:automatic')(document, []).end.y).toBeGreaterThan(
            0,
        );
        session.dispose();
    });

    it('rolls back an incomplete bundle when another contribution already owns the identifier', () => {
        const studio = Shopwell.Service('workflowStudioService');
        const template = {
            name: 'example:existing',
            titleKey: 'example.title',
            descriptionKey: 'example.description',
            icon: 'regular-cog',
            available: () => true,
            create: createWorkflowDraft,
        };
        const unregister = studio.register({ name: 'example:first', templates: [template] });

        expect(() =>
            studio.register({
                name: 'example:broken',
                nodes: [{ type: automatic.name, version: 1, presentation: { icon: 'regular-cog', tone: 'blue' } }],
                templates: [template],
            }),
        ).toThrow('Duplicate');
        expect(Shopwell.Service('workflowNodePresentationService').get(automatic.name, 1).icon).toBe('regular-flow');
        expect(Shopwell.Service('workflowTemplateService').get(template.name)).toEqual(template);
        unregister();
    });
});
