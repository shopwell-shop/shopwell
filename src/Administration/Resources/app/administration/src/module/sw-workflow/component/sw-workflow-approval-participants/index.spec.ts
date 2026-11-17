import type { VueWrapper } from '@vue/test-utils';
/** @sw-package framework */
import { shallowMount } from '@vue/test-utils';

const tasks = [
    {
        id: 'one',
        nodeId: 'approval',
        nodeName: 'Review',
        round: 1,
        countersignType: 'countersign',
        state: 'todo',
        assignee: { id: 'a', name: 'Alice' },
    },
    {
        id: 'two',
        nodeId: 'approval',
        nodeName: 'Review',
        round: 1,
        countersignType: 'countersign',
        state: 'todo',
        assignee: { id: 'b', name: 'Bob' },
    },
];

async function createWrapper(approvalTasks = tasks): Promise<VueWrapper> {
    return shallowMount(await wrapTestComponent('sw-workflow-approval-participants', { sync: true }), {
        props: { tasks: approvalTasks },
    });
}

describe('approval participants', () => {
    it('identifies mandatory added reviewers separately from the original voting rule', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({ tasks: [tasks[0], { ...tasks[1], requiredReviewer: true }] });
        const participants = wrapper.findAll('.sw-workflow-approval-participants__participant');
        expect(participants[0].text()).not.toContain('sw-workflow.runtime.participants.requiredReviewer');
        expect(participants[1].text()).toContain('sw-workflow.runtime.participants.requiredReviewer');
        wrapper.unmount();
    });

    it('shows all participants and advances the decision count after one approval', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.findAll('.sw-workflow-approval-participants__participant')).toHaveLength(2);
        expect(wrapper.text()).toContain('Alice');
        expect(wrapper.text()).toContain('Bob');
        expect(wrapper.text()).toContain('sw-workflow.runtime.participants.rules.countersign');
        expect(wrapper.find('progress').attributes()).toMatchObject({ value: '0', max: '2' });
        await wrapper.setProps({ tasks: [{ ...tasks[0], state: 'agreed' }, tasks[1]] });
        expect(wrapper.find('progress').attributes()).toMatchObject({ value: '1', max: '2' });
        expect(wrapper.findAll('[data-state="agreed"]')).toHaveLength(1);
        expect(wrapper.findAll('[data-state="todo"]')).toHaveLength(1);
        wrapper.unmount();
    });

    it.each(['agreed', 'rejected'])('keeps canceled participants visible after the first %s decision', async (state) => {
        const wrapper = await createWrapper([
            { ...tasks[0], countersignType: 'firstDecision', state },
            { ...tasks[1], countersignType: 'firstDecision', state: 'canceled' },
        ]);
        expect(wrapper.text()).toContain('sw-workflow.runtime.participants.rules.firstDecision');
        expect(wrapper.findAll('[data-state="canceled"]')).toHaveLength(1);
        expect(wrapper.find('[data-state="canceled"]').text()).toContain('Bob');
        expect(wrapper.find('[data-state="canceled"]').text()).toContain('sw-workflow.runtime.participants.states.canceled');
        expect(wrapper.find('progress').attributes()).toMatchObject({ value: '2', max: '2' });
        wrapper.unmount();
    });
});

describe('sequential approval stages', () => {
    const stages = [
        {
            nodeId: 'all',
            nodeName: 'Team review',
            description: 'Check the request',
            countersignType: 'countersign',
            status: 'active',
            participants: tasks.map((task) => task.assignee),
            tasks,
        },
        {
            nodeId: 'any',
            nodeName: 'Finance review',
            description: '',
            countersignType: 'firstDecision',
            status: 'pending',
            participants: [{ id: 'c', name: 'Carol' }],
            tasks: [],
        },
        {
            nodeId: 'single',
            nodeName: 'Director approval',
            description: '',
            countersignType: 'single',
            status: 'pending',
            participants: [{ id: 'd', name: 'Diana' }],
            tasks: [],
        },
    ];

    it('shows ordered configured future stages without task progress or actions', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({ stages });
        expect(wrapper.findAll('h4').map((heading) => heading.text())).toEqual([
            'Team review',
            'Finance review',
            'Director approval',
        ]);
        expect(wrapper.find('[aria-current="step"]').text()).toContain('Team review');
        expect(wrapper.text()).toContain('Check the request');
        expect(wrapper.findAll('progress')).toHaveLength(1);
        expect(wrapper.findAll('[data-state="pending"]').map((row) => row.text().split('sw-workflow')[0].trim())).toEqual([
            'Carol',
            'Diana',
        ]);
        expect(wrapper.findAll('button')).toHaveLength(0);
    });

    it('advances all, first-decision and single stages without stale participants', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({ stages });
        const progressed = stages.map((stage, index) => ({
            ...stage,
            status: index === 0 ? 'approved' : index === 1 ? 'active' : 'pending',
            tasks:
                index === 0
                    ? tasks.map((task) => ({ ...task, state: 'agreed' }))
                    : index === 1
                      ? [{ ...tasks[0], id: 'finance', assignee: { id: 'c', name: 'Carol' } }]
                      : [],
        }));
        await wrapper.setProps({ stages: progressed });
        expect(wrapper.find('[aria-current="step"]').text()).toContain('Finance review');
        await wrapper.setProps({
            stages: progressed.map((stage, index) => ({ ...stage, status: index < 2 ? 'approved' : 'active' })),
        });
        expect(wrapper.find('[aria-current="step"]').text()).toContain('Director approval');
        await wrapper.setProps({ stages: [], tasks: [] });
        expect(wrapper.findAll('h4')).toHaveLength(0);
        expect(wrapper.text()).not.toContain('Carol');
    });

    it('distinguishes rejected and skipped stages from canceled actual tasks', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            stages: stages.map((stage, index) => ({
                ...stage,
                status: index === 0 ? 'rejected' : 'skipped',
                tasks:
                    index === 0
                        ? [
                              { ...tasks[0], state: 'rejected' },
                              { ...tasks[1], state: 'canceled' },
                          ]
                        : [],
            })),
        });
        expect(wrapper.findAll('[data-stage-status="skipped"]')).toHaveLength(2);
        expect(wrapper.findAll('[data-state="skipped"]')).toHaveLength(2);
        expect(wrapper.find('[data-state="canceled"]').text()).toContain('Bob');
        expect(wrapper.findAll('progress')).toHaveLength(1);
        expect(wrapper.find('[aria-current="step"]').exists()).toBe(false);
    });
});

describe('runtime interactions', () => {
    it('groups generic runtime task rounds independently without snapshot stages', async () => {
        const wrapper = await createWrapper([tasks[0], { ...tasks[1], round: 2, nodeName: 'Review again' }]);
        expect(wrapper.findAll('h4').map((heading) => heading.text())).toEqual(['Review', 'Review again']);
        expect(wrapper.findAll('progress')).toHaveLength(2);
        wrapper.unmount();
    });
});
