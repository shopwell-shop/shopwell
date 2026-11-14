/** @sw-package framework */
import { CanvasEditorSession } from '../canvas-editor/canvas-editor-session';
import { createWorkflowDraft } from './workflow-document';
import type { WorkflowDocument } from './workflow.types';
import type { WorkflowExtension } from './workflow-extension.types';

describe('workflow document editing', () => {
    it('creates independent node and connection collections for each new draft', () => {
        const first = createWorkflowDraft();
        const second = createWorkflowDraft();

        first.nodes.push({ id: 'operation', type: 'example:operation', typeVersion: 3, config: {} });
        first.connections.push({
            id: 'unfinished',
            source: 'operation',
            sourcePort: 'out',
            target: 'missing',
            targetPort: 'in',
        });

        expect(second.nodes).toEqual([]);
        expect(second.connections).toEqual([]);
    });

    it('edits domain data through the generic canvas session without installing approval capabilities', () => {
        const document = createWorkflowDraft();
        const session = new CanvasEditorSession<WorkflowDocument>(document);
        const extension: WorkflowExtension = {
            name: 'example:automation',
            setup(context) {
                context.commands.register({
                    name: 'example:add-operation',
                    execute(draft) {
                        draft.nodes.push({
                            id: 'operation',
                            type: 'example:operation',
                            typeVersion: 3,
                            config: { endpoint: '/orders' },
                        });
                        return draft;
                    },
                });
            },
        };
        session.install(extension);

        expect(session.execute('example:add-operation')).toBe(true);
        session.selectNode('operation');
        expect(session.getState().selection.nodeId).toBe('operation');
        session.undo();
        expect(session.getState().document).toEqual(document);
        expect(session.getState().selection.nodeId).toBeNull();
        session.redo();
        expect(session.getState().document.nodes[0].typeVersion).toBe(3);
        expect(session.getState().document.nodes[0].config).toEqual({ endpoint: '/orders' });
        session.dispose();
    });
});
