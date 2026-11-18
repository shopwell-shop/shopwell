/** @sw-package framework */
import '../../service/extension/approval-workflow.registration';
import type { ComponentPublicInstance } from 'vue';
import type { VueWrapper } from '@vue/test-utils';
import { mount, flushPromises } from '@vue/test-utils';
import '../../service';
import WorkflowApprovalService from '../../service/workflow-approval.service';
const node = { id: 'a', type: 'core:approval', typeVersion: 1, config: {} };
const wrappers: VueWrapper[] = [];

async function createWrapper(allowEdit = true, service = Shopwell.Service('workflowApprovalService')) {
    const wrapper: VueWrapper = mount(await wrapTestComponent('sw-workflow-approval-settings', { sync: true }), {
        props: { node, allowEdit },
        global: {
            provide: {
                workflowApprovalService: service,
                workflowRequestFormService: Shopwell.Service('workflowRequestFormService'),
            },
            stubs: {
                'mt-tabs': {
                    props: ['items'],
                    template:
                        '<div><button v-for="item in items" :key="item.name" :data-tab="item.name" @click="$emit(\'new-item-active\', item.name)">{{ item.label }}</button></div>',
                },
                'sw-workflow-users-assignee-select': true,
                'sw-workflow-user-assignee-select': {
                    props: ['config', 'allowEdit'],
                    emits: ['update-config'],
                    template:
                        '<button class="select-user" :disabled="!allowEdit" @click="$emit(\'update-config\', { userId: \'11111111111111111111111111111111\' })">Select</button>',
                },
            },
        },
    });
    wrappers.push(wrapper);
    return wrapper;
}
describe('approval settings', () => {
    afterEach(() => {
        wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
    });
    it('preserves a replacement policy across source changes and honors read-only mode', async () => {
        const wrapper = await createWrapper();
        const policy = wrapper.findComponent('[data-testid="missing-assignee-policy"]') as VueWrapper;
        policy.vm.$emit('update:modelValue', 'assign');
        let config = wrapper.emitted('update-config')!.at(-1)![0] as Record<string, unknown>;
        expect(config.missingAssignee).toEqual({ action: 'assign' });
        await wrapper.setProps({ node: { ...node, config } });
        await wrapper.findAll('.select-user').at(-1)!.trigger('click');
        config = wrapper.emitted('update-config')!.at(-1)![0] as Record<string, unknown>;
        expect(config.missingAssignee).toEqual({ action: 'assign', userId: '11111111111111111111111111111111' });
        await wrapper.setProps({ node: { ...node, config } });
        const source = wrapper.findComponent('.sw-workflow-approval-settings__source') as VueWrapper;
        source.vm.$emit('update:modelValue', 'core:initiator');
        expect(wrapper.emitted('update-config')!.at(-1)![0]).toMatchObject({ missingAssignee: config.missingAssignee });
        policy.vm.$emit('update:modelValue', 'approve');
        expect(wrapper.emitted('update-config')!.at(-1)![0]).toMatchObject({ missingAssignee: { action: 'approve' } });
        await wrapper.setProps({ allowEdit: false });
        const count = wrapper.emitted('update-config')!.length;
        policy.vm.$emit('update:modelValue', 'reject');
        expect(wrapper.emitted('update-config')).toHaveLength(count);
    });

    it('uses the Meteor active-tab event to show form permissions', async () => {
        const wrapper = await createWrapper();

        await wrapper.get('[data-tab="form"]').trigger('click');

        expect(wrapper.find('.sw-workflow-approval-settings__configuration').exists()).toBe(false);
        await wrapper.get('[data-tab="settings"]').trigger('click');
        expect(wrapper.find('.sw-workflow-approval-settings__configuration').exists()).toBe(true);
    });

    it('enables a deadline, preserves it across source changes and removes it explicitly', async () => {
        const wrapper = await createWrapper();
        const enabled = wrapper.findComponent('[data-testid="approval-deadline-enabled"]') as VueWrapper;
        enabled.vm.$emit('update:modelValue', true);
        const config = wrapper.emitted('update-config')!.at(-1)![0] as Record<string, unknown>;
        expect(config.deadline).toEqual({ minutes: 1440, remind: true });
        await wrapper.setProps({ node: { ...node, config } });
        const duration = wrapper.findComponent('[data-testid="approval-deadline-minutes"]') as VueWrapper;
        duration.vm.$emit('update:modelValue', 120);
        expect(wrapper.emitted('update-config')!.at(-1)![0]).toMatchObject({ deadline: { minutes: 120, remind: true } });
        const workingTime = wrapper.findComponent('[data-testid="approval-deadline-working-time"]') as VueWrapper;
        workingTime.vm.$emit('update:modelValue', true);
        expect(wrapper.emitted('update-config')!.at(-1)![0]).toMatchObject({
            deadline: { minutes: 1440, remind: true, workingTime: true },
        });
        await wrapper.setProps({ node: { ...node, config: wrapper.emitted('update-config')!.at(-1)![0] } });
        const escalation = wrapper.findComponent('[data-testid="approval-deadline-escalation"]') as VueWrapper;
        escalation.vm.$emit('update-config', { userId: '22222222222222222222222222222222' });
        expect(wrapper.emitted('update-config')!.at(-1)![0]).toMatchObject({
            deadline: { minutes: 1440, remind: true, workingTime: true, escalateTo: '22222222222222222222222222222222' },
        });
        const source = wrapper.findComponent('.sw-workflow-approval-settings__source') as VueWrapper;
        source.vm.$emit('update:modelValue', 'core:users');
        expect(wrapper.emitted('update-config')!.at(-1)![0]).toMatchObject({ deadline: { minutes: 1440, remind: true } });
        enabled.vm.$emit('update:modelValue', false);
        expect(wrapper.emitted('update-config')!.at(-1)![0]).not.toHaveProperty('deadline');
        await wrapper.setProps({ allowEdit: false });
        const count = wrapper.emitted('update-config')!.length;
        duration.vm.$emit('update:modelValue', 30);
        enabled.vm.$emit('update:modelValue', false);
        expect(wrapper.emitted('update-config')).toHaveLength(count);
    });
    it('stores stable IDs and the single-approval mode through normal config updates', async () => {
        const wrapper = await createWrapper();
        await wrapper.find('.select-user').trigger('click');
        expect(wrapper.emitted('update-config')![0][0]).toEqual({
            assignee: { source: 'core:user', config: { userId: '11111111111111111111111111111111' } },
            approval: { mode: 'core:single', config: {} },
        });
    });
    it('configures node operations and preserves them when the participant source changes', async () => {
        const wrapper = await createWrapper();
        const toggle = wrapper.findComponent('[data-operation="return"]') as VueWrapper;
        toggle.vm.$emit('update:modelValue', false);
        const config = wrapper.emitted('update-config')!.at(-1)![0] as Record<string, unknown>;
        expect(config.operations).toEqual(['transfer', 'add-sign']);
        await wrapper.setProps({ node: { ...node, config } });
        const select = wrapper.findComponent('.sw-workflow-approval-settings__source') as VueWrapper;
        select.vm.$emit('update:modelValue', 'core:users');
        expect(wrapper.emitted('update-config')!.at(-1)![0]).toMatchObject({ operations: ['transfer', 'add-sign'] });
        await wrapper.setProps({ allowEdit: false });
        const count = wrapper.emitted('update-config')!.length;
        toggle.vm.$emit('update:modelValue', true);
        expect(wrapper.emitted('update-config')).toHaveLength(count);
    });

    it('keeps unavailable extension configurations unchanged and prevents read-only editing', async () => {
        const wrapper = await createWrapper(false);
        await wrapper.find('.select-user').trigger('click');
        expect(wrapper.emitted('update-config')).toBeUndefined();
        await wrapper.setProps({
            node: {
                ...node,
                config: {
                    assignee: { source: 'custom:manager', config: { level: 2 } },
                    approval: { mode: 'custom:all', config: {} },
                },
            },
        });
        expect(wrapper.find('.sw-workflow-approval-settings__unavailable').exists()).toBe(true);
        expect(wrapper.emitted('update-config')).toBeUndefined();
    });
    it('offers compatible modes and preserves people when switching between all and any approval', async () => {
        const wrapper = await createWrapper();
        const sourceSelect = wrapper.findComponent('.sw-workflow-approval-settings__source') as VueWrapper;
        sourceSelect.vm.$emit('update:modelValue', 'core:users');
        await flushPromises();
        const config = wrapper.emitted('update-config')![0][0] as Record<string, unknown>;
        expect(config).toEqual({
            assignee: { source: 'core:users', config: {} },
            approval: { mode: 'core:all', config: {} },
        });
        const users = { userIds: ['11111111111111111111111111111111', '22222222222222222222222222222222'] };
        await wrapper.setProps({
            node: { ...node, config: { ...config, assignee: { source: 'core:users', config: users } } },
        });
        const modeSelect = wrapper.findComponent('.sw-workflow-approval-settings__mode-select') as VueWrapper<
            ComponentPublicInstance<{ options: { value: string }[] }>
        >;
        expect(modeSelect.props('options').map((option) => option.value)).toEqual([
            'core:all',
            'core:or-sign',
            'core:sequential',
            'core:any',
        ]);
        modeSelect.vm.$emit('update:modelValue', 'core:any');
        await flushPromises();
        expect(wrapper.emitted('update-config')!.at(-1)![0]).toEqual({
            assignee: { source: 'core:users', config: users },
            approval: { mode: 'core:any', config: {} },
        });
        const count = wrapper.emitted('update-config')!.length;
        modeSelect.vm.$emit('update:modelValue', 'core:single');
        expect(wrapper.emitted('update-config')).toHaveLength(count);
    });
    it('preserves an imported incompatible mode until the user chooses a compatible one', async () => {
        const wrapper = await createWrapper();
        const config = {
            assignee: { source: 'core:users', config: { userIds: ['11111111111111111111111111111111'] } },
            approval: { mode: 'core:single', config: {} },
        };
        await wrapper.setProps({ node: { ...node, config } });
        expect(wrapper.find('.sw-workflow-approval-settings__unavailable').exists()).toBe(true);
        expect(wrapper.emitted('update-config')).toBeUndefined();
        const select = wrapper.findComponent('.sw-workflow-approval-settings__mode-select') as VueWrapper;
        const options = (select.props() as { options: { value: string; disabled: boolean }[] }).options;
        expect(options.find((option) => option.value === 'core:single')?.disabled).toBe(true);
        select.vm.$emit('update:modelValue', 'core:all');
        expect(wrapper.emitted('update-config')![0][0]).toEqual({ ...config, approval: { mode: 'core:all', config: {} } });
    });
    it('clears the assignee only on explicit source change and chooses the source default mode', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            node: {
                ...node,
                config: {
                    assignee: { source: 'core:users', config: { userIds: ['11111111111111111111111111111111'] } },
                    approval: { mode: 'core:any', config: {} },
                },
            },
        });
        const select = wrapper.findComponent('.sw-workflow-approval-settings__source') as VueWrapper;
        select.vm.$emit('update:modelValue', 'core:user');
        expect(wrapper.emitted('update-config')![0][0]).toEqual({
            assignee: { source: 'core:user', config: {} },
            approval: { mode: 'core:single', config: {} },
        });
    });
    it('allows an extension mode after the source explicitly opts in, preserving stored mode settings', async () => {
        const service = new WorkflowApprovalService();
        const source = Shopwell.Service('workflowApprovalService').getSource('core:users')!;
        service.registerSource(source);
        service.registerMode({ name: 'example:quorum', labelKey: 'example.quorum', descriptionKey: 'example.description' });
        service.registerMode(Shopwell.Service('workflowApprovalService').getMode('core:all')!);
        const disposeSupport = service.registerSupportedMode(source.name, 'example:quorum');
        const wrapper = await createWrapper(true, service);
        const config = {
            assignee: { source: 'core:users', config: { userIds: ['11111111111111111111111111111111'] } },
            approval: { mode: 'example:quorum', config: { threshold: 2 } },
        };
        await wrapper.setProps({ node: { ...node, config } });
        const select = wrapper.findComponent('.sw-workflow-approval-settings__mode-select') as VueWrapper;
        const options = (select.props() as { options: { value: string; disabled: boolean }[] }).options;
        expect(options.find((option) => option.value === 'example:quorum')?.disabled).toBe(false);
        expect(wrapper.find('.sw-workflow-approval-settings__unavailable').exists()).toBe(false);
        expect(wrapper.emitted('update-config')).toBeUndefined();
        const people = wrapper.findComponent({ name: 'sw-workflow-users-assignee-select' }) as VueWrapper;
        people.vm.$emit('update-config', { userIds: ['22222222222222222222222222222222'] });
        expect(wrapper.emitted('update-config')![0][0]).toMatchObject({ approval: config.approval });
        disposeSupport();
        await flushPromises();
        expect(wrapper.find('.sw-workflow-approval-settings__unavailable').exists()).toBe(true);
        expect(wrapper.emitted('update-config')).toHaveLength(1);
    });
    it('selects the saved mode version and preserves its settings on participant changes', async () => {
        const service = new WorkflowApprovalService();
        service.registerSource({
            name: 'example:team',
            labelKey: 'example.team',
            settingsComponent: 'sw-workflow-users-assignee-select',
            summaryComponent: 'example-summary',
        });
        service.registerMode({
            name: 'example:quorum',
            version: 1,
            labelKey: 'example.v1',
            descriptionKey: 'example.description',
        });
        service.registerMode({
            name: 'example:quorum',
            version: 2,
            labelKey: 'example.v2',
            descriptionKey: 'example.description',
        });
        const wrapper = await createWrapper(true, service);
        const config = {
            assignee: { source: 'example:team', config: { userIds: [] } },
            approval: { mode: 'example:quorum', version: 2, config: { required: 2 } },
        };
        await wrapper.setProps({ node: { ...node, config } });

        const select = wrapper.findComponent('.sw-workflow-approval-settings__mode-select') as VueWrapper<
            ComponentPublicInstance<{ modelValue: string | null }>
        >;
        expect(select.props('modelValue')).toBe('example:quorum@2');
        select.vm.$emit('update:modelValue', 'example:quorum');
        expect(wrapper.emitted('update-config')!.at(-1)![0]).toEqual({
            ...config,
            approval: { mode: 'example:quorum', config: {} },
        });
    });
});
