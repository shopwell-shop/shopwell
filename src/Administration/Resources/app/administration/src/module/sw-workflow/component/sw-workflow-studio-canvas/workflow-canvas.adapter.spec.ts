/** @sw-package framework */
import { createStructuredWorkflowDraft as createWorkflowDraft } from 'src/module/sw-workflow/util/workflow-template.util';
import { toCanvasGraph } from './workflow-canvas.adapter';

describe('workflow canvas adapter', () => {
    it('projects ports and labels without adding canvas state to the document', () => {
        const document = createWorkflowDraft();
        const original = JSON.stringify(document);
        const graph = toCanvasGraph(document, [], { 'core:start': 'Start' });
        expect(graph.nodes[0].data?.label).toBe('Start');
        expect(graph.nodes[0].data?.invalid).toBe(true);
        expect(graph.nodes[1].position.y).toBeGreaterThan(graph.nodes[0].position.y);
        expect(graph.connections[0]).toEqual(expect.objectContaining({ sourcePort: 'out', targetPort: 'in' }));
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
