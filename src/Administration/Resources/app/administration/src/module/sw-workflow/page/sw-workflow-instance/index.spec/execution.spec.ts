/** @sw-package framework */
import type { RuntimeNodeExecution } from 'src/core/workflow/workflow-runtime.types';
import { createWrapper, instance } from './create-wrapper';

function execution(
    id: string,
    nodeId: string,
    sequence: number,
    status: RuntimeNodeExecution['status'],
): RuntimeNodeExecution {
    return {
        id,
        nodeId,
        sequence,
        status,
        round: 1,
        sourceConnectionId: null,
        waitingFor: status === 'waiting' ? 'example:callback' : null,
        result: null,
        output: {},
        startedAt: '2026-11-14T00:00:00Z',
        endedAt: status === 'completed' ? '2026-11-14T00:00:01Z' : null,
    };
}

describe('generic workflow execution details', () => {
    it('shows completion independently of approval decisions', async () => {
        const wrapper = await createWrapper({
            ...instance,
            status: 'COMPLETED',
            tasks: [],
            approvalTasks: [],
        });

        expect(wrapper.get('.sw-workflow-instance__status').attributes('variant')).toBe('positive');

        wrapper.unmount();
    });

    it('colors automatic and waiting nodes using their persisted executions', async () => {
        const value = {
            ...instance,
            currentNodeId: 'callback',
            tasks: [],
            approvalTasks: [],
            executions: [
                execution('first', 'start', 1, 'completed'),
                execution('second', 'action', 2, 'completed'),
                execution('third', 'callback', 3, 'waiting'),
            ],
        };
        const wrapper = await createWrapper(value);
        const graph = wrapper.findComponent({ name: 'sw-workflow-runtime-viewer' });

        expect(graph.props('nodeStates')).toEqual({ start: 'COMPLETED', action: 'COMPLETED', callback: 'TODO' });

        wrapper.unmount();
    });
});
