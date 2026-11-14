/**
 * @sw-package framework
 */
import { shallowMount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';

const configuration = {
    releaseId: 'release-1',
    releaseName: 'Finance',
    revision: 3,
    available: true,
    reason: null,
    releases: [
        { id: 'release-1', name: 'Finance', revision: 3, available: true, reason: null },
        { id: 'release-2', name: 'Finance', revision: 4, available: true, reason: null },
    ],
};
let wrapper: VueWrapper;

async function mountSettings(options: { get?: jest.Mock; save?: jest.Mock; canUpdate?: boolean } = {}) {
    const getWorkflowConfiguration = options.get ?? jest.fn().mockResolvedValue(configuration);
    const saveWorkflowConfiguration = options.save ?? jest.fn().mockResolvedValue(configuration);
    wrapper = shallowMount(await wrapTestComponent('sw-finance-transfer-workflow-settings', { sync: true }), {
        global: {
            mocks: { $te: () => true },
            provide: {
                paymentTransferApiService: { getWorkflowConfiguration, saveWorkflowConfiguration },
                acl: { can: (privilege: string) => privilege !== 'system_config:update' || options.canUpdate !== false },
            },
            stubs: {
                'sw-modal': { template: '<div><slot/><slot name="modal-footer"/></div>' },
                'mt-banner': { template: '<div><slot/></div>' },
                'mt-select': {
                    props: ['modelValue', 'options', 'disabled'],
                    template:
                        '<select :value="modelValue" :disabled="disabled" @change="$emit(\'update:modelValue\', $event.target.value)"><option v-for="option in options" :key="option.value" :value="option.value" :disabled="option.disabled">{{ option.label }}</option></select>',
                },
                'mt-button': { props: ['disabled'], template: '<button :disabled="disabled"><slot/></button>' },
                'mt-link': { template: '<a><slot/></a>' },
                'sw-loader': true,
            },
        },
    });
    await flushPromises();
    return { getWorkflowConfiguration, saveWorkflowConfiguration };
}

describe('transfer approval workflow settings', () => {
    afterEach(() => {
        wrapper?.unmount();
        jest.restoreAllMocks();
    });

    it('selects and saves a published revision without offering a disabled approval option', async () => {
        const { saveWorkflowConfiguration } = await mountSettings();
        expect(wrapper.findAll('option')).toHaveLength(2);
        expect(wrapper.get('select').element.value).toBe('release-1');
        await wrapper.get('select').setValue('release-2');
        await wrapper.get('.sw-finance-transfer-workflow-settings__save').trigger('click');
        await flushPromises();
        expect(saveWorkflowConfiguration).toHaveBeenCalledWith('release-2');
        expect(wrapper.emitted('modal-close')).toHaveLength(1);
    });

    it('guides the manager to publish the transfer template when no release is compatible', async () => {
        await mountSettings({ get: jest.fn().mockResolvedValue({ ...configuration, releaseId: null, releases: [] }) });
        expect(wrapper.text()).toContain('sw-finance.transfer.workflow.noReleases');
        expect(wrapper.get('a').text()).toContain('sw-finance.transfer.workflow.openDesigner');
        expect(wrapper.get('.sw-finance-transfer-workflow-settings__save').attributes('disabled')).toBeDefined();
    });

    it('explains an unavailable release and prevents binding it', async () => {
        const get = jest.fn().mockResolvedValue({
            ...configuration,
            available: false,
            reason: 'approval_assignee_forbidden',
            releases: [
                { id: 'release-1', name: 'Finance', revision: 3, available: false, reason: 'approval_assignee_forbidden' },
            ],
        });
        const { saveWorkflowConfiguration } = await mountSettings({ get });
        expect(wrapper.text()).toContain('sw-finance.transfer.workflow.checks.approval_assignee_forbidden');
        expect(wrapper.get('option').attributes('disabled')).toBeDefined();
        expect(wrapper.get('.sw-finance-transfer-workflow-settings__save').attributes('disabled')).toBeDefined();
        await wrapper.get('.sw-finance-transfer-workflow-settings__save').trigger('click');
        expect(saveWorkflowConfiguration).not.toHaveBeenCalled();
    });

    it('shows a changed reviewer prerequisite returned by the save request', async () => {
        await mountSettings({
            save: jest.fn().mockRejectedValue({
                isAxiosError: true,
                response: { data: { errors: [{ meta: { parameters: { reason: 'approval_assignee_unavailable' } } }] } },
            }),
        });
        await wrapper.get('.sw-finance-transfer-workflow-settings__save').trigger('click');
        await flushPromises();
        expect(wrapper.text()).toContain('sw-finance.transfer.workflow.checks.approval_assignee_unavailable');
        expect(wrapper.emitted('modal-close')).toBeUndefined();
    });

    it('prevents saving without update permissions', async () => {
        const { saveWorkflowConfiguration } = await mountSettings({ canUpdate: false });
        await wrapper.get('.sw-finance-transfer-workflow-settings__save').trigger('click');
        expect(saveWorkflowConfiguration).not.toHaveBeenCalled();
    });

    it('offers retry after a failed configuration load', async () => {
        const get = jest.fn().mockRejectedValueOnce(new Error('Offline')).mockResolvedValue(configuration);
        await mountSettings({ get });
        expect(wrapper.text()).toContain('sw-finance.errors.loadFailed');
        expect(wrapper.get('.sw-finance-transfer-workflow-settings__save').attributes('disabled')).toBeDefined();
        await wrapper.get('.sw-finance-transfer-workflow-settings__retry').trigger('click');
        await flushPromises();
        expect(wrapper.findAll('option')).toHaveLength(2);
    });

    it('keeps the selection after a save error and allows retry', async () => {
        const notify = jest.spyOn(Shopwell.Store.get('notification'), 'createNotification').mockReturnValue('notice');
        await mountSettings({ save: jest.fn().mockRejectedValue(new Error('Offline')) });
        await wrapper.get('select').setValue('release-2');
        await wrapper.get('.sw-finance-transfer-workflow-settings__save').trigger('click');
        await flushPromises();
        expect(notify).toHaveBeenCalledWith(expect.objectContaining({ variant: 'error' }));
        expect(wrapper.emitted('modal-close')).toBeUndefined();
        expect(wrapper.get('select').element.value).toBe('release-2');
        expect(wrapper.get('.sw-finance-transfer-workflow-settings__save').attributes('disabled')).toBeUndefined();
    });
});
