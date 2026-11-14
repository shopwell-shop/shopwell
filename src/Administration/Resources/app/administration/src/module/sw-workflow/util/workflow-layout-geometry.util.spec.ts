/** @sw-package framework */
import { workflowNodeSize, workflowRowGap } from './workflow-layout-geometry.util';
import { routeWorkflowConnection } from './workflow-connection-route.util';

describe('workflow layout geometry', () => {
    it.each([
        1,
        2,
        3,
        8,
        20,
    ])('reserves a corridor for labels and controls on every one of %s branches', (count) => {
        const targetY = workflowRowGap(count);
        for (let index = 0; index < count; index += 1) {
            const route = routeWorkflowConnection({ x: index * 20, y: 0 }, { x: -640, y: targetY }, { index, count });
            expect(route.label.y - 12).toBeGreaterThan(0);
            expect(route.label.y + 12).toBeLessThan(route.insert.y - 11);
            expect(route.insert.y + 11).toBeLessThan(targetY);
        }
    });

    it('uses measured extension dimensions while a new renderer is still unmeasured', () => {
        const node = { id: 'extension', type: 'example:node', typeVersion: 1, config: {} };
        const measured = { width: 700, height: 240 };
        expect(workflowNodeSize(node, [], { nodeSizes: { extension: measured } })).toEqual(measured);
        expect(workflowNodeSize(node, [], { nodeSizes: { extension: { width: 0, height: 0 } } }).width).toBeGreaterThan(0);
    });
});
