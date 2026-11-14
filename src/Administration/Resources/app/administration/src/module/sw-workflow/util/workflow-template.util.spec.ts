/** @sw-package framework */
import { createStructuredWorkflowDraft } from './workflow-template.util';

describe('structured workflow draft', () => {
    it('provides a connected start-to-end path and fresh node configuration for each draft', () => {
        const first = createStructuredWorkflowDraft();
        const second = createStructuredWorkflowDraft();
        const start = first.nodes.find((node) => node.type === 'core:start');
        const end = first.nodes.find((node) => node.type === 'core:end');

        expect(start).toBeDefined();
        expect(end).toBeDefined();
        expect(
            first.connections.filter((connection) => connection.source === start?.id).map((connection) => connection.target),
        ).toEqual([end?.id]);

        first.nodes[0].config = { label: 'Changed' };
        first.connections.splice(0);

        expect(second.nodes[0].config).toEqual({});
        expect(second.connections).toHaveLength(1);
    });
});
