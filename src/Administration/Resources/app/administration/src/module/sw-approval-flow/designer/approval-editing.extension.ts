/** @sw-package framework */
import type { CanvasExtension } from 'src/core/canvas-editor/canvas-extension.types';
import type { ApprovalDesignGraph } from '../service/approval-flow-design.api.service';
import { canConnect } from './approval-editor-session';
import type { ApprovalDesignConnection, ApprovalDesignNode } from './approval-editor-session';

/** @private */
export const APPROVAL_NODE_STEP = 152;

/** @private */
export interface ApprovalEditPayloads {
    'approval:add-node': ApprovalDesignNode;
    'approval:insert-node': { connectionId: string; node: ApprovalDesignNode; nextConnectionId: string };
    'approval:move-node': { id: string; position: ApprovalDesignNode['position'] };
    'approval:connect': ApprovalDesignConnection;
    'approval:remove-node': string;
    'approval:remove-connection': string;
    'approval:set-configuration': { id: string; config: ApprovalDesignNode['config'] };
    'approval:set-business': { type: string; formSchemaId: string };
    'approval:set-name': string;
}

/** @private */
export type ApprovalEditCommand = keyof ApprovalEditPayloads;

function insertNode(
    document: ApprovalDesignGraph,
    { connectionId, node, nextConnectionId }: ApprovalEditPayloads['approval:insert-node'],
): void {
    const connection = document.connections.find((item) => item.id === connectionId);
    const source = document.nodes.find((item) => item.id === connection?.source);
    const target = document.nodes.find((item) => item.id === connection?.target);
    const input = node.ports.find((port) => port.direction === 'input');
    const output = node.ports.find((port) => port.direction === 'output');

    if (!connection || !source || !target || !input || !output) {
        return;
    }

    const sourcePosition = source.position;
    const targetPosition = target.position;
    const gap = targetPosition.y - sourcePosition.y;

    if (document.meta.editingMode === 'oa' && sourcePosition.x === targetPosition.x && gap > 0) {
        const nextRow = sourcePosition.y + APPROVAL_NODE_STEP;
        const shift = Math.max(0, nextRow + APPROVAL_NODE_STEP - targetPosition.y);
        node.position = { x: sourcePosition.x, y: nextRow };

        for (const item of document.nodes) {
            if (item.position.y >= targetPosition.y) {
                item.position = { x: item.position.x, y: item.position.y + shift };
            }
        }
    } else {
        node.position = {
            x: (sourcePosition.x + targetPosition.x) / 2,
            y: (sourcePosition.y + targetPosition.y) / 2,
        };
    }

    document.nodes.push(node);
    document.connections.push({
        id: nextConnectionId,
        source: node.id,
        sourcePort: output.id,
        target: connection.target,
        targetPort: connection.targetPort,
        condition: { kind: 'always' },
        extensions: {},
    });
    connection.target = node.id;
    connection.targetPort = input.id;
}

function removeNode(document: ApprovalDesignGraph, id: string): void {
    const node = document.nodes.find((item) => item.id === id);
    if (!node || node.type === 'core:start' || node.type === 'core:end') {
        return;
    }

    const incoming = document.connections.filter((edge) => edge.target === id);
    const outgoing = document.connections.filter((edge) => edge.source === id);

    document.nodes = document.nodes.filter((item) => item.id !== id);
    document.connections = document.connections.filter((edge) => edge.source !== id && edge.target !== id);
    document.layout.nodes = document.layout.nodes.filter((item) => item.nodeId !== id);

    if (document.meta.editingMode !== 'oa' || incoming.length !== 1 || outgoing.length !== 1) {
        return;
    }

    const [next] = outgoing;
    if (next.condition.kind !== 'always' || Object.keys(next.extensions).length > 0) {
        return;
    }

    const replacement = { ...incoming[0], target: next.target, targetPort: next.targetPort };
    if (canConnect(document, replacement)) {
        document.connections.push(replacement);
    }
}

/** @private */
export const approvalEditing: CanvasExtension<ApprovalDesignGraph> = {
    name: 'approval:editing',
    setup({ commands }) {
        function register<K extends ApprovalEditCommand>(
            name: K,
            edit: (document: ApprovalDesignGraph, payload: ApprovalEditPayloads[K]) => void,
        ): void {
            commands.register({
                name,
                execute(document, payload) {
                    edit(document, payload as ApprovalEditPayloads[K]);

                    return document;
                },
            });
        }

        register('approval:add-node', (document, node) => {
            document.nodes.push(node);
        });

        register('approval:insert-node', insertNode);

        register('approval:move-node', (document, { id, position }) => {
            const node = document.nodes.find((item) => item.id === id);
            if (node) {
                node.position = position;
            }
        });

        register('approval:connect', (document, connection) => {
            if (canConnect(document, connection)) {
                document.connections.push(connection);
            }
        });

        register('approval:remove-node', removeNode);

        register('approval:remove-connection', (document, id) => {
            document.connections = document.connections.filter((item) => item.id !== id);
        });

        register('approval:set-configuration', (document, { id, config }) => {
            const node = document.nodes.find((item) => item.id === id);
            if (node) {
                node.config = config;
            }
        });

        register('approval:set-business', (document, { type, formSchemaId }) => {
            const start = document.nodes.find((node) => node.type === 'core:start');
            if (start) {
                start.config.businessType = type;
                document.meta.formSchemaRef = formSchemaId;
            }
        });

        register('approval:set-name', (document, name) => {
            document.meta.name = name;
        });
    },
};
