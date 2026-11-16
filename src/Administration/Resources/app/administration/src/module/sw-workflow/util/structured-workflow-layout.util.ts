/** @sw-package framework */
import type { WorkflowConnection, WorkflowDocument, WorkflowNodeTypeSpecification } from 'src/core/workflow/workflow.types';
import type { WorkflowLayoutContext, WorkflowNodeSize, WorkflowPoint } from 'src/core/workflow/workflow-studio.types';
import {
    workflowNodeSize,
    workflowRowGap,
    WORKFLOW_MINIMUM_SLOT_WIDTH,
    WORKFLOW_NODE_HORIZONTAL_SPACING,
} from './workflow-layout-geometry.util';
import { rankedWorkflowLayout } from './workflow-layout.util';

type LayoutNode = {
    id: string;
    size: WorkflowNodeSize;
    children: LayoutNode[];
    outputPorts: string[];
    incoming: number;
    subtreeWidth: number;
    left: number;
    depth: number;
};

/** Reserves branch subtrees in declared output-port order; non-tree drafts use ranked layout. @private */
export function structuredWorkflowLayout(
    document: WorkflowDocument,
    types: WorkflowNodeTypeSpecification[],
    context: WorkflowLayoutContext = {},
): Record<string, WorkflowPoint> {
    const nodes = indexLayoutNodes(document, types, context);
    const roots = [...nodes.values()].filter((node) => node.incoming === 0);
    const order = [...roots];

    if ([...nodes.values()].some((node) => node.incoming > 1)) {
        return rankedWorkflowLayout(document, types, context);
    }

    // Every node has at most one parent, so breadth-first order visits each tree once.
    // Cycles have no root and remain absent; ranked layout keeps those invalid drafts editable.
    for (let index = 0; index < order.length; index += 1) {
        order.push(...order[index].children);
    }

    if (order.length !== nodes.size) {
        return rankedWorkflowLayout(document, types, context);
    }

    for (const node of [...order].reverse()) {
        const childrenWidth = node.children.reduce((width, child) => width + child.subtreeWidth, 0);
        node.subtreeWidth = Math.max(
            WORKFLOW_MINIMUM_SLOT_WIDTH,
            node.size.width + WORKFLOW_NODE_HORIZONTAL_SPACING,
            childrenWidth,
        );
    }

    return positionForest(roots, order);
}

function indexLayoutNodes(
    document: WorkflowDocument,
    types: WorkflowNodeTypeSpecification[],
    context: WorkflowLayoutContext,
): Map<string, LayoutNode> {
    const nodes = new Map<string, LayoutNode>();
    const outgoing = new Map<string, WorkflowConnection[]>();
    const specifications = new Map(types.map((type) => [`${type.name}@${type.version}`, type]));

    for (const node of document.nodes) {
        nodes.set(node.id, {
            id: node.id,
            size: workflowNodeSize(node, types, context),
            children: [],
            outputPorts:
                context.outputPorts?.[node.id] ?? specifications.get(`${node.type}@${node.typeVersion}`)?.outputPorts ?? [],
            incoming: 0,
            subtreeWidth: 0,
            left: 0,
            depth: 0,
        });
    }

    for (const connection of document.connections) {
        const target = nodes.get(connection.target);

        if (!nodes.has(connection.source) || !target) {
            continue;
        }

        const edges = outgoing.get(connection.source) ?? [];
        edges.push(connection);
        outgoing.set(connection.source, edges);
        target.incoming += 1;
    }

    for (const parent of nodes.values()) {
        const ports = parent.outputPorts;
        const edges = outgoing.get(parent.id) ?? [];
        const portIndex = (port: string) => {
            const index = ports.indexOf(port);
            return index < 0 ? ports.length : index;
        };
        edges.sort((first, second) => portIndex(first.sourcePort) - portIndex(second.sourcePort));

        for (const edge of edges) {
            const child = nodes.get(edge.target);

            if (child) {
                parent.children.push(child);
            }
        }
    }

    return nodes;
}

function positionForest(roots: LayoutNode[], order: LayoutNode[]): Record<string, WorkflowPoint> {
    let forestWidth = 0;

    for (const root of roots) {
        root.left = forestWidth;
        forestWidth += root.subtreeWidth;
    }

    const rows: Array<{ height: number; branches: number; top: number }> = [];

    for (const node of order) {
        const row = rows[node.depth] ?? { height: 0, branches: 0, top: 0 };
        row.height = Math.max(row.height, node.size.height);
        row.branches = Math.max(row.branches, node.children.length);
        rows[node.depth] = row;

        const childrenWidth = node.children.reduce((width, child) => width + child.subtreeWidth, 0);
        let childLeft = node.left + (node.subtreeWidth - childrenWidth) / 2;

        for (const child of node.children) {
            child.left = childLeft;
            child.depth = node.depth + 1;
            childLeft += child.subtreeWidth;
        }
    }

    let top = 0;

    for (const row of rows) {
        row.top = top;
        top += row.height + workflowRowGap(row.branches);
    }

    const positions: Record<string, WorkflowPoint> = {};

    for (const node of order) {
        const subtreeCenter = node.left + node.subtreeWidth / 2;
        positions[node.id] = {
            x: subtreeCenter - forestWidth / 2 - node.size.width / 2,
            y: rows[node.depth].top,
        };
    }

    return positions;
}
