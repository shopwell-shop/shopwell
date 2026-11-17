/** @sw-package framework */
import WorkflowApprovalService from '../../service/workflow-approval.service';
import { shallowMount, mount, flushPromises } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';

describe('workflow approval decisions', () => {
    it('requires a rejection reason and locks both actions while submitting', async () => {
        const wrapper: VueWrapper = shallowMount(await wrapTestComponent('sw-workflow-approval-actions', { sync: true }), {
            global: {
                provide: { workflowApprovalService: new WorkflowApprovalService(), acl: { can: () => true } },
                stubs: {
                    'mt-textarea': {
                        props: ['modelValue'],
                        template:
                            '<textarea :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
                    },
                    'mt-button': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
                },
            },
        });
        const reject = wrapper.findAll('button').filter((button) => button.text().endsWith('.reject'))[0];
        expect(reject.attributes('disabled')).toBeDefined();
        await wrapper.get('textarea').setValue('  Invalid account  ');
        await reject.trigger('click');
        expect(wrapper.emitted('reject')).toEqual([['Invalid account']]);
        await wrapper.setProps({ approveDisabled: true });
        const approve = wrapper.findAll('button').find((button) => button.text().endsWith('.approve'));
        expect(approve?.attributes('disabled')).toBeDefined();
        expect(reject.attributes('disabled')).toBeUndefined();
        await wrapper.setProps({ isLoading: true });
        expect(wrapper.findAll('button').every((button) => button.attributes('disabled') !== undefined)).toBe(true);
        wrapper.unmount();
    });
    it('requires a person and reason before emitting a participant action', async () => {
        const wrapper = shallowMount(await wrapTestComponent('sw-workflow-approval-actions', { sync: true }), {
            props: { availableActions: ['transfer', 'add-sign'] },
            global: {
                provide: { workflowApprovalService: new WorkflowApprovalService(), acl: { can: () => true } },
                stubs: {
                    'mt-button': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
                    'sw-modal': { template: '<div><slot /><slot name="modal-footer" /></div>' },
                    'sw-entity-single-select': {
                        template:
                            '<input class="participant-selector" @input="$emit(\'update:value\', $event.target.value)" />',
                    },
                    'mt-textarea': true,
                },
            },
        });
        await wrapper.get('[data-participation="add-sign"]').trigger('click');
        expect(wrapper.get('[data-participation-confirm]').attributes('disabled')).toBeDefined();
        await wrapper.get('.participant-selector').setValue('target');
        await wrapper.setData({ comment: '  Expert review  ' });
        await wrapper.get('[data-participation-confirm]').trigger('click');
        expect(wrapper.emitted('participate')).toEqual([
            [
                {
                    action: 'add-sign',
                    targetUserId: 'target',
                    comment: 'Expert review',
                },
            ],
        ]);
        wrapper.unmount();
    });

    it('does not expose participant selection without user read access', async () => {
        const wrapper = shallowMount(await wrapTestComponent('sw-workflow-approval-actions', { sync: true }), {
            props: { availableActions: ['transfer', 'add-sign'] },
            global: { provide: { workflowApprovalService: new WorkflowApprovalService(), acl: { can: () => false } } },
        });
        expect(wrapper.find('[data-participation]').exists()).toBe(false);
        wrapper.unmount();
    });
    it('returns to the initiator only after an explanation is entered', async () => {
        const wrapper = shallowMount(await wrapTestComponent('sw-workflow-approval-actions', { sync: true }), {
            props: { availableActions: ['return'] },
            global: {
                provide: { workflowApprovalService: new WorkflowApprovalService(), acl: { can: () => false } },
                stubs: {
                    'mt-textarea': {
                        props: ['modelValue'],
                        template:
                            '<textarea :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
                    },
                    'mt-button': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
                },
            },
        });
        expect(wrapper.get('[data-return]').attributes('disabled')).toBeDefined();
        await wrapper.find('textarea').setValue('Please attach the invoice');
        await wrapper.get('[data-return]').trigger('click');
        expect(wrapper.emitted('return')).toEqual([['Please attach the invoice']]);
        expect(wrapper.emitted('reject')).toBeUndefined();
        wrapper.unmount();
    });
    it('renders an installed plugin action only when admitted by the server and disposes it reactively', async () => {
        const service = new WorkflowApprovalService();
        const dispose = service.registerOperation({
            name: 'example:consult',
            labelKey: 'example.consult',
            actionComponent: 'example-consult',
        });
        const wrapper: VueWrapper = mount(await wrapTestComponent('sw-workflow-approval-actions', { sync: true }), {
            props: { taskId: 'task-id', availableActions: ['example:consult'] },
            global: {
                provide: { workflowApprovalService: service, acl: { can: () => true } },
                stubs: {
                    'mt-textarea': true,
                    'mt-button': true,
                    'example-consult': {
                        props: ['taskId', 'isLoading'],
                        emits: ['completed'],
                        template:
                            '<button data-plugin-operation :disabled="isLoading" @click="$emit(\'completed\')">{{ taskId }}</button>',
                    },
                },
            },
        });
        expect(wrapper.get('[data-plugin-operation]').text()).toBe('task-id');
        await wrapper.get('[data-plugin-operation]').trigger('click');
        expect(wrapper.emitted('completed')).toHaveLength(1);
        await wrapper.setProps({ availableActions: [] });
        expect(wrapper.find('[data-plugin-operation]').exists()).toBe(false);
        await wrapper.setProps({ availableActions: ['example:consult'] });
        dispose();
        await flushPromises();
        expect(wrapper.find('[data-plugin-operation]').exists()).toBe(false);
        wrapper.unmount();
    });
});
