/** @sw-package framework */
import { layoutRuntimeGraph } from './runtime-graph-layout';

describe('runtime graph layout', () => {
    it('separates branches and disconnected nodes without changing the snapshot', () => {
        const graph = {
            nodes: [
                'start',
                'left',
                'right',
                'end',
                'isolated',
            ].map((id) => ({ id, type: `snaker:${id}` })),
            edges: [
                ['start', 'left'],
                ['start', 'right'],
                ['left', 'end'],
                ['right', 'end'],
            ].map(([sourceNodeId, targetNodeId], i) => ({ id: String(i), sourceNodeId, targetNodeId })),
        };
        const result = layoutRuntimeGraph(graph);
        expect(new Set(result.nodes.map((node) => `${node.x}:${node.y}`)).size).toBe(5);
        expect(result.edges).toEqual(graph.edges);
        expect(graph.nodes[0]).not.toHaveProperty('x');
    });

    it('uses the common layout corridor and measured card sizes for the read-only diagram', () => {
        const graph = {
            nodes: ['start', 'review', 'end'].map((id) => ({ id, type: `snaker:${id === 'review' ? 'task' : id}` })),
            edges: [
                { id: 'first', sourceNodeId: 'start', targetNodeId: 'review' },
                { id: 'last', sourceNodeId: 'review', targetNodeId: 'end' },
            ],
        };
        const result = layoutRuntimeGraph(graph, { nodeSizes: { review: { width: 500, height: 280 } } });
        const review = result.nodes[1];
        const end = result.nodes[2];
        expect((end.y ?? 0) - (review.y ?? 0)).toBeGreaterThanOrEqual(280 + 144);
        expect((review.x ?? 0) + 250).toBeCloseTo((end.x ?? 0) + 80);
        expect(graph.nodes[1]).not.toHaveProperty('x');
    });
    it('lays out an unknown extension node as an activity while preserving its protocol name', () => {
        const graph = {
            nodes: [
                { id: 'start', type: 'snaker:start' },
                { id: 'custom', type: 'plugin:review' },
                { id: 'end', type: 'snaker:end' },
            ],
            edges: [
                { id: 'first', sourceNodeId: 'start', targetNodeId: 'custom' },
                { id: 'last', sourceNodeId: 'custom', targetNodeId: 'end' },
            ],
        };
        const result = layoutRuntimeGraph(graph);
        expect(result.nodes[1].type).toBe('plugin:review');
        expect((result.nodes[0].x ?? 0) + 80).toBeCloseTo((result.nodes[1].x ?? 0) + 126);
        expect((result.nodes[1].x ?? 0) + 126).toBeCloseTo((result.nodes[2].x ?? 0) + 80);
        expect((result.nodes[2].y ?? 0) - (result.nodes[1].y ?? 0)).toBeGreaterThanOrEqual(106 + 144);
    });
});
