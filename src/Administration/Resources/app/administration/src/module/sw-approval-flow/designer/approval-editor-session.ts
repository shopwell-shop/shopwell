/** @sw-package framework */
import { computed, onScopeDispose, shallowRef } from 'vue';
import type { CanvasEditorSession } from 'src/core/canvas-editor/canvas-editor-session';
import type { CanvasEditorState } from 'src/core/canvas-editor/canvas-extension.types';
import type { ApprovalDesignGraph } from '../service/approval-flow-design.api.service';
import type { ApprovalEditCommand, ApprovalEditPayloads } from './approval-editing.extension';

/** @private */
export type ApprovalDesignNode = ApprovalDesignGraph['nodes'][number];

/** @private */
export type ApprovalDesignConnection = ApprovalDesignGraph['connections'][number];

/** @private */
export type ApprovalConnectionEndpoints = Pick<ApprovalDesignConnection, 'source' | 'target' | 'sourcePort' | 'targetPort'>;

/** @private */
export function canConnect(graph: ApprovalDesignGraph, edge: ApprovalConnectionEndpoints): boolean {
    const source = graph.nodes.find((node) => node.id === edge.source);
    const target = graph.nodes.find((node) => node.id === edge.target);
    const output = source?.ports.find((port) => port.id === edge.sourcePort && port.direction === 'output');
    const input = target?.ports.find((port) => port.id === edge.targetPort && port.direction === 'input');

    if (!output || !input || edge.source === edge.target) {
        return false;
    }

    for (const connection of graph.connections) {
        const sameOutput = connection.source === edge.source && connection.sourcePort === edge.sourcePort;
        const sameInput = connection.target === edge.target && connection.targetPort === edge.targetPort;
        if (
            (sameOutput && sameInput) ||
            (sameOutput && output.cardinality === 'one') ||
            (sameInput && input.cardinality === 'one')
        ) {
            return false;
        }
    }

    return !canReach(graph.connections, edge.target, edge.source);
}

function canReach(connections: ApprovalDesignConnection[], start: string, destination: string): boolean {
    const visited = new Set([start]);
    const pending = [start];

    for (let index = 0; index < pending.length; index += 1) {
        const source = pending[index];
        if (source === destination) {
            return true;
        }

        for (const connection of connections) {
            if (connection.source === source && !visited.has(connection.target)) {
                visited.add(connection.target);
                pending.push(connection.target);
            }
        }
    }

    return false;
}

/** @private */
export function useApprovalEditorSession() {
    const session = shallowRef<CanvasEditorSession<ApprovalDesignGraph> | null>(null);
    const state = shallowRef<CanvasEditorState<ApprovalDesignGraph> | null>(null);
    const graph = shallowRef<ApprovalDesignGraph | null>(null);
    const saved = shallowRef<string | null>(null);

    function load(next: CanvasEditorSession<ApprovalDesignGraph>, persisted: boolean): void {
        session.value?.dispose();
        session.value = next;
        state.value = next.getState();
        graph.value = state.value.document;
        saved.value = persisted ? JSON.stringify(graph.value) : null;

        next.subscribe('document.changed', (document) => {
            graph.value = document;
        });
        next.subscribe('session.changed', () => {
            state.value = next.getState();
        });
    }

    function execute<K extends ApprovalEditCommand>(name: K, payload: ApprovalEditPayloads[K]): boolean {
        return session.value?.execute(name, payload) ?? false;
    }

    onScopeDispose(() => session.value?.dispose());

    return {
        editorSession: session,
        editorState: state,
        graph,
        isDirty: computed(() => graph.value !== null && JSON.stringify(graph.value) !== saved.value),
        load,
        execute,
        acceptSaved: (graphJson: string) => {
            saved.value = graphJson;
        },
    };
}
