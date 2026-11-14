import WorkflowRuntimeApiService from 'src/core/service/api/workflow-runtime.api.service';
import type { HttpClient } from 'src/core/factory/http-client.types';
import type { VueWrapper } from '@vue/test-utils';
import type { ComponentPublicInstance } from 'vue';
/** @sw-package framework */
import { shallowMount, flushPromises } from '@vue/test-utils';

import type { LoginService } from 'src/core/service/login.service';
/** @private */
Shopwell.Service().register('loginService', () => ({ getToken: () => 'test-token' }) as LoginService);

const document = { nodes: [{ id: 'start', type: 'core:start', config: {} }] };

async function createWrapper(
    canStart = true,
    getRelease = jest.fn().mockResolvedValue({ document }),
    getFormOptions = jest.fn().mockResolvedValue([{ value: 'CNY', label: '人民币 · CNY' }]),
): Promise<{ wrapper: VueWrapper; post: jest.Mock; push: jest.Mock }> {
    const post = jest.fn().mockResolvedValue({ data: { data: { id: 'instance' } } });
    const push = jest.fn();
    const wrapper = shallowMount(await wrapTestComponent('sw-workflow-start', { sync: true }), {
        props: { release: { id: 'release', name: 'Review' } },
        global: {
            provide: {
                acl: { can: () => canStart },
                workflowRuntimeApiService: new WorkflowRuntimeApiService(
                    { post } as unknown as HttpClient,
                    Shopwell.Service('loginService'),
                ),
                workflowService: { getRelease, getFormOptions },
                workflowRequestFormService: {
                    getFields: (config: { formFields?: unknown[] }) =>
                        config.formFields ?? [{ key: 'reason', label: 'Reason', type: 'text', required: false }],
                },
            },
            mocks: { $router: { push } },
            stubs: {
                'mt-text-field': true,
                'mt-textarea': true,
                'mt-select': true,
                'mt-button': {
                    props: ['disabled'],
                    template: '<button :disabled="disabled"><slot /></button>',
                },
                'sw-modal': {
                    template: '<div><slot /><slot name="modal-footer" /></div>',
                },
            },
        },
    });
    return { wrapper, post, push };
}

