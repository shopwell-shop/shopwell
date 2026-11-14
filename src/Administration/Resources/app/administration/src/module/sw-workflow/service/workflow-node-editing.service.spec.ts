/** @sw-package framework */
import { CanvasEditor } from 'src/core/canvas-editor/canvas-editor';
import { createWorkflowDraft } from 'src/core/workflow/workflow-document';
import type { WorkflowNodeTypeSpecification } from 'src/core/workflow/workflow.types';
import WorkflowNodeEditingService from './workflow-node-editing.service';
import type { WorkflowNodeEditingStrategy } from './editing/structured-workflow-editing.strategy';

describe('workflow editing extension boundary', () => {
    it('registers a multi-input node strategy without modifying the editor core', () => {
        const service = new WorkflowNodeEditingService();
        const type: WorkflowNodeTypeSpecification = {
            name: 'example:join',
            version: 1,
            kind: 'activity',
            inputPorts: ['left', 'right'],
            outputPorts: ['done'],
        };
        const strategy: WorkflowNodeEditingStrategy = {
            canInsert: () => true,
            insert: (document, specification, connection, id) => ({
                ...document,
                nodes: [
                    ...document.nodes,
                    { id, type: specification.name, typeVersion: specification.version, config: { join: 'all' } },
                ],
            }),
            canRemove: () => true,
            remove: (document, id) => ({ ...document, nodes: document.nodes.filter((node) => node.id !== id) }),
        };
        service.register(type.name, type.version, strategy);
        const editor = new CanvasEditor(createWorkflowDraft());
        editor.execute((draft) => service.get(type.name, type.version).insert(draft, type, '', 'join'));
        expect(editor.document.nodes[0].config).toEqual({ join: 'all' });
        editor.undo();
        expect(editor.document.nodes).toEqual([]);
        editor.redo();
        expect(editor.document.nodes[0].type).toBe('example:join');
    });
});
