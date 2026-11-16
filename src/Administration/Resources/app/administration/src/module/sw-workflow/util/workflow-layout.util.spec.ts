/** @sw-package framework */
import { rankedWorkflowLayout } from './workflow-layout.util';
import { createStructuredWorkflowDraft } from './workflow-template.util';

describe('ranked workflow layout', () => {
    it('places connected nodes in execution order independently of array order', () => {
        const document = createStructuredWorkflowDraft();
        document.nodes.reverse();
        const positions = rankedWorkflowLayout(document, []);
        expect(positions.start.y).toBeLessThan(positions.end.y);
    });

    it('keeps disconnected and cyclic draft nodes visible and ignores dangling references', () => {
        const document = createStructuredWorkflowDraft();
        document.nodes.push(
            { id: 'first-cycle', type: 'extension:activity', typeVersion: 1, config: {} },
            { id: 'second-cycle', type: 'extension:activity', typeVersion: 1, config: {} },
        );
        document.connections.push(
            { id: 'cycle-forward', source: 'first-cycle', target: 'second-cycle', sourcePort: 'out', targetPort: 'in' },
            { id: 'cycle-back', source: 'second-cycle', target: 'first-cycle', sourcePort: 'out', targetPort: 'in' },
            { id: 'dangling', source: 'missing', target: 'end', sourcePort: 'out', targetPort: 'in' },
        );

        const positions = rankedWorkflowLayout(document, []);

        expect(Object.keys(positions).sort()).toEqual([
            'end',
            'first-cycle',
            'second-cycle',
            'start',
        ]);
        expect(positions['first-cycle'].y).toBe(0);
        expect(positions['second-cycle'].y).toBe(0);
        expect(positions.end.y).toBeGreaterThan(positions.start.y);
    });
});

describe('workflow extension scenarios', () => {
    it('centers mixed-width siblings and keeps enough room below the tallest node', () => {
        const document = createStructuredWorkflowDraft();
        document.nodes.push({ id: 'second-end', type: 'core:end', typeVersion: 1, config: {} });
        document.connections.push({
            id: 'second',
            source: 'start',
            sourcePort: 'other',
            target: 'second-end',
            targetPort: 'in',
        });

        const positions = rankedWorkflowLayout(document, [], {
            nodeSizes: { start: { width: 160, height: 90 }, end: { width: 400, height: 200 } },
        });

        expect(positions.start).toEqual({ x: -80, y: 0 });
        expect(positions.end).toEqual({ x: -360, y: 234 });
        expect(positions['second-end']).toEqual({ x: 152, y: 234 });
    });
});
