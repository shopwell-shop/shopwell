/** @sw-package framework */
import { CanvasEditor } from 'src/core/canvas-editor/canvas-editor';
import type { WorkflowNodeTypeSpecification } from 'src/core/workflow/workflow.types';
import { createStructuredWorkflowDraft } from '../../util/workflow-template.util';
import StructuredWorkflowEditingStrategy from './structured-workflow-editing.strategy';

const action: WorkflowNodeTypeSpecification = {
    name: 'core:action',
    version: 1,
    kind: 'activity',
    inputPorts: ['in'],
    outputPorts: ['out'],
};
const strategy = new StructuredWorkflowEditingStrategy();

describe('structured workflow editing strategy', () => {
    it('inserts and removes a linear node as reversible commands', () => {
        const initial = createStructuredWorkflowDraft();
        const editor = new CanvasEditor(initial);
        editor.execute((draft) => strategy.insert(draft, action, 'start-end', 'task'));
        expect(editor.document.nodes).toHaveLength(3);
        expect(initial.nodes).toHaveLength(2);
        editor.execute((draft) => strategy.remove(draft, 'task'));
        expect(editor.document).toEqual(initial);
        editor.undo();
        expect(editor.document.nodes).toHaveLength(3);
        editor.redo();
        expect(editor.document).toEqual(initial);
    });

    it('creates independent end nodes for additional outcomes', () => {
        const draft = strategy.insert(
            createStructuredWorkflowDraft(),
            { ...action, outputPorts: ['approved', 'rejected'] },
            'start-end',
            'review',
        );
        expect(draft.connections.filter((edge) => edge.source === 'review').map((edge) => edge.target)).toEqual([
            'end',
            'review-end-1',
        ]);
        expect(strategy.canRemove(draft, 'review')).toBe(false);
    });

    it('rejects colliding IDs without changing history', () => {
        const editor = new CanvasEditor(createStructuredWorkflowDraft());
        expect(() => editor.execute((draft) => strategy.insert(draft, action, 'start-end', 'end'))).toThrow();
        expect(editor.canUndo).toBe(false);
    });

    it('does not offer unsupported multi-input insertion or boundary deletion', () => {
        const draft = createStructuredWorkflowDraft();
        expect(strategy.canInsert(draft, { ...action, inputPorts: ['left', 'right'] }, 'start-end')).toBe(false);
        expect(strategy.canRemove(draft, 'start')).toBe(false);
        expect(strategy.remove(draft, 'end')).toEqual(draft);
    });
});
