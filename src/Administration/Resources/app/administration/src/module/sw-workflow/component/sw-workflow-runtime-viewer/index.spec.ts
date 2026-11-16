/** @sw-package framework */
import { mount, shallowMount } from '@vue/test-utils';

jest.mock('@vue-flow/core', () => ({
    VueFlow: { template: '<div />' },
    Handle: { template: '<div />' },
    BaseEdge: { props: ['path'], template: '<path :d="path" />' },
    EdgeLabelRenderer: { template: '<div><slot /></div>' },
    Position: { Top: 'top', Bottom: 'bottom' },
    MarkerType: { ArrowClosed: 'arrowclosed' },
    useVueFlow: () => ({ fitView: jest.fn() }),
}));

describe('workflow read-only graph', () => {
    beforeEach(() => {
        global.ResizeObserver = jest.fn().mockImplementation(() => ({ observe: jest.fn(), disconnect: jest.fn() }));
    });
    it('shows a connected flowchart with approval participants and their actual task states inside nodes', async () => {
        const wrapper = mount(await wrapTestComponent('sw-workflow-runtime-viewer', { sync: true }), {
            props: {
                graph: {
                    nodes: [
                        { id: 'start', type: 'snaker:start' },
                        { id: 'review', type: 'snaker:task', properties: { name: 'Finance review' } },
                        { id: 'end', type: 'snaker:end' },
                    ],
                    edges: [
                        { id: 'first', sourceNodeId: 'start', targetNodeId: 'review' },
                        { id: 'last', sourceNodeId: 'review', targetNodeId: 'end' },
                    ],
                },
                nodeStates: { review: 'TODO' },
                stages: [
                    {
                        nodeId: 'review',
                        nodeName: 'Finance review',
                        description: '',
                        countersignType: 'countersign',
                        status: 'active',
                        participants: [
                            { id: 'alice', name: 'Alice' },
                            { id: 'bob', name: 'Bob' },
                        ],
                        tasks: [
                            {
                                id: 'one',
                                nodeId: 'review',
                                nodeName: 'Finance review',
                                round: 1,
                                countersignType: 'countersign',
                                state: 'agreed',
                                assignee: { id: 'alice', name: 'Alice' },
                            },
                        ],
                    },
                ],
            },
            global: {
                stubs: {
                    'vue-flow': {
                        props: ['nodes', 'edges'],
                        template:
                            '<div><div v-for="node in nodes" :key="node.id"><slot name="node-runtime" :data="node.data" /></div><slot v-for="edge in edges" :key="edge.id" name="edge-runtime" v-bind="edge" :source-x="0" :source-y="100" :target-x="100" :target-y="300" /></div>',
                    },
                    'mt-icon': true,
                    'mt-button': { template: '<button><slot /></button>' },
                },
            },
        });
        expect(wrapper.findAll('.sw-workflow-runtime-viewer__node')).toHaveLength(3);
        expect(wrapper.findAll('path')).toHaveLength(2);
        expect(wrapper.get('.is--todo').text()).toContain('Finance review');
        expect(wrapper.get('[data-participant-state="agreed"]').text()).toContain('Alice');
        expect(wrapper.get('[data-participant-state="active"]').text()).toContain('Bob');
        expect(wrapper.text()).not.toContain('snaker:start');
        expect(wrapper.text()).not.toContain('snaker:end');
        expect(wrapper.text()).not.toContain('secret condition');
        wrapper.unmount();
    });

    it('distinguishes visited, planned and skipped edges without exposing condition expressions', async () => {
        global.ResizeObserver = jest.fn().mockImplementation(() => ({ observe: jest.fn(), disconnect: jest.fn() }));
        const wrapper = shallowMount(await wrapTestComponent('sw-workflow-runtime-viewer', { sync: true }), {
            props: {
                graph: {
                    nodes: [],
                    edges: ['visited', 'next', 'other'].map((id) => ({
                        id,
                        sourceNodeId: 'condition',
                        targetNodeId: id,
                        properties: { name: id, expr: 'secret condition' },
                    })),
                },
                selectedPath: { nodeIds: [], edgeIds: ['visited'] },
                plannedPath: { nodeIds: [], edgeIds: ['visited', 'next'] },
            },
            global: {
                stubs: {
                    'vue-flow': {
                        props: ['edges', 'nodesDraggable'],
                        template:
                            '<div :data-editable="nodesDraggable"><span v-for="edge in edges" :key="edge.id" :class="edge.class">{{ edge.label }}</span></div>',
                    },
                },
            },
        });
        expect(wrapper.get('[data-editable] .is--visited').text()).toBe('visited');
        expect(wrapper.get('[data-editable] .is--planned').text()).toBe('next');
        expect(wrapper.get('[data-editable] .is--skipped').text()).toBe('other');
        expect(wrapper.text()).not.toContain('secret condition');
        expect(wrapper.get('[data-editable]').attributes('data-editable')).toBe('false');
        wrapper.unmount();
    });
    it('keeps unknown extension nodes visible with their type name and generic icon', async () => {
        const wrapper = shallowMount(await wrapTestComponent('sw-workflow-runtime-viewer', { sync: true }), {
            props: { graph: { nodes: [{ id: 'custom', type: 'plugin:review' }], edges: [] } },
            global: {
                stubs: {
                    'vue-flow': {
                        props: ['nodes'],
                        template:
                            '<section><div v-for="node in nodes" :key="node.id" :data-icon="node.data.icon" :data-terminal="node.data.terminal">{{ node.data.label }}</div></section>',
                    },
                },
            },
        });
        expect(wrapper.get('[data-icon]').attributes('data-icon')).toBe('regular-cog');
        expect(wrapper.get('[data-terminal]').attributes('data-terminal')).toBe('false');
        expect(wrapper.text()).toContain('plugin:review');
        wrapper.unmount();
    });
});
