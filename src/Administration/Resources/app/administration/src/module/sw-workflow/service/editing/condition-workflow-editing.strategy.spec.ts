/** @sw-package framework */
import '../index';
import { canRemoveConditionBranch } from './condition-workflow-editing.strategy';
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
        expect(update.call(strategy, document, 'condition', { branches: [branch('branch-2')] })).toBe(document);
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

    it.each([
        null,
        {},
        [],
        [{ id: 'invalid' }],
        [branch('branch-1'), branch('branch-1')],
    ])('rejects malformed branch replacements without changing the original document: %s', (branches) => {
        const { strategy, document } = setup();
        const original = JSON.stringify(document);
        expect(strategy.updateConfig!(document, 'condition', { branches })).toBe(document);
        expect(JSON.stringify(document)).toBe(original);
    });

    it('rejects a late identifier collision before committing removals or earlier additions', () => {
        const { strategy, document } = setup();
        document.nodes.push({ id: 'condition-branch-3', type: 'core:end', typeVersion: 1, config: { label: 'Keep' } });
        const original = JSON.stringify(document);
        expect(strategy.updateConfig!(document, 'condition', { branches: [branch('branch-2'), branch('branch-3')] })).toBe(
            document,
        );
        expect(JSON.stringify(document)).toBe(original);
    });

    it('keeps a shared branch terminal when its branch or condition is removed', () => {
        const { strategy, document } = setup();
        const branchEdge = document.connections.find((edge) => edge.sourcePort === 'branch-1')!;
        document.connections.push({ ...branchEdge, id: 'shared', source: 'other' });
        expect(strategy.updateConfig!(document, 'condition', { branches: [branch('branch-2')] })).toBe(document);
        expect(strategy.remove(document, 'condition')).toBe(document);
    });

    it('preserves raw incomplete branch configuration, extra fields and the fallback target through edits', () => {
        const { strategy, document } = setup();
        const fallback = document.connections.find((edge) => edge.sourcePort === 'fallback')!;
        fallback.targetPort = 'plugin:entry';
        const branches = [
            { id: 'branch-2', extension: { code: 'custom' } },
            { id: 'branch-1', operator: 'plugin:compare' },
        ];
        const updated = strategy.updateConfig!(document, 'condition', { label: 'Renamed', branches });
        expect(updated.nodes.find((node) => node.id === 'condition')?.config).toEqual({ label: 'Renamed', branches });
        expect(updated.connections.filter((edge) => edge.source === 'condition').map((edge) => edge.sourcePort)).toEqual([
            'branch-2',
            'branch-1',
            'fallback',
        ]);
        expect(updated.connections.find((edge) => edge.sourcePort === 'fallback')).toEqual(fallback);
        const restored = strategy.remove(updated, 'condition');
        expect(restored).toEqual({
            ...createStructuredWorkflowDraft(),
            connections: [{ ...createStructuredWorkflowDraft().connections[0], targetPort: 'plugin:entry' }],
        });
    });
});

describe('condition branch deletion rule', () => {
    it('does not treat shared or configured terminal paths as disposable', () => {
        const document = {
            schemaVersion: 1 as const,
            policy: 'core:structured',
            nodes: [{ id: 'end', type: 'core:end', typeVersion: 1, config: { label: '', description: '' } }],
            connections: [{ id: 'a', source: 'condition', sourcePort: 'branch-a', target: 'end', targetPort: 'in' }],
        };
        expect(canRemoveConditionBranch(document, 'condition', 'branch-a')).toBe(true);
        document.nodes[0].config.label = 'Custom ending';
        expect(canRemoveConditionBranch(document, 'condition', 'branch-a')).toBe(false);
        document.nodes[0].config.label = '';
        document.connections.push({ ...document.connections[0], id: 'b', source: 'other' });
        expect(canRemoveConditionBranch(document, 'condition', 'branch-a')).toBe(false);
    });

    it.each([
        [
            'missing terminal',
            (document: WorkflowDocument) => {
                document.nodes = [];
            },
        ],
        [
            'activity successor',
            (document: WorkflowDocument) => {
                document.nodes[0].type = 'core:approval';
            },
        ],
        [
            'unsupported terminal version',
            (document: WorkflowDocument) => {
                document.nodes[0].typeVersion = 2;
            },
        ],
        [
            'unknown configuration',
            (document: WorkflowDocument) => {
                document.nodes[0].config = { extension: '' };
            },
        ],
        [
            'terminal with an outgoing edge',
            (document: WorkflowDocument) => {
                document.connections.push({
                    id: 'next',
                    source: 'end',
                    sourcePort: 'out',
                    target: 'another',
                    targetPort: 'in',
                });
            },
        ],
        [
            'multiple paths from the port',
            (document: WorkflowDocument) => {
                document.connections.push({ ...document.connections[0], id: 'duplicate' });
            },
        ],
    ])('protects a branch with %s', (_name, change) => {
        const document: WorkflowDocument = {
            schemaVersion: 1,
            policy: 'core:structured',
            nodes: [{ id: 'end', type: 'core:end', typeVersion: 1, config: {} }],
            connections: [{ id: 'branch', source: 'condition', sourcePort: 'branch-a', target: 'end', targetPort: 'in' }],
        };
        change(document);
        expect(canRemoveConditionBranch(document, 'condition', 'branch-a')).toBe(false);
    });
});
