/** @sw-package framework */
import { flushPromises, shallowMount } from '@vue/test-utils';

const business = {
    key: 'payment:transfer@1.0.0',
    type: 'payment:transfer',
    version: '1.0.0',
    labelSnippet: 'sw-finance.navigation.transfer',
    formSchema: '{}',
    formSchemaId: 'payment:transfer',
    formSchemaHash: 'hash',
};

async function createWrapper(activeReleaseId?: string) {
    const binding = activeReleaseId && { releaseId: activeReleaseId, businessVersion: business.version };
    const head = { latestRevision: 5, activeRevision: binding ? 4 : null, version: 3 };
    const activateRelease = jest.fn().mockResolvedValue(undefined);
    const wrapper = shallowMount(await wrapTestComponent('sw-approval-flow-release-activation', { sync: true }), {
        props: { release: { id: 'selected-release', draftRevision: 2 }, businessTypes: [business] },
        global: {
            provide: {
                approvalFlowDesignApiService: { activateRelease },
                repositoryFactory: {
                    create: (entity: string) => ({
                        search: jest.fn().mockResolvedValue({
                            first: () => (entity === 'approval_flow_binding_head' ? head : binding),
                        }),
                    }),
                },
            },
            stubs: {
                'mt-modal-root': { template: '<div><slot/></div>' },
                'mt-modal': { template: '<div><slot/><slot name="footer"/></div>' },
                'mt-button': { template: '<button><slot/></button>' },
                'mt-select': true,
                'mt-loader': true,
            },
        },
    });
    await flushPromises();
    return { wrapper, activateRelease };
}

describe('approval release activation', () => {
    it('uses the current counters and the exact business version without switching on open', async () => {
        const { wrapper, activateRelease } = await createWrapper('previous-release');
        expect(activateRelease).not.toHaveBeenCalled();

        await wrapper.get('[data-testid="activate-approval-release"]').trigger('click');
        await flushPromises();
        expect(activateRelease).toHaveBeenCalledWith('selected-release', {
            businessType: 'payment:transfer',
            businessVersion: '1.0.0',
            expectedRevision: 5,
            expectedVersion: 3,
        });
        expect(wrapper.emitted('activated')).toEqual([['selected-release']]);
    });

    it('shows that the selected release is already active and hides the activation action', async () => {
        const { wrapper, activateRelease } = await createWrapper('selected-release');

        expect(wrapper.text()).toContain('sw-approval-flow.design.alreadyActive');
        expect(wrapper.find('[data-testid="activate-approval-release"]').exists()).toBe(false);
        expect(activateRelease).not.toHaveBeenCalled();
    });
});
