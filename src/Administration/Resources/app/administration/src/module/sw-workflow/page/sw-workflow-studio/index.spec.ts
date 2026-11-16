import '../../service';
/** @sw-package framework */
import { shallowMount, flushPromises } from '@vue/test-utils';
import type { WorkflowViolation } from 'src/core/workflow/workflow.types';

const nodeTypes = [
    { name: 'core:approval', version: 1, kind: 'activity', inputPorts: ['in'], outputPorts: ['approved', 'rejected'] },
];
async function createWrapper(service: {
    getNodeTypes: () => Promise<unknown>;
    diagnose: () => Promise<WorkflowViolation[]>;
}) {
    return shallowMount(await wrapTestComponent('sw-workflow-studio', { sync: true }), {
        global: {
            provide: {
                workflowTemplateService: Shopwell.Service('workflowTemplateService'),
                workflowService: service,
                acl: { can: () => true },
            },
            stubs: {
                'sw-page': { template: '<div><slot name="content" /></div>' },
                'sw-workflow-studio-editor': {
                    name: 'sw-workflow-studio-editor',
                    props: [
                        'document',
                        'hasDiagnosed',
                        'isDiagnosing',
                        'diagnosticError',
                        'violations',
                    ],
                    emits: ['diagnose', 'update:document', 'settings-change'],
                    template: `<div><button class="retry" @click="$emit('diagnose', document)">Retry</button><button class="edit" @click="$emit('update:document', document)">Edit</button><span class="result">{{ hasDiagnosed }}</span><span class="pending">{{ isDiagnosing }}</span><span class="error">{{ diagnosticError }}</span><span class="violations">{{ violations.length }}</span></div>`,
                },
            },
        },
    });
}

describe('sw-workflow-studio', () => {
    beforeEach(() => jest.useFakeTimers());
    afterEach(() => jest.useRealTimers());

    it('displays failed catalog loading and allows retry', async () => {
        const getNodeTypes = jest.fn().mockRejectedValueOnce(new Error('Unavailable')).mockResolvedValue(nodeTypes);
        const wrapper = await createWrapper({ getNodeTypes, diagnose: () => Promise.resolve([]) });
        await flushPromises();
        expect(wrapper.find('[role=alert]').exists()).toBe(true);
        await wrapper.find('button').trigger('click');
        await flushPromises();
        expect(wrapper.find('[role=alert]').exists()).toBe(false);
        wrapper.unmount();
    });

    it('checks the loaded draft automatically and debounces subsequent edits', async () => {
        const diagnose = jest.fn().mockResolvedValue([]);
        const wrapper = await createWrapper({ getNodeTypes: () => Promise.resolve(nodeTypes), diagnose });
        await flushPromises();
        expect(diagnose).toHaveBeenCalledTimes(1);
        expect(wrapper.find('.result').text()).toBe('true');
        await wrapper.find('.edit').trigger('click');
        await wrapper.find('.edit').trigger('click');
        expect(wrapper.find('.result').text()).toBe('false');
        expect(diagnose).toHaveBeenCalledTimes(1);
        await jest.advanceTimersByTimeAsync(500);
        await flushPromises();
        expect(diagnose).toHaveBeenCalledTimes(2);
        expect(wrapper.find('.result').text()).toBe('true');
        wrapper.unmount();
    });

    it('ignores stale failures and completion while a newer check is pending', async () => {
        let rejectOld: (reason: Error) => void = () => {};
        let resolveNew: (value: WorkflowViolation[]) => void = () => {};
        const diagnose = jest
            .fn()
            .mockReturnValueOnce(
                new Promise((_, reject) => {
                    rejectOld = reject;
                }),
            )
            .mockReturnValueOnce(
                new Promise((resolve) => {
                    resolveNew = resolve;
                }),
            );
        const wrapper = await createWrapper({ getNodeTypes: () => Promise.resolve(nodeTypes), diagnose });
        await flushPromises();
        await wrapper.find('.edit').trigger('click');
        await jest.advanceTimersByTimeAsync(500);
        rejectOld(new Error('Stale failure'));
        await flushPromises();
        expect(wrapper.find('.pending').text()).toBe('true');
        expect(wrapper.find('.error').text()).toBe('false');
        resolveNew([{ code: 'cycle', path: '/nodes', nodeId: 'approval' }]);
        await flushPromises();
        expect(wrapper.find('.violations').text()).toBe('1');
        expect(wrapper.find('.pending').text()).toBe('false');
        wrapper.unmount();
    });

    it('retries failed validation without reloading or replacing the document', async () => {
        const getNodeTypes = jest.fn().mockResolvedValue(nodeTypes);
        const diagnose = jest.fn().mockRejectedValueOnce(new Error('Offline')).mockResolvedValue([]);
        const wrapper = await createWrapper({ getNodeTypes, diagnose });
        await flushPromises();
        expect(wrapper.find('.error').text()).toBe('true');
        await wrapper.find('.retry').trigger('click');
        await flushPromises();
        expect(wrapper.find('.error').text()).toBe('false');
        expect(wrapper.find('.result').text()).toBe('true');
        expect(getNodeTypes).toHaveBeenCalledTimes(1);
        await wrapper.find('.edit').trigger('click');
        wrapper.unmount();
        await jest.advanceTimersByTimeAsync(500);
        expect(diagnose).toHaveBeenCalledTimes(2);
    });
});
