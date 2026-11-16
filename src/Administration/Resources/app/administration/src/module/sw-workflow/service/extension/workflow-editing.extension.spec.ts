/** @sw-package framework */
import type { WorkflowDocument } from 'src/core/workflow/workflow.types';
import createWorkflowEditingExtension from './workflow-editing.extension';
import { CanvasEditorSession } from 'src/core/canvas-editor/canvas-editor-session';
import { createStructuredWorkflowDraft } from '../../util/workflow-template.util';
import ApprovalWorkflowEditingStrategy from '../editing/approval-workflow-editing.strategy';
import WorkflowNodeEditingService from '../workflow-node-editing.service';
import WorkflowTemplateService from '../workflow-template.service';
import type { WorkflowNodeTypeSpecification } from 'src/core/workflow/workflow.types';

const type: WorkflowNodeTypeSpecification = {
    name: 'example:review',
    version: 1,
    kind: 'activity',
    inputPorts: ['in'],
    outputPorts: ['out'],
};
function createSession() {
    const session = new CanvasEditorSession<WorkflowDocument>(createStructuredWorkflowDraft());
    session.install(
        createWorkflowEditingExtension({
            editing: new WorkflowNodeEditingService(),
            templates: new WorkflowTemplateService(),
            nodeTypes: () => [type],
            templateContext: { translate: (key) => key },
        }),
    );
    return session;
}

describe('workflow editing commands', () => {
    it('inserts, updates and removes through the shared command history', () => {
        const session = createSession();
        expect(
            session.execute('core:insert-node', { type: type.name, version: 1, connectionId: 'start-end', id: 'review' }),
        ).toBe(true);
        expect(session.getState().document.nodes.find((node) => node.id === 'review')).toBeDefined();
        session.execute('core:update-node', { nodeId: 'review', patch: { label: 'Review' } });
        expect(session.getState().document.nodes.find((node) => node.id === 'review')?.config).toEqual({ label: 'Review' });
        session.execute('core:remove-node', { nodeId: 'review' });
        expect(session.getState().document).toEqual(createStructuredWorkflowDraft());
        session.undo();
        expect(session.getState().document.nodes.find((node) => node.id === 'review')?.config).toEqual({ label: 'Review' });
        session.dispose();
    });
    it('rejects unknown types, malformed payloads and structural deletion without creating history', () => {
        const session = createSession();
        expect(
            session.execute('core:insert-node', { type: 'missing', version: 1, connectionId: 'start-end', id: 'bad' }),
        ).toBe(false);
        expect(session.execute('core:update-node', { nodeId: 'start' })).toBe(false);
        expect(session.execute('core:remove-node', { nodeId: 'start' })).toBe(false);
        expect(session.getState().canUndo).toBe(false);
        session.dispose();
    });
});

describe('approval command history', () => {
    it('restores the complete approval and rejection branch through undo and redo', () => {
        const approval = { ...type, name: 'core:approval', outputPorts: ['approved', 'rejected'] };
        const editing = new WorkflowNodeEditingService();
        editing.register(approval.name, approval.version, new ApprovalWorkflowEditingStrategy());
        const session = new CanvasEditorSession<WorkflowDocument>(createStructuredWorkflowDraft());
        session.install(
            createWorkflowEditingExtension({
                editing,
                templates: new WorkflowTemplateService(),
                nodeTypes: () => [approval],
                templateContext: { translate: (key) => key },
            }),
        );
        session.execute('core:insert-node', { type: approval.name, version: 1, connectionId: 'start-end', id: 'approval' });
        const inserted = session.getState().document;
        expect(session.execute('core:remove-node', { nodeId: 'approval' })).toBe(true);
        expect(session.getState().document).toEqual(createStructuredWorkflowDraft());
        session.undo();
        expect(session.getState().document).toEqual(inserted);
        session.redo();
        expect(session.getState().document).toEqual(createStructuredWorkflowDraft());
        session.dispose();
    });
});
