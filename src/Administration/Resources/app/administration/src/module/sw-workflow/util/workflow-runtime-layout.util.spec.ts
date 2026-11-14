/** @sw-package framework */
import { layout } from './workflow-runtime-layout.util';

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
        const result = layout(graph);
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
        const result = layout(graph, { nodeSizes: { review: { width: 500, height: 280 } } });
        const review = result.nodes[1];
        const end = result.nodes[2];
        expect((end.y ?? 0) - (review.y ?? 0)).toBeGreaterThanOrEqual(280 + 144);
        expect((review.x ?? 0) + 250).toBeCloseTo((end.x ?? 0) + 80);
        expect(graph.nodes[1]).not.toHaveProperty('x');
    });
});