describe('core released workflow action', () => {
    it('renders a sourced field as a selection and rejects values outside its loaded options', async () => {
        const getRelease = jest.fn().mockResolvedValue({
            document: {
                nodes: [
                    {
                        type: 'core:start',
                        config: {
                            formFields: [
                                {
                                    key: 'settlement',
                                    label: 'Settlement currency',
                                    type: 'text',
                                    required: true,
                                    dataSource: 'core:currency',
                                },
                            ],
                        },
                    },
                ],
            },
        });
        const { wrapper, post } = await createWrapper(true, getRelease);
        await wrapper.get('.sw-workflow-start__open').trigger('click');
        await flushPromises();
        const select = wrapper.findComponent({ name: 'mt-select' }) as VueWrapper<ComponentPublicInstance>;
        expect(select.exists()).toBe(true);
        select.vm.$emit('update:modelValue', 'cny');
        await flushPromises();
        await wrapper.get('.sw-workflow-start__confirm').trigger('click');
        expect(post).not.toHaveBeenCalled();
        select.vm.$emit('update:modelValue', 'CNY');
        await flushPromises();
        await wrapper.get('.sw-workflow-start__confirm').trigger('click');
        await flushPromises();
        expect(post).toHaveBeenCalledWith(
            expect.any(String),
            expect.objectContaining({ formData: { settlement: 'CNY' } }),
            expect.any(Object),
        );
        wrapper.unmount();
    });

    it('blocks submission when the option source cannot be loaded', async () => {
        const getRelease = jest.fn().mockResolvedValue({
            document: {
                nodes: [
                    {
                        type: 'core:start',
                        config: {
                            formFields: [
                                {
                                    key: 'settlement',
                                    label: 'Currency',
                                    type: 'text',
                                    required: true,
                                    dataSource: 'core:currency',
                                },
                            ],
                        },
                    },
                ],
            },
        });
        const { wrapper, post } = await createWrapper(
            true,
            getRelease,
            jest.fn().mockRejectedValue(new Error('Unavailable')),
        );
        await wrapper.get('.sw-workflow-start__open').trigger('click');
        await flushPromises();
        await wrapper.get('.sw-workflow-start__confirm').trigger('click');
        expect(post).not.toHaveBeenCalled();
        expect(wrapper.find('[role="alert"]').exists()).toBe(true);
        wrapper.unmount();
    });

    it('blocks users without creator permission', async () => {
        const { wrapper, post } = await createWrapper(false);
        await wrapper.find('.sw-workflow-start__open').trigger('click');
        await flushPromises();
        expect(wrapper.find('.sw-workflow-start__confirm').exists()).toBe(false);
        expect(post).not.toHaveBeenCalled();
        wrapper.unmount();
    });

    it('starts the immutable definition once and opens the existing instance detail', async () => {
        const { wrapper, post, push } = await createWrapper();
        let resolve: (value: unknown) => void = () => {};
        post.mockReturnValue(
            new Promise((done) => {
                resolve = done;
            }),
        );
        await wrapper.find('.sw-workflow-start__open').trigger('click');
        await flushPromises();
        await wrapper.find('.sw-workflow-start__confirm').trigger('click');
        await wrapper.find('.sw-workflow-start__confirm').trigger('click');
        expect(post).toHaveBeenCalledTimes(1);
        expect(post).toHaveBeenCalledWith(
            '/_action/workflow/instance',
            {
                definitionId: 'release',
                title: 'Review',
                formData: { reason: '' },
            },
            expect.objectContaining({
                headers: {
                    Authorization: 'Bearer test-token',
                    Accept: 'application/vnd.api+json',
                    'Content-Type': 'application/json',
                    'sw-language-id': Shopwell.Context.api.languageId,
                },
            }),
        );
        expect(push).not.toHaveBeenCalled();
        resolve({ data: { data: { id: 'instance' } } });
        await flushPromises();
        expect(push).toHaveBeenCalledWith({
            name: 'sw.workflow.instance',
            params: { id: 'instance' },
        });
        wrapper.unmount();
    });

    it('does not navigate when a pending request finishes after unmount', async () => {
        const { wrapper, post, push } = await createWrapper();
        let resolve: (value: unknown) => void = () => {};
        post.mockReturnValue(
            new Promise((done) => {
                resolve = done;
            }),
        );
        await wrapper.find('.sw-workflow-start__open').trigger('click');
        await flushPromises();
        await wrapper.find('.sw-workflow-start__confirm').trigger('click');
        wrapper.unmount();
        resolve({ data: { data: { id: 'old-instance' } } });
        await flushPromises();
        expect(push).not.toHaveBeenCalled();
    });

    it('ignores a stale rejection after switching releases and starting again', async () => {
        const { wrapper, post, push } = await createWrapper();
        let reject: (error: Error) => void = () => {};
        let resolve: (value: unknown) => void = () => {};
        post.mockReturnValueOnce(
            new Promise((_, fail) => {
                reject = fail;
            }),
        );
        post.mockReturnValueOnce(
            new Promise((done) => {
                resolve = done;
            }),
        );
        await wrapper.find('.sw-workflow-start__open').trigger('click');
        await flushPromises();
        await wrapper.find('.sw-workflow-start__confirm').trigger('click');
        await wrapper.setProps({
            release: { id: 'new-release', name: 'New review' },
        });
        await wrapper.find('.sw-workflow-start__open').trigger('click');
        await flushPromises();
        await wrapper.find('.sw-workflow-start__confirm').trigger('click');
        reject(new Error('Old failure'));
        await flushPromises();
        expect(wrapper.find('[role="alert"]').exists()).toBe(false);
        expect(wrapper.find('.sw-workflow-start__confirm').attributes('disabled')).toBeDefined();
        resolve({ data: { data: { id: 'new-instance' } } });
        await flushPromises();
        expect(push).toHaveBeenCalledTimes(1);
        expect(push).toHaveBeenCalledWith({
            name: 'sw.workflow.instance',
            params: { id: 'new-instance' },
        });
        wrapper.unmount();
    });

    it('keeps input and shows an error when starting fails', async () => {
        const { wrapper, post, push } = await createWrapper();
        post.mockRejectedValue(new Error('Unavailable'));
        await wrapper.find('.sw-workflow-start__open').trigger('click');
        await flushPromises();
        await wrapper.find('.sw-workflow-start__confirm').trigger('click');
        await flushPromises();
        expect(wrapper.find('[role="alert"]').exists()).toBe(true);
        expect(wrapper.find('.sw-workflow-start__confirm').attributes('disabled')).toBeUndefined();
        expect(push).not.toHaveBeenCalled();
        wrapper.unmount();
    });
});

