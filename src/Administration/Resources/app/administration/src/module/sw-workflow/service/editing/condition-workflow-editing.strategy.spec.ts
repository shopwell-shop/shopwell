/** @sw-package framework */
import '../index';
import { CanvasEditor } from 'src/core/canvas-editor/canvas-editor';
import { createStructuredWorkflowDraft } from '../../util/workflow-template.util';
import type { WorkflowDocument, WorkflowNodeTypeSpecification } from 'src/core/workflow/workflow.types';

const type: WorkflowNodeTypeSpecification = {
    name: 'core:condition',
    version: 1,
    kind: 'activity',
    inputPorts: ['in'],
    outputPorts: ['branch-1', 'fallback'],
};
const branch = (id: string) => ({
    id,
    label: id,
    field: 'order.status',
    fieldType: 'text',
    operator: 'equals',
    value: 'paid',
});
function setup() {
    const strategy = Shopwell.Service('workflowNodeEditingService').get(type.name, 1);
    const draft = createStructuredWorkflowDraft();
    const document = strategy.insert(draft, type, draft.connections[0].id, 'condition');
    return { strategy, document };
}

describe('condition node extension', () => {
    it('keeps the original downstream on the fallback and creates an independent conditional path', () => {
        const { document } = setup();
        expect(document.connections.find((edge) => edge.sourcePort === 'fallback')?.target).toBe('end');
        expect(document.nodes.find((node) => node.id === 'condition')?.config).toHaveProperty('branches');
    });

    it('adds, reorders and removes ports with their edges as undoable edits', () => {
        const { strategy, document } = setup();
        expect(strategy.updateConfig).toBeDefined();
        const editor = new CanvasEditor(document);
        editor.execute((draft) =>
            strategy.updateConfig!(draft, 'condition', { branches: [branch('branch-1'), branch('branch-2')] }),
        );
        const added = editor.document;
        expect(added.connections.filter((edge) => edge.source === 'condition').map((edge) => edge.sourcePort)).toEqual([
            'branch-1',
            'branch-2',
            'fallback',
        ]);
        editor.execute((draft) =>
            strategy.updateConfig!(draft, 'condition', { branches: [branch('branch-2'), branch('branch-1')] }),
        );
        expect(
            editor.document.connections.filter((edge) => edge.source === 'condition').map((edge) => edge.sourcePort),
        ).toEqual(['branch-2', 'branch-1', 'fallback']);
        editor.execute((draft) => strategy.updateConfig!(draft, 'condition', { branches: [branch('branch-2')] }));
        expect(
            editor.document.connections.some((edge) => edge.source === 'condition' && edge.sourcePort === 'branch-1'),
        ).toBe(false);
        editor.undo();
        editor.undo();
        expect(editor.document).toEqual(added);
        editor.undo();
        expect(editor.document).toEqual(document);
    });

    it('preserves a populated branch when removal is attempted', () => {
        const { strategy, document } = setup();
        const edge = document.connections.find((item) => item.sourcePort === 'branch-1')!;
        document.nodes.find((node) => node.id === edge.target)!.config = { label: 'Do not lose my work' };
        const update = strategy.updateConfig!;
        expect(update.call(strategy, document, 'condition', { branches: [branch('branch-2')] })).toEqual(document);
        expect(strategy.canRemove(document, 'condition')).toBe(false);
    });

    it('removes an empty condition and reconnects its fallback in one command', () => {
        const { strategy, document } = setup();
        expect(strategy.canRemove(document, 'condition')).toBe(true);
        expect(strategy.remove(document, 'condition')).toEqual(createStructuredWorkflowDraft());
    });

    it('resolves per-node ports and labels through the registered presentation', () => {
        const { strategy, document } = setup();
        const updated: WorkflowDocument = strategy.updateConfig!(document, 'condition', { branches: [branch('branch-2')] });
        const node = updated.nodes.find((item) => item.id === 'condition')!;
        const presentation = Shopwell.Service('workflowNodePresentationService').get(type.name, 1);
        expect(presentation.settingsComponent).toBe('sw-workflow-condition-settings');
        expect(presentation.resolvePorts!(node, type)).toMatchObject({
            outputPorts: ['branch-2', 'fallback'],
            labels: { 'branch-2': '1. branch-2' },
        });
    });
});
