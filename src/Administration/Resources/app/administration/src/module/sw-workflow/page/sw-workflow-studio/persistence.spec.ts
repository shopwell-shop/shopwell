import '../../service';
/** @sw-package framework */
import { shallowMount, flushPromises } from '@vue/test-utils';
import type { WorkflowDraft } from 'src/core/service/api/workflow.api.service';
import { createWorkflowDraft } from 'src/core/workflow/workflow-document';
import { AxiosError } from 'axios';

const draft: WorkflowDraft = { id: 'saved', name: 'Original', revision: 3, document: createWorkflowDraft() };
async function createWrapper(
    updateDraft = jest.fn().mockResolvedValue({ ...draft, name: 'Renamed', revision: 4 }),
    canEdit = true,
    workflowId: string | null = draft.id,
) {
    const router = { replace: jest.fn(), push: jest.fn() };
    const service = {
        getNodeTypes: jest.fn().mockResolvedValue([
            {
                name: 'core:approval',
                version: 1,
                kind: 'activity',
                inputPorts: ['in'],
                outputPorts: ['approved', 'rejected'],
            },
        ]),
        diagnose: jest.fn().mockResolvedValue([]),
        getDraft: jest.fn().mockResolvedValue(draft),
        updateDraft,
        createDraft: jest.fn().mockResolvedValue({ ...draft, revision: 1 }),
    };
    const wrapper = shallowMount(await wrapTestComponent('sw-workflow-studio', { sync: true }), {
        props: { workflowId },
        global: {
            provide: {
                workflowTemplateService: Shopwell.Service('workflowTemplateService'),
                workflowService: service,
                acl: { can: () => canEdit },
            },
            mocks: { $router: router },
            stubs: {
                'sw-page': { template: '<div><slot name="content" /></div>' },
                'sw-workflow-studio-editor': {
                    template: '<div><slot name="document-info" /><slot name="document-actions" /></div>',
                },
                'mt-text-field': {
                    props: ['modelValue', 'disabled', 'size'],
                    emits: ['update:modelValue'],
                    template:
                        '<input :value="modelValue" :disabled="disabled" @input="$emit(\'update:modelValue\', $event.target.value)" />',
                },
                'mt-button': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
            },
        },
    });
    await flushPromises();
    return { wrapper, service, router };
}

describe('workflow draft persistence', () => {
    it('saves a new draft and navigates once after the pending save finishes', async () => {
        const { wrapper, service, router } = await createWrapper(undefined, true, null);
        router.replace.mockImplementation(() => {
            const event = new Event('beforeunload', { cancelable: true });
            window.dispatchEvent(event);
            expect(event.defaultPrevented).toBe(false);
        });

        await wrapper.find('.sw-workflow-studio__save').trigger('click');
        await flushPromises();

        expect(service.createDraft).toHaveBeenCalledTimes(1);
        expect(router.replace).toHaveBeenCalledTimes(1);
        expect(router.replace).toHaveBeenCalledWith({ name: 'sw.workflow.detail', params: { id: draft.id } });
        wrapper.unmount();
    });

    it('loads and renames a draft with its revision, then clears the dirty state', async () => {
        const { wrapper, service } = await createWrapper();
        expect(wrapper.find('input').element.value).toBe('Original');
        expect(wrapper.find('.sw-workflow-studio__save').attributes('disabled')).toBeDefined();
        await wrapper.find('input').setValue('Renamed');
        const event = new Event('beforeunload', { cancelable: true });
        window.dispatchEvent(event);
        expect(event.defaultPrevented).toBe(true);
        await wrapper.find('.sw-workflow-studio__save').trigger('click');
        await flushPromises();
        expect(service.updateDraft).toHaveBeenCalledWith('saved', 'Renamed', draft.document, 3);
        expect(wrapper.find('.sw-workflow-studio__save').attributes('disabled')).toBeDefined();
        const clean = new Event('beforeunload', { cancelable: true });
        window.dispatchEvent(clean);
        expect(clean.defaultPrevented).toBe(false);
        wrapper.unmount();
    });

    it('retains local changes and offers reload after a conflicting save', async () => {
        const error = new AxiosError('Conflict');
        Object.assign(error, { response: { status: 409 } });
        const { wrapper } = await createWrapper(jest.fn().mockRejectedValue(error));
        await wrapper.find('input').setValue('My work');
        await wrapper.find('.sw-workflow-studio__save').trigger('click');
        await flushPromises();
        expect(wrapper.find('input').element.value).toBe('My work');
        expect(wrapper.find('.sw-workflow-studio__reload').exists()).toBe(true);
        expect(wrapper.find('.sw-workflow-studio__save').attributes('disabled')).toBeUndefined();
        wrapper.unmount();
    });

    it('prevents readers from changing the name or saving', async () => {
        const { wrapper } = await createWrapper(undefined, false);
        expect(wrapper.find('input').attributes('disabled')).toBeDefined();
        expect(wrapper.find('.sw-workflow-studio__save').attributes('disabled')).toBeDefined();
        wrapper.unmount();
    });
});
