/**
 * @sw-package after-sales
 */
import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import type CriteriaType from 'src/core/data/criteria.data';

/**
 * The shape the http layer rejects with - an axios error carries the validation of the server under
 * `response.data.errors`, and the page renders `errors[0].detail`.
 */
function httpError(detail: string): Error {
    const error = new Error(detail) as Error & { response: { data: { errors: Array<{ detail: string }> } } };
    error.response = { data: { errors: [{ detail }] } };

    return error;
}

const formData = {
    orderNumber: 'SW-10001',
    currency: 'CNY',
    decimals: 2,
    positions: [{ lineItemId: 'line-item-1', label: 'a product', quantity: 3, returnable: 2, unitPrice: 10.5 }],
    reasons: [{ id: 'reason-1', label: 'broken' }],
};

const orderReturns = [
    {
        id: 'return-1',
        returnNumber: 'RT-1',
        type: 'return_refund',
        refundAmount: 2100,
        currency: 'CNY',
        createdAt: '2026-10-01T10:00:00.000Z',
        deadlineAt: '2026-10-05T10:00:00.000Z',
        stateMachineState: { technicalName: 'requested', name: 'Requested', translated: { name: 'Waiting' } },
    },
];

let activeWrapper: VueWrapper | null = null;

async function createWrapper(can: (privilege: string) => boolean = () => true, api: Record<string, unknown> = {}) {
    const search = jest.fn<Promise<typeof orderReturns & { total: number }>, [CriteriaType]>(() =>
        Promise.resolve(Object.assign([...orderReturns], { total: orderReturns.length })),
    );
    const formDataMock = jest.fn(() => Promise.resolve(formData));
    const createMock = jest.fn(() => Promise.resolve({ id: 'return-2', returnNumber: 'RT-2' }));

    const wrapper = mount(await wrapTestComponent('sw-order-detail-return', { sync: true }), {
        props: { orderId: 'order-id' },
        global: {
            stubs: {
                'sw-data-grid': {
                    name: 'sw-data-grid',
                    props: ['dataSource'],
                    template: `<div><article v-for="item in dataSource" :key="item.id"><slot v-for="(_, name) in $slots" :name="name" :item="item"/></article></div>`,
                },
                'sw-loader': true,
                // the card passes its body through the `grid` slot, so the stub has to render both -
                // otherwise the grid and the toolbar would never reach the template under test
                'mt-card': { template: '<div class="mt-card-stub"><slot></slot><slot name="grid"></slot></div>' },
                'sw-card-section': { template: '<div><slot></slot></div>' },
                'sw-modal': {
                    template: '<div class="sw-modal-stub"><slot></slot><slot name="modal-footer"></slot></div>',
                },
                'mt-empty-state': true,
                'mt-button': { props: ['disabled'], template: '<button :disabled="disabled"><slot/></button>' },
                'mt-badge': { props: ['variant'], template: '<span :data-variant="variant"><slot/></span>' },
                'mt-banner': true,
                'mt-checkbox': {
                    props: ['checked', 'label'],
                    template: `<label>{{ label }}<input type="checkbox" :checked="checked" @change="$emit('update:checked', $event.target.checked)"/></label>`,
                },
                'mt-number-field': {
                    props: [
                        'modelValue',
                        'disabled',
                        'max',
                        'min',
                        'size',
                    ],
                    template: `<input type="number" :value="modelValue" :disabled="disabled" :min="min" :max="max" @input="$emit('update:modelValue', Number($event.target.value))"/>`,
                },
                'mt-select': {
                    props: ['modelValue', 'options'],
                    template: `<select :value="modelValue" @change="$emit('update:modelValue', $event.target.value)"><option v-for="option in options" :value="option.value">{{ option.label }}</option></select>`,
                },
                'mt-textarea': {
                    props: ['modelValue'],
                    template: `<textarea :value="modelValue" @input="$emit('update:modelValue', $event.target.value)"/>`,
                },
                'router-link': { template: '<a><slot></slot></a>' },
            },
            provide: {
                repositoryFactory: {
                    create: () => {
                        return { search };
                    },
                },
                acl: { can },
                orderReturnApiService: {
                    formData: formDataMock,
                    create: createMock,
                    ...api,
                },
            },
            mocks: {
                $route: { name: 'sw.order.detail.returns', meta: { $module: { icon: 'regular-undo' } } },
                $router: { push: jest.fn() },
                $t: (key: string) => key,
                $te: () => false,
            },
        },
    });

    activeWrapper = wrapper;

    return { wrapper, search, formDataMock, createMock };
}

