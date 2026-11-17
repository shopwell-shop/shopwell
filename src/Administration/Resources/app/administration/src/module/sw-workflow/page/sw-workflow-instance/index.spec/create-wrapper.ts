import type { RuntimeInstance } from 'src/core/workflow/workflow-runtime.types';
/** @sw-package framework */
import { shallowMount, flushPromises } from '@vue/test-utils';
import { reactive } from 'vue';

const repository = {
    getInstance: jest.fn(),
    approveTask: jest.fn(),
    rejectTask: jest.fn(),
    cancelInstance: jest.fn(),
    remindInstance: jest.fn(),
};

/** @private */
export const task = {
    id: 'task',
    nodeId: 'review',
    round: 1,
    actionable: true,
    state: 'todo',
    assignee: { id: 'approver' },
    nodeName: 'Review',
    countersignType: 'single',
};
/** @private */
export const instance: RuntimeInstance = {
    id: 'instance',
    title: 'Request',
    status: 'RUNNING',
    round: 1,
    graph: { nodes: [], edges: [] },
    formData: { reason: 'Equipment' },
    formSchema: [{ key: 'reason', label: 'Request reason', type: 'text', required: false }],
    tasks: [task],
    approvalTasks: [task],
    history: [],
    approvalStages: [],
};
/** @private */
export async function createWrapper(
    value: typeof instance | Promise<typeof instance> = instance,
    route = reactive({
        name: 'sw.workflow.instance',
        params: { id: 'instance' },
        query: {},
    }),
) {
    jest.mocked(repository.getInstance).mockResolvedValue(value);
    Shopwell.Store.get('session').currentUser = {
        id: 'approver',
    } as Entity<'user'>;
    const wrapper = shallowMount(await wrapTestComponent('sw-workflow-instance', { sync: true }), {
        global: {
            provide: { workflowRuntimeApiService: repository, acl: { can: () => true } },
            mocks: { $route: route, $router: { push: jest.fn() } },
            stubs: {
                'sw-page': {
                    template:
                        '<div><slot name="smart-bar-header"/><slot name="smart-bar-actions"/><slot name="content"/></div>',
                },
                'mt-button': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
                'mt-textarea': {
                    props: ['modelValue'],
                    template: '<textarea :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
                },
                'sw-modal': { template: '<section class="cancel-modal"><slot/><slot name="modal-footer" /></section>' },
                'mt-loader': { template: '<div class="approval-loading" />' },
                'sw-workflow-runtime-viewer': {
                    name: 'sw-workflow-runtime-viewer',
                    props: [
                        'nodeStates',
                        'plannedPath',
                        'selectedPath',
                        'stages',
                        'tasks',
                    ],
                    template: '<div />',
                },
                'sw-workflow-approval-actions': {
                    name: 'sw-workflow-approval-actions',
                    template:
                        '<section class="approval-actions"><button @click="$emit(\'approve\', \'\')">Approve</button></section>',
                },
                'sw-workflow-approval-form': {
                    name: 'sw-workflow-approval-form',
                    props: ['instance', 'disabled'],
                    emits: ['update-patch', 'validity-change'],
                    template: '<div class="approval-form-editor" />',
                },
                'sw-workflow-approval-timeline': true,
                'sw-workflow-approval-participants': {
                    name: 'sw-workflow-approval-participants',
                    props: ['tasks', 'stages'],
                    template: '<div />',
                },
            },
        },
    });
    await flushPromises();
    return wrapper;
}

/** @private */
export { repository };
