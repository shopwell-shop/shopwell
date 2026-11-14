/** @sw-package framework */
import type { WorkflowDocument, WorkflowNodeTypeSpecification } from 'src/core/workflow/workflow.types';
import type { WorkflowLayoutContext } from './workflow-layout-geometry.util';
import { workflowNodeSize, workflowRowGap } from './workflow-layout-geometry.util';
import { rankedWorkflowLayout } from './workflow-layout.util';

/** Reserves each branch subtree and routes it in the same order as its renderer's output ports. @private */
export function structuredWorkflowLayout(
    document: WorkflowDocument,
    types: WorkflowNodeTypeSpecification[],
    context: WorkflowLayoutContext = {},
): Record<string, { x: number; y: number }> {
    const nodes = new Map(document.nodes.map((node) => [node.id, node]));
    const incoming = new Map(document.nodes.map((node) => [node.id, 0]));
    const children = new Map<string, string[]>();
    for (const node of document.nodes) {
        const type = types.find((item) => item.name === node.type && item.version === node.typeVersion);
        const ports = context.outputPorts?.[node.id] ?? type?.outputPorts ?? [];
        const edges = document.connections.filter((edge) => edge.source === node.id && nodes.has(edge.target));
        edges.sort((a, b) => {
            const index = (port: string) => {
                const i = ports.indexOf(port);
                return i < 0 ? ports.length : i;
            };
            return index(a.sourcePort) - index(b.sourcePort);
        });
        children.set(
            node.id,
            edges.map((edge) => edge.target),
        );
        edges.forEach((edge) => incoming.set(edge.target, (incoming.get(edge.target) ?? 0) + 1));
    }
    if ([...incoming.values()].some((count) => count > 1)) return rankedWorkflowLayout(document, types, context);
    const roots = [...incoming].filter(([, count]) => count === 0).map(([id]) => id);
    const order = [...roots];
    for (let i = 0; i < order.length; i += 1) order.push(...(children.get(order[i]) ?? []));
    if (order.length !== document.nodes.length) return rankedWorkflowLayout(document, types, context);
    const sizes = new Map(document.nodes.map((node) => [node.id, workflowNodeSize(node, types, context)]));
    const widths = new Map<string, number>();
    [...order].reverse().forEach((id) =>
        widths.set(
            id,
            Math.max(
                320,
                (sizes.get(id)?.width ?? 0) + 64,
                (children.get(id) ?? []).reduce((sum, child) => sum + (widths.get(child) ?? 0), 0),
            ),
        ),
    );
    const offsets = new Map<string, number>();
    const depths = new Map<string, number>();
    let width = 0;
    roots.forEach((id) => {
        offsets.set(id, width);
        depths.set(id, 0);
        width += widths.get(id) ?? 0;
    });
    const heights: number[] = [];
    const branches: number[] = [];
    order.forEach((id) => {
        const depth = depths.get(id) ?? 0;
        heights[depth] = Math.max(heights[depth] ?? 0, sizes.get(id)?.height ?? 0);
        branches[depth] = Math.max(branches[depth] ?? 0, children.get(id)?.length ?? 0);
        const childWidths = (children.get(id) ?? []).reduce((sum, child) => sum + (widths.get(child) ?? 0), 0);
        let offset = (offsets.get(id) ?? 0) + ((widths.get(id) ?? 0) - childWidths) / 2;
        (children.get(id) ?? []).forEach((child) => {
            offsets.set(child, offset);
            depths.set(child, depth + 1);
            offset += widths.get(child) ?? 0;
        });
    });
    const rows = [0];
    heights.forEach((height, depth) => {
        rows[depth + 1] = rows[depth] + height + workflowRowGap(branches[depth]);
    });
    return Object.fromEntries(
        order.map((id) => [
            id,
            {
                x: (offsets.get(id) ?? 0) + (widths.get(id) ?? 0) / 2 - width / 2 - (sizes.get(id)?.width ?? 0) / 2,
                y: rows[depths.get(id) ?? 0],
            },
        ]),
    );
}
