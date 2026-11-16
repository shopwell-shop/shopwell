/** @sw-package framework */
import { routeWorkflowConnection } from './workflow-connection-route.util';
import type { WorkflowPoint } from 'src/core/workflow/workflow-studio.types';

function horizontalSegments(points: WorkflowPoint[]) {
    return points
        .slice(1)
        .flatMap((point, index) =>
            point.y === points[index].y
                ? [{ y: point.y, left: Math.min(point.x, points[index].x), right: Math.max(point.x, points[index].x) }]
                : [],
        );
}

describe('workflow connection routing', () => {
    it.each([1, -1])(
        'separates same-direction sibling branches without overlapping horizontal segments (%s)',
        (direction) => {
            const routes = [0, 1, 2].map((index) =>
                routeWorkflowConnection(
                    { x: index * 40, y: 100 },
                    { x: direction * 640 + index * 320, y: 400 },
                    { index, count: 3 },
                ),
            );
            const segments = routes.flatMap((route) => horizontalSegments(route.points));
            segments.forEach((segment, index) => {
                segments.slice(index + 1).forEach((other) => {
                    expect(
                        segment.y === other.y && Math.max(segment.left, other.left) < Math.min(segment.right, other.right),
                    ).toBe(false);
                });
            });
            routes.forEach((route) => {
                expect(route.label.y).toBeGreaterThan(100 + 16);
                expect(route.label.y + 12).toBeLessThan(route.insert.y - 11);
                expect(route.insert.y + 11).toBeLessThan(400);
                expect(route.path).not.toMatch(/NaN|Infinity/);
            });
        },
    );

    it('keeps a straight edge and its controls between the two node boundaries', () => {
        const route = routeWorkflowConnection({ x: 50, y: 100 }, { x: 50, y: 244 });
        expect(route.points).toEqual([
            { x: 50, y: 100 },
            { x: 50, y: 244 },
        ]);
        expect(route.label.y).toBeGreaterThan(100);
        expect(route.insert.y).toBeLessThan(244);
    });

    it('keeps invalid or temporarily unmeasured edges renderable for diagnostics', () => {
        for (const y of [
            0,
            20,
            80,
            144,
        ]) {
            const route = routeWorkflowConnection({ x: 0, y: 0 }, { x: 100, y });
            expect(route.path).not.toMatch(/NaN|Infinity/);
        }
    });
});
