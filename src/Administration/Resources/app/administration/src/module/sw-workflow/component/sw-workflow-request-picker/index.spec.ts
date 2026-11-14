/** @sw-package framework */
import { flushPromises, shallowMount } from '@vue/test-utils';
import WorkflowReleaseActionService from '../../service/workflow-release-action.service';

describe('workflow request picker', () => {
    it('uses the available request catalogue and dispatches the registered business action', async () => {
        const actions = new WorkflowReleaseActionService();
        actions.register('payment:transfer', 'transfer-request-action');
        const wrapper = shallowMount(await wrapTestComponent('sw-workflow-request-picker', { sync: true }), {
            global: {
                provide: {
                    workflowService: {
                        getRequestCatalogue: jest.fn().mockResolvedValue({
                            data: [
                                {
                                    id: 'release',
                                    name: 'Transfer',
                                    publisher: 'core:approval',
                                    requestAction: 'payment:transfer',
                                },
                            ],
                            total: 1,
                        }),
                    },
                    workflowReleaseActionService: actions,
                },
                stubs: {
                    'sw-modal': { template: '<div><slot /></div>' },
                    'sw-data-grid': {
                        props: ['dataSource'],
                        template: '<div v-if="dataSource.length"><slot name="column-action" :item="dataSource[0]" /></div>',
                    },
                    'transfer-request-action': { template: '<button>Transfer request</button>' },
                },
            },
        });
        await flushPromises();
        expect(wrapper.text()).toContain('Transfer request');
        expect(wrapper.find('sw-workflow-start-stub').exists()).toBe(false);
        wrapper.unmount();
    });
    it('shows a retry action after a failed load and restores the catalogue', async () => {
        const getRequestCatalogue = jest
            .fn()
            .mockRejectedValueOnce(new Error('Offline'))
            .mockResolvedValue({ data: [], total: 0 });
        const wrapper = shallowMount(await wrapTestComponent('sw-workflow-request-picker', { sync: true }), {
            global: {
                provide: {
                    workflowService: { getRequestCatalogue },
                    workflowReleaseActionService: new WorkflowReleaseActionService(),
                },
                stubs: {
                    'sw-modal': { template: '<div><slot /></div>' },
                    'mt-empty-state': { template: '<div class="empty-state"></div>' },
                    'mt-banner': { template: '<section role="alert"><slot /></section>' },
                    'mt-button': { template: '<button><slot /></button>' },
                },
            },
        });
        await flushPromises();
        expect(wrapper.find('[role="alert"]').exists()).toBe(true);
        await wrapper.get('button').trigger('click');
        await flushPromises();
        expect(getRequestCatalogue).toHaveBeenCalledTimes(2);
        expect(wrapper.find('[role="alert"]').exists()).toBe(false);
        expect(wrapper.find('.empty-state').exists()).toBe(true);
        wrapper.unmount();
    });
});
