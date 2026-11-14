/** @sw-package framework */
import { shallowMount, flushPromises } from '@vue/test-utils';

async function createWrapper(canPublish = true, hasChanges = false, revision = 3) {
    const service = {
        getPublishers: jest
            .fn()
            .mockResolvedValue([{ name: 'example:approval', labelKey: 'example.publisher', canPublish: true }]),
        publishDraft: jest.fn().mockResolvedValue({ id: 'release', revision: 3 }),
    };
    const wrapper = shallowMount(await wrapTestComponent('sw-workflow-publish', { sync: true }), {
        props: { draftId: 'draft', revision, hasChanges },
        global: {
            provide: { workflowService: service, acl: { can: () => canPublish } },
            stubs: {
                'mt-button': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
                'sw-modal': { template: '<div><slot /><slot name="modal-footer" /></div>' },
                'router-link': { props: ['to'], template: '<a><slot /></a>' },
            },
        },
    });
    return { wrapper, service };
}

describe('workflow publication', () => {
    it('does not offer publishing to read-only users', async () => {
        const { wrapper, service } = await createWrapper(false);
        expect(wrapper.find('.sw-workflow-publish__open').exists()).toBe(false);
        expect(service.getPublishers).not.toHaveBeenCalled();
        wrapper.unmount();
    });

    it.each([
        [true, 3],
        [false, 0],
    ])('requires an unchanged saved revision (%s, %s)', async (hasChanges, revision) => {
        const { wrapper, service } = await createWrapper(true, hasChanges, revision);
        await wrapper.find('.sw-workflow-publish__open').trigger('click');
        expect(service.getPublishers).not.toHaveBeenCalled();
        wrapper.unmount();
    });

    it('publishes the saved revision once and links the released catalogue', async () => {
        const { wrapper, service } = await createWrapper();
        let resolve: (release: { id: string; revision: number }) => void = () => {};
        service.publishDraft.mockReturnValue(
            new Promise((done) => {
                resolve = done;
            }),
        );
        await wrapper.find('.sw-workflow-publish__open').trigger('click');
        await flushPromises();
        await wrapper.find('.sw-workflow-publish__confirm').trigger('click');
        await wrapper.find('.sw-workflow-publish__confirm').trigger('click');
        expect(service.publishDraft).toHaveBeenCalledTimes(1);
        expect(service.publishDraft).toHaveBeenCalledWith('draft', 3, 'example:approval');
        resolve({ id: 'release', revision: 3 });
        await flushPromises();
        expect(wrapper.find('a').exists()).toBe(true);
        wrapper.unmount();
    });

    it('keeps the confirmation open with an error after failure', async () => {
        const { wrapper, service } = await createWrapper();
        service.publishDraft.mockRejectedValue(new Error('Unavailable'));
        await wrapper.find('.sw-workflow-publish__open').trigger('click');
        await flushPromises();
        await wrapper.find('.sw-workflow-publish__confirm').trigger('click');
        await flushPromises();
        expect(wrapper.find('[role="alert"]').text()).toContain('sw-workflow.publication.failed');
        expect(wrapper.find('a').exists()).toBe(false);
        expect(wrapper.find('.sw-workflow-publish__confirm').attributes('disabled')).toBeUndefined();
        wrapper.unmount();
    });

    it.each(['invalid_graph', 'unsupported_approval_workflow', 'approval_assignee_unavailable'])(
        'shows the server rejection reason %s',
        async (reason) => {
            const { wrapper, service } = await createWrapper();
            service.publishDraft.mockRejectedValue({
                isAxiosError: true,
                response: { status: 422, data: { errors: [{ meta: { parameters: { reason } } }] } },
            });
            await wrapper.find('.sw-workflow-publish__open').trigger('click');
            await flushPromises();
            await wrapper.find('.sw-workflow-publish__confirm').trigger('click');
            await flushPromises();
            expect(wrapper.find('[role="alert"]').text()).toContain(`sw-workflow.publication.${reason}`);
            expect(wrapper.find('a').exists()).toBe(false);
            wrapper.unmount();
        },
    );

    it('requires an available publisher with publication permission', async () => {
        const { wrapper, service } = await createWrapper();
        service.getPublishers.mockResolvedValue([
            { name: 'example:approval', labelKey: 'example.publisher', canPublish: false },
        ]);
        await wrapper.find('.sw-workflow-publish__open').trigger('click');
        await flushPromises();
        expect(wrapper.text()).toContain('sw-workflow.publication.noProvider');
        await wrapper.find('.sw-workflow-publish__confirm').trigger('click');
        expect(service.publishDraft).not.toHaveBeenCalled();
        wrapper.unmount();
    });

    it('retries publisher loading inside the confirmation', async () => {
        const { wrapper, service } = await createWrapper();
        service.getPublishers.mockRejectedValueOnce(new Error('Offline'));
        await wrapper.find('.sw-workflow-publish__open').trigger('click');
        await flushPromises();
        await wrapper.find('.sw-workflow-publish__retry').trigger('click');
        await flushPromises();
        expect(service.getPublishers).toHaveBeenCalledTimes(2);
        expect(wrapper.find('[role="alert"]').exists()).toBe(false);
        expect(wrapper.find('.sw-workflow-publish__confirm').attributes('disabled')).toBeUndefined();
        wrapper.unmount();
    });

    it('ignores a publisher response from the previous draft', async () => {
        const { wrapper, service } = await createWrapper();
        let reject: (error: Error) => void = () => {};
        service.getPublishers.mockReturnValueOnce(
            new Promise((_, fail) => {
                reject = fail;
            }),
        );
        await wrapper.find('.sw-workflow-publish__open').trigger('click');
        await wrapper.setProps({ draftId: 'other-draft' } as never);
        await wrapper.find('.sw-workflow-publish__open').trigger('click');
        await flushPromises();
        reject(new Error('Old failure'));
        await flushPromises();
        expect(wrapper.find('[role="alert"]').exists()).toBe(false);
        expect(wrapper.find('.sw-workflow-publish__confirm').attributes('disabled')).toBeUndefined();
        wrapper.unmount();
    });

    it('ignores a completed publication after switching drafts', async () => {
        const { wrapper, service } = await createWrapper();
        let resolve: (release: { id: string; revision: number }) => void = () => {};
        service.publishDraft.mockReturnValueOnce(
            new Promise((done) => {
                resolve = done;
            }),
        );
        await wrapper.find('.sw-workflow-publish__open').trigger('click');
        await flushPromises();
        await wrapper.find('.sw-workflow-publish__confirm').trigger('click');
        await wrapper.setProps({ draftId: 'other-draft' } as never);
        await wrapper.find('.sw-workflow-publish__open').trigger('click');
        await flushPromises();
        resolve({ id: 'previous-release', revision: 3 });
        await flushPromises();
        expect(wrapper.find('a').exists()).toBe(false);
        expect(wrapper.find('.sw-workflow-publish__confirm').exists()).toBe(true);
        wrapper.unmount();
    });

    it('explains that a reviewer lacks approval privileges', async () => {
        const { wrapper, service } = await createWrapper();
        service.publishDraft.mockRejectedValueOnce({
            isAxiosError: true,
            response: {
                status: 422,
                data: { errors: [{ meta: { parameters: { reason: 'approval_assignee_forbidden' } } }] },
            },
        });
        await wrapper.find('.sw-workflow-publish__open').trigger('click');
        await flushPromises();
        await wrapper.find('.sw-workflow-publish__confirm').trigger('click');
        await flushPromises();
        expect(wrapper.text()).toContain('sw-workflow.publication.approval_assignee_forbidden');
        expect(wrapper.find('a').exists()).toBe(false);
        wrapper.unmount();
    });

    it('clears the release link when opening another draft', async () => {
        const { wrapper } = await createWrapper();
        await wrapper.find('.sw-workflow-publish__open').trigger('click');
        await flushPromises();
        await wrapper.find('.sw-workflow-publish__confirm').trigger('click');
        await flushPromises();
        expect(wrapper.find('a').exists()).toBe(true);
        await wrapper.setProps({ draftId: 'other-draft' } as never);
        expect(wrapper.find('a').exists()).toBe(false);
        wrapper.unmount();
    });

    it('disables confirmation if draft changes while choosing a publisher', async () => {
        const { wrapper, service } = await createWrapper();
        await wrapper.find('.sw-workflow-publish__open').trigger('click');
        await flushPromises();
        await wrapper.setProps({ hasChanges: true } as never);
        await wrapper.find('.sw-workflow-publish__confirm').trigger('click');
        expect(service.publishDraft).not.toHaveBeenCalled();
        wrapper.unmount();
    });
});
