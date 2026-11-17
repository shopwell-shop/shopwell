/** @sw-package framework */
import type { RuntimeInstance } from 'src/core/workflow/workflow-runtime.types';
import { createWrapper, task, instance } from './create-wrapper';
describe('snapshot rendering', () => {
    it('passes configured stages and uses stage outcomes instead of partial approval history', async () => {
        const approvalStages = [
            {
                nodeId: 'review',
                nodeName: 'Review',
                description: '',
                countersignType: 'single',
                participants: [],
                tasks: [],
                status: 'skipped',
            },
            {
                nodeId: 'finance',
                nodeName: 'Review',
                description: '',
                countersignType: 'single',
                participants: [],
                tasks: [],
                status: 'rejected',
            },
        ];
        const wrapper = await createWrapper({
            ...instance,
            status: 'CANCEL',
            tasks: [],
            approvalTasks: [],
            approvalStages,
            history: [
                {
                    id: 'decision',
                    taskId: task.id,
                    round: 1,
                    nodeId: 'review',
                    nodeName: task.nodeName,
                    assignee: task.assignee,
                    countersignType: task.countersignType,
                    decision: 'agree',
                    comment: null,
                    completedAt: null,
                },
            ],
        });
        expect(wrapper.findComponent({ name: 'sw-workflow-runtime-viewer' }).props('stages')).toEqual(approvalStages);
        expect(wrapper.findComponent({ name: 'sw-workflow-runtime-viewer' }).props('nodeStates')).toEqual({
            review: 'SKIPPED',
            finance: 'REJECTED',
        });
        expect(wrapper.findComponent({ name: 'sw-workflow-approval-actions' }).exists()).toBe(false);
        wrapper.unmount();
    });

    it('keeps the active stage overview visible for a participant without an actionable task', async () => {
        const approvalStages = [
            {
                nodeId: 'review',
                nodeName: 'Review',
                description: '',
                countersignType: 'single',
                participants: [],
                tasks: [],
                status: 'active',
            },
            {
                nodeId: 'finance',
                nodeName: 'Review',
                description: '',
                countersignType: 'single',
                participants: [],
                tasks: [],
                status: 'pending',
            },
        ];
        const wrapper = await createWrapper({
            ...instance,
            tasks: [],
            approvalTasks: [],
            approvalStages,
        });
        expect(wrapper.findComponent({ name: 'sw-workflow-runtime-viewer' }).props('stages')).toEqual(approvalStages);
        expect(wrapper.findComponent({ name: 'sw-workflow-runtime-viewer' }).props('nodeStates')).toEqual({
            review: 'TODO',
        });
        expect(wrapper.findComponent({ name: 'sw-workflow-approval-actions' }).exists()).toBe(false);
        wrapper.unmount();
    });

    it('highlights only visited nodes while preserving pending and skipped stages on the selected route', async () => {
        const selectedPath = {
            nodeIds: [
                'start',
                'department',
                'condition',
                'finance',
            ],
            edgeIds: ['first', 'choice'],
        };
        const plannedPath = {
            nodeIds: [...selectedPath.nodeIds, 'end'],
            edgeIds: [...selectedPath.edgeIds, 'finish'],
        };
        const wrapper = await createWrapper({
            ...instance,
            selectedPath,
            plannedPath,
            graph: {
                nodes: [
                    'start',
                    'department',
                    'condition',
                    'finance',
                    'alternative',
                    'end',
                ].map((id) => ({ id, type: 'snaker:task', properties: {} })),
                edges: [],
            },
            approvalStages: [
                {
                    nodeId: 'department',
                    nodeName: 'Review',
                    description: '',
                    countersignType: 'single',
                    participants: [],
                    tasks: [],
                    status: 'approved',
                },
                {
                    nodeId: 'finance',
                    nodeName: 'Review',
                    description: '',
                    countersignType: 'single',
                    participants: [],
                    tasks: [],
                    status: 'active',
                },
                {
                    nodeId: 'alternative',
                    nodeName: 'Review',
                    description: '',
                    countersignType: 'single',
                    participants: [],
                    tasks: [],
                    status: 'skipped',
                },
            ],
        });
        expect(wrapper.findComponent({ name: 'sw-workflow-runtime-viewer' }).props('nodeStates')).toEqual({
            start: 'VISITED',
            department: 'AGREED',
            condition: 'VISITED',
            finance: 'TODO',
            alternative: 'SKIPPED',
        });
        expect(wrapper.findComponent({ name: 'sw-workflow-runtime-viewer' }).props('selectedPath')).toBeDefined();
        wrapper.unmount();
    });

    it('keeps editable values visible for a returned initiator without an active task', async () => {
        const wrapper = await createWrapper({
            ...instance,
            status: 'RETURNED',
            tasks: [],
            approvalTasks: [],
            formPermissions: { reason: { access: 'editable', required: false } },
        });

        expect(wrapper.text()).toContain('Equipment');
        expect(wrapper.findComponent({ name: 'sw-workflow-approval-form' }).exists()).toBe(false);
        wrapper.unmount();
    });

    it('displays select labels and formatted amounts from the authorized snapshot', async () => {
        const wrapper = await createWrapper({
            ...instance,
            formData: { category: 'travel', amount: '999999999999.99' },
            formSchema: [
                {
                    key: 'category',
                    label: 'Category',
                    type: 'select',
                    required: false,
                    options: [{ value: 'travel', label: 'Travel expenses' }],
                },
                { key: 'amount', label: 'Amount', type: 'amount', required: false },
            ],
        });
        expect(wrapper.text()).toContain('Travel expenses');
        expect(wrapper.text()).toContain('999,999,999,999.99');
        wrapper.unmount();
    });

    it.each([null, { nodeIds: [], edgeIds: [] }])(
        'leaves generic runtime graph undecorated for planned path %s',
        async (plannedPath) => {
            const snapshot = {
                ...instance,
                currentNodeId: null,
                selectedPath: { nodeIds: [], edgeIds: [] },
                plannedPath,
                graph: { nodes: [{ id: 'legacy', type: 'snaker:task', properties: {} }], edges: [] },
            } satisfies RuntimeInstance;
            const wrapper = await createWrapper(snapshot);
            expect(wrapper.findComponent({ name: 'sw-workflow-runtime-viewer' }).props('plannedPath')).toBeNull();
            expect(wrapper.findComponent({ name: 'sw-workflow-runtime-viewer' }).props('nodeStates')).toEqual({});
            wrapper.unmount();
        },
    );

    it.each(['REJECT', 'CANCEL'])('does not highlight future path after %s', async (status) => {
        const selectedPath = { nodeIds: ['start', 'review'], edgeIds: ['first'] };
        const wrapper = await createWrapper({
            ...instance,
            status,
            selectedPath,
            plannedPath: {
                nodeIds: [
                    'start',
                    'review',
                    'condition',
                    'finance',
                    'end',
                ],
                edgeIds: ['first', 'next'],
            },
            graph: {
                nodes: [
                    'start',
                    'review',
                    'condition',
                    'finance',
                    'end',
                ].map((id) => ({ id, type: 'snaker:task', properties: {} })),
                edges: [],
            },
            approvalStages: [
                {
                    nodeId: 'review',
                    nodeName: 'Review',
                    description: '',
                    countersignType: 'single',
                    participants: [],
                    tasks: [],
                    status: status === 'REJECT' ? 'rejected' : 'skipped',
                },
                {
                    nodeId: 'finance',
                    nodeName: 'Review',
                    description: '',
                    countersignType: 'single',
                    participants: [],
                    tasks: [],
                    status: 'skipped',
                },
            ],
        });
        expect(wrapper.findComponent({ name: 'sw-workflow-runtime-viewer' }).props('plannedPath')).toEqual(selectedPath);
        expect(wrapper.findComponent({ name: 'sw-workflow-runtime-viewer' }).props('nodeStates')).toMatchObject({
            condition: 'SKIPPED',
            finance: 'SKIPPED',
            end: 'SKIPPED',
        });
        wrapper.unmount();
    });
});