describe('runtime interactions', () => {
    it('ignores a stale published form load after switching releases', async () => {
        let resolve!: (value: unknown) => void;
        const getRelease = jest
            .fn()
            .mockReturnValueOnce(
                new Promise((done) => {
                    resolve = done;
                }),
            )
            .mockResolvedValueOnce({
                document: {
                    nodes: [
                        {
                            type: 'core:start',
                            config: {
                                formFields: [
                                    {
                                        key: 'new',
                                        label: 'New field',
                                        type: 'text',
                                        required: true,
                                    },
                                ],
                            },
                        },
                    ],
                },
            });
        const { wrapper } = await createWrapper(true, getRelease);
        await wrapper.find('.sw-workflow-start__open').trigger('click');
        await wrapper.setProps({
            release: { id: 'new-release', name: 'New review' },
        });
        await wrapper.find('.sw-workflow-start__open').trigger('click');
        await flushPromises();
        resolve({ document });
        await flushPromises();
        expect(wrapper.findAll('mt-textarea-stub')).toHaveLength(1);
        expect(wrapper.find('mt-textarea-stub').attributes('label')).toBe('New field');
        expect(getRelease).toHaveBeenNthCalledWith(2, 'new-release');
        wrapper.unmount();
    });

    it('blocks submission when the published form cannot load', async () => {
        const { wrapper, post } = await createWrapper(true, jest.fn().mockRejectedValue(new Error('Release unavailable')));
        await wrapper.find('.sw-workflow-start__open').trigger('click');
        await flushPromises();
        expect(wrapper.find('[role="alert"]').text()).toContain('Release unavailable');
        expect(wrapper.find('.sw-workflow-start__confirm').attributes('disabled')).toBeDefined();
        expect(post).not.toHaveBeenCalled();
        wrapper.unmount();
    });

    it('renders all field controls, validates required values and preserves them on submission errors', async () => {
        const fields = [
            'text',
            'amount',
            'number',
            'date',
            'select',
        ].map((type) => ({
            key: type,
            label: type,
            type,
            required: true,
            ...(type === 'select' ? { options: [{ value: 'travel', label: 'Travel' }] } : {}),
        }));
        const { wrapper, post } = await createWrapper(
            true,
            jest.fn().mockResolvedValue({
                document: {
                    nodes: [{ type: 'core:start', config: { formFields: fields } }],
                },
            }),
        );
        await wrapper.find('.sw-workflow-start__open').trigger('click');
        await flushPromises();
        expect(wrapper.findAll('mt-text-field-stub')).toHaveLength(4);
        expect(wrapper.findAll('mt-textarea-stub')).toHaveLength(1);
        expect(wrapper.findAll('mt-select-stub')).toHaveLength(1);
        await wrapper.find('.sw-workflow-start__confirm').trigger('click');
        expect(post).not.toHaveBeenCalled();
        const values = {
            text: 'Trip',
            amount: '999999999999.99',
            number: '-2.123456',
            date: '2026-11-13',
            select: 'travel',
        };
        await wrapper.setData({ values });
        post.mockRejectedValue({
            response: { data: { errors: [{ detail: 'Amount is not accepted' }] } },
        });
        await wrapper.find('.sw-workflow-start__confirm').trigger('click');
        await flushPromises();
        expect(post).toHaveBeenCalledWith(
            expect.anything(),
            expect.objectContaining({ formData: values }),
            expect.anything(),
        );
        expect(wrapper.find('mt-textarea-stub').attributes('modelvalue')).toBe('Trip');
        expect(wrapper.find('[role="alert"]').text()).toContain('Amount is not accepted');
        wrapper.unmount();
    });
});