describe('src/module/sw-order-return/page/sw-order-detail-return', () => {
    afterEach(() => {
        activeWrapper?.unmount();
        activeWrapper = null;
        jest.restoreAllMocks();
    });

    it('loads the returns of this order only', async () => {
        const { wrapper, search } = await createWrapper();
        await flushPromises();
        const criteria = search.mock.calls[0][0];
        expect(criteria.filters).toEqual([expect.objectContaining({ field: 'orderId', value: 'order-id' })]);
        expect(criteria.associations.map((association) => association.association)).toContain('stateMachineState');
        expect(wrapper.getComponent({ name: 'sw-data-grid' }).props('dataSource')).toEqual(
            Object.assign([...orderReturns], { total: orderReturns.length }),
        );
    });

    it('offers the create button to an editor only', async () => {
        const { wrapper } = await createWrapper((privilege) => privilege === 'order_return.viewer');
        await flushPromises();
        expect(wrapper.find('.sw-order-detail-return__button-create').exists()).toBe(false);
    });

    it('builds the draft from the positions the server offers', async () => {
        const { wrapper, formDataMock } = await createWrapper();
        await wrapper.get('.sw-order-detail-return__button-create').trigger('click');
        await flushPromises();
        expect(formDataMock).toHaveBeenCalledWith('order-id');
        expect(wrapper.find('.sw-order-detail-return__create-modal').exists()).toBe(true);
        const position = wrapper.get('.sw-order-detail-return__position');
        expect(position.text()).toContain('a product');
        expect(position.get('input[type="checkbox"]').element).toHaveProperty('checked', false);
        expect(position.get('.sw-order-detail-return__position-returnable').text()).toBe('2');
        expect(position.get('input[type="number"]').element).toHaveProperty('value', '2');
        expect(position.get('input[type="number"]').attributes('max')).toBe('2');
        expect(wrapper.get('.sw-order-detail-return__create-reason').element).toHaveProperty('value', 'reason-1');
        expect(wrapper.get('.sw-order-detail-return__button-confirm').attributes('disabled')).toBeDefined();
    });

    it('renders one row per returnable position in the create modal', async () => {
        const { wrapper } = await createWrapper();
        await wrapper.get('.sw-order-detail-return__button-create').trigger('click');
        await flushPromises();
        expect(wrapper.findAll('.sw-order-detail-return__position')).toHaveLength(1);
    });

    it('opens no modal when the positions can not be read', async () => {
        const notification = jest.spyOn(Shopwell.Store.get('notification'), 'createNotification');
        const { wrapper } = await createWrapper(undefined, {
            formData: jest.fn(() => Promise.reject(httpError('order is gone'))),
        });
        await wrapper.get('.sw-order-detail-return__button-create').trigger('click');
        await flushPromises();
        expect(wrapper.find('.sw-order-detail-return__create-modal').exists()).toBe(false);
        expect(notification).toHaveBeenCalledWith(expect.objectContaining({ message: 'order is gone' }));
    });

    it('sends only the selected positions', async () => {
        const notification = jest.spyOn(Shopwell.Store.get('notification'), 'createNotification');
        const { wrapper, createMock } = await createWrapper();
        await wrapper.get('.sw-order-detail-return__button-create').trigger('click');
        await flushPromises();
        await wrapper.get('input[type="checkbox"]').setValue(true);
        await wrapper.get('.sw-order-detail-return__position-quantity').setValue(2);
        await wrapper.get('.sw-order-detail-return__create-type').setValue('refund_only');
        await wrapper.get('.sw-order-detail-return__create-note').setValue('  the customer called  ');
        await wrapper.get('.sw-order-detail-return__button-confirm').trigger('click');
        await flushPromises();
        expect(createMock).toHaveBeenCalledWith('order-id', {
            type: 'refund_only',
            positions: [{ lineItemId: 'line-item-1', quantity: 2 }],
            reasonId: 'reason-1',
            customerNote: 'the customer called',
        });
        expect(wrapper.find('.sw-order-detail-return__create-modal').exists()).toBe(false);
        expect(notification).toHaveBeenCalledWith(expect.objectContaining({ variant: 'success' }));
    });

    it('does not send a return without a position', async () => {
        const { wrapper, createMock } = await createWrapper();
        await wrapper.get('.sw-order-detail-return__button-create').trigger('click');
        await flushPromises();
        expect(wrapper.get('.sw-order-detail-return__button-confirm').attributes('disabled')).toBeDefined();
        await wrapper.get('.sw-order-detail-return__button-confirm').trigger('click');
        expect(createMock).not.toHaveBeenCalled();
    });

    it('reports the refusal of the server instead of a status code', async () => {
        const notification = jest.spyOn(Shopwell.Store.get('notification'), 'createNotification');
        const { wrapper } = await createWrapper(undefined, {
            create: jest.fn(() => Promise.reject(httpError('the return window is over'))),
        });
        await wrapper.get('.sw-order-detail-return__button-create').trigger('click');
        await flushPromises();
        await wrapper.get('input[type="checkbox"]').setValue(true);
        await wrapper.get('.sw-order-detail-return__button-confirm').trigger('click');
        await flushPromises();
        expect(notification).toHaveBeenCalledWith(expect.objectContaining({ message: 'the return window is over' }));
        expect(wrapper.get('.sw-order-detail-return__button-confirm').attributes('disabled')).toBeUndefined();
    });

    it('renders the state, type and overdue deadline with the operator vocabulary', async () => {
        const { wrapper } = await createWrapper();
        await flushPromises();
        const grid = wrapper.get('.sw-order-detail-return__grid');
        expect(grid.get('[data-variant]').text()).toBe('Waiting');
        expect(grid.get('[data-variant]').attributes('data-variant')).toBe('attention');
        expect(grid.text()).toContain('return_refund');
        expect(grid.get('.sw-order-detail-return__overdue').text()).toBe('sw-order-return.orderTab.textOverdue');
    });
});
