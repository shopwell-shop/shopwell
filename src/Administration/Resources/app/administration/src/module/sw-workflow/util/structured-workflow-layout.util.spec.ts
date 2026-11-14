/** @sw-package framework */
import '../service';
import { structuredWorkflowLayout } from './structured-workflow-layout.util';
import type { WorkflowDocument } from 'src/core/workflow/workflow.types';

describe('structured workflow layout', () => {
    it('keeps branch lanes in declared port order after connections are appended or reordered', () => {
        const document: WorkflowDocument = {
            schemaVersion: 1,
            policy: 'core:structured',
            nodes: ['review', 'approved', 'rejected'].map((id) => ({
                id,
                type: 'example:review',
                typeVersion: 1,
                config: {},
            })),
            connections: [
                { id: 'rejected', source: 'review', sourcePort: 'rejected', target: 'rejected', targetPort: 'in' },
                { id: 'approved', source: 'review', sourcePort: 'approved', target: 'approved', targetPort: 'in' },
            ],
        };
        const types = [
            {
                name: 'example:review',
                version: 1,
                kind: 'activity' as const,
                inputPorts: ['in'],
                outputPorts: ['approved', 'rejected'],
            },
        ];
        const result = structuredWorkflowLayout(document, types);
        expect(result.approved.x).toBeLessThan(result.rejected.x);
        document.connections.reverse();
        expect(structuredWorkflowLayout(document, types)).toEqual(result);
    });

    it('reserves measured extension node sizes and a routing corridor for every row', () => {
        const document: WorkflowDocument = {
            schemaVersion: 1,
            policy: 'core:structured',
            nodes: ['source', 'left', 'right'].map((id) => ({ id, type: 'extension:card', typeVersion: 1, config: {} })),
            connections: ['left', 'right'].map((target) => ({
                id: target,
                source: 'source',
                sourcePort: target,
                target,
                targetPort: 'in',
            })),
        };
        const sizes = {
            source: { width: 700, height: 240 },
            left: { width: 600, height: 120 },
            right: { width: 600, height: 120 },
        };
        const result = structuredWorkflowLayout(document, [], {
            nodeSizes: sizes,
            outputPorts: { source: ['left', 'right'] },
        });
        expect(result.right.x - result.left.x).toBeGreaterThan(sizes.left.width);
        expect(result.left.y - result.source.y - sizes.source.height).toBeGreaterThanOrEqual(144);
        expect(result.source.x + sizes.source.width / 2).toBeCloseTo(0);
    });

    it('keeps each branch subtree in a lane and respects edge priority rather than node creation order', () => {
        const document: WorkflowDocument = {
            schemaVersion: 1,
            policy: 'core:structured',
            nodes: [
                'start',
                'right',
                'left',
                'left-end',
                'right-end',
            ].map((id) => ({ id, type: 'example:node', typeVersion: 1, config: {} })),
            connections: [
                ['start', 'left'],
                ['start', 'right'],
                ['left', 'left-end'],
                ['right', 'right-end'],
            ].map(([source, target]) => ({ id: target, source, target, sourcePort: target, targetPort: 'in' })),
        };
        const layout = Shopwell.Service('workflowLayoutService').forPolicy(document.policy);
        const positions = layout(document, []);
        expect(positions.left.x).toBeLessThan(positions.right.x);
        expect(positions['left-end'].x).toBe(positions.left.x);
        expect(positions['right-end'].x).toBe(positions.right.x);
        document.connections.reverse();
        expect(layout(document, []).left.x).toBeGreaterThan(layout(document, []).right.x);
    });
});
