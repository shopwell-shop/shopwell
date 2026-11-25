/** @sw-package framework */
import { effectScope } from 'vue';
import ApprovalDesignerService from './approval-designer.service';
import { APPROVAL_NODE_STEP } from './approval-editing.extension';
import { canConnect, useApprovalEditorSession } from './approval-editor-session';
import type { ApprovalDesignGraph } from '../service/approval-flow-design.api.service';

function document(): ApprovalDesignGraph {
    return {
        id: 'flow',
        schemaVersion: '1.0.0',
        meta: { name: 'Approval', editingMode: 'free', formSchemaRef: 'payment:transfer' },
        nodes: ['first', 'second'].map((id) => ({
            id,
            type: 'core:approval',
            typeVersion: '1.0.0',
            config: { pluginValue: { keep: true } },
            position: { x: 0, y: 0 },
            ports: [
                { id: 'in', direction: 'input', channel: 'control', cardinality: 'one' },
                { id: 'out', direction: 'output', channel: 'control', cardinality: 'one' },
            ],
            extensions: {},
        })),
        connections: [],
        layout: { algorithm: 'core:layered', nodes: [] },
        extensions: {},
    };
}

describe('approval editor session', () => {
    it('undoes a move without losing the node configuration', () => {
        const scope = effectScope();
        const designer = new ApprovalDesignerService();
        const session = scope.run(useApprovalEditorSession);
        if (!session) {
            throw new Error('Could not open the editor session');
        }
        session.load(designer.createSession(document(), {}), true);

        session.execute('approval:move-node', { id: 'first', position: { x: 164, y: 0 } });
        expect(session.isDirty.value).toBe(true);
        session.editorSession.value?.undo();
        expect(session.isDirty.value).toBe(false);
        session.editorSession.value?.redo();
        expect(session.graph.value?.nodes[0]).toMatchObject({
            position: { x: 164, y: 0 },
            config: { pluginValue: { keep: true } },
        });

        scope.stop();
    });

    it('installs plugin tools and events independently in each editor session', () => {
        const designer = new ApprovalDesignerService();
        const destroy = jest.fn();
        const changed = jest.fn();
        designer.registerExtension({
            name: 'expense:designer',
            setup({ commands, ui, events }) {
                commands.register({
                    name: 'expense:rename',
                    execute(graph) {
                        graph.meta.name = 'Expense review';

                        return graph;
                    },
                });
                ui.registerAction({
                    name: 'expense:rename',
                    position: 'toolbar',
                    labelKey: 'expense.rename',
                    command: 'expense:rename',
                });
                events.subscribe('document.changed', changed);
                return destroy;
            },
        });

        const editor = designer.createSession(document(), {});
        const viewer = designer.createSession(document(), { canEdit: () => false });

        expect(editor.actions('toolbar').map((action) => action.name)).toEqual(['expense:rename']);
        expect(viewer.runAction('expense:rename')).toBe(false);
        expect(editor.runAction('expense:rename')).toBe(true);
        expect(editor.getState().document.meta.name).toBe('Expense review');
        expect(viewer.getState().document.meta.name).toBe('Approval');
        expect(changed).toHaveBeenCalledTimes(1);

        editor.undo();
        expect(editor.getState().document.meta.name).toBe('Approval');
        editor.dispose();
        viewer.dispose();
        expect(destroy).toHaveBeenCalledTimes(2);
    });

    it('inserts and removes a serial node without discarding the incoming connection configuration', () => {
        const graph = document();
        graph.meta.editingMode = 'oa';
        graph.nodes[1].position.y = APPROVAL_NODE_STEP;
        const connection = {
            id: 'original',
            source: 'first',
            sourcePort: 'out',
            target: 'second',
            targetPort: 'in',
            condition: { kind: 'expression', expression: 'amount > 100' },
            extensions: { 'expense:rule': true },
        };
        graph.connections.push(connection);

        const editor = new ApprovalDesignerService().createSession(graph, {});
        editor.execute('approval:insert-node', {
            connectionId: 'original',
            node: { ...graph.nodes[0], id: 'review' },
            nextConnectionId: 'next',
        });

        const inserted = editor.getState().document;
        expect(inserted.connections).toEqual([
            { ...connection, target: 'review' },
            {
                id: 'next',
                source: 'review',
                sourcePort: 'out',
                target: 'second',
                targetPort: 'in',
                condition: { kind: 'always' },
                extensions: {},
            },
        ]);
        expect(inserted.nodes.map((node) => [node.id, node.position.y])).toEqual([
            ['first', 0],
            ['second', APPROVAL_NODE_STEP * 2],
            ['review', APPROVAL_NODE_STEP],
        ]);
        editor.undo();
        expect(editor.getState().document).toEqual(graph);

        editor.redo();
        editor.execute('approval:remove-node', 'review');
        expect(editor.getState().document.connections).toEqual([connection]);
        editor.dispose();
    });

    it('rejects saturated ports and cycles while leaving valid connections available', () => {
        const graph = document();
        const forward = { source: 'first', sourcePort: 'out', target: 'second', targetPort: 'in' };
        const backward = { source: 'second', sourcePort: 'out', target: 'first', targetPort: 'in' };
        expect(canConnect(graph, forward)).toBe(true);

        graph.connections.push({ ...forward, id: 'edge', condition: { kind: 'always' }, extensions: {} });

        expect(canConnect(graph, forward)).toBe(false);
        expect(canConnect(graph, backward)).toBe(false);
    });
});
