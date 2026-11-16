/** @sw-package framework */
import { createStructuredWorkflowDraft as createWorkflowDraft } from 'src/module/sw-workflow/util/workflow-template.util';
import type { WorkflowNode, WorkflowNodeTypeSpecification } from 'src/core/workflow/workflow.types';
import { toCanvasGraph } from './workflow-canvas.adapter';

describe('workflow canvas adapter', () => {
    it('resolves each node once and projects sibling lanes in declared port order', () => {
        const document = createWorkflowDraft();
        document.connections = ['right', 'left'].map((port) => ({
            id: port,
            source: 'start',
            sourcePort: port,
            target: 'end',
            targetPort: 'in',
        }));
        const types = [
            { name: 'core:start', version: 1, kind: 'start' as const, inputPorts: [], outputPorts: ['left', 'right'] },
            { name: 'core:end', version: 1, kind: 'end' as const, inputPorts: ['in'], outputPorts: [] },
        ];
        const resolvePorts = jest.fn((node: WorkflowNode, type: WorkflowNodeTypeSpecification) => ({
            ...type,
            labels: { left: 'Left', right: 'Right' },
        }));

        const graph = toCanvasGraph(document, types, {}, { resolvePorts });

        expect(resolvePorts).toHaveBeenCalledTimes(2);
        expect(graph.edges.map((edge) => [edge.label, edge.data?.branch])).toEqual([
            ['Right', { index: 1, count: 2 }],
            ['Left', { index: 0, count: 2 }],
        ]);
    });

    it('projects ports and labels without adding canvas state to the document', () => {
        const document = createWorkflowDraft();
        const original = JSON.stringify(document);
        const graph = toCanvasGraph(document, [], { 'core:start': 'Start' });
        expect(graph.nodes[0].data?.label).toBe('Start');
        expect(graph.nodes[0].data?.invalid).toBe(true);
        expect(graph.nodes[1].position.y).toBeGreaterThan(graph.nodes[0].position.y);
        expect(graph.edges[0]).toEqual(expect.objectContaining({ sourceHandle: 'out', targetHandle: 'in' }));
        expect(JSON.stringify(document)).toBe(original);
        expect(graph.nodes[0].data?.node).toEqual(document.nodes[0]);
        expect(graph.nodes[0].data?.node).not.toBe(document.nodes[0]);
    });

    it('can render a cyclic draft so diagnostics remain accessible', () => {
        const document = createWorkflowDraft();
        document.connections.push({ id: 'cycle', source: 'end', sourcePort: 'out', target: 'start', targetPort: 'in' });
        const graph = toCanvasGraph(document, [], {}, { invalidIds: ['start'] });
        expect(graph.nodes).toHaveLength(2);
        expect(new Set(graph.nodes.map((node) => node.position.x)).size).toBe(2);
    });
});
