/**
 * @sw-package after-sales
 */
import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';

type DraftPosition = {
    lineItemId: string;
    label: string;
    quantity: number;
    returnable: number;
    unitPrice: number;
    selected: boolean;
    draftQuantity: number;
};

type OrderDetailReturnVm = {
    orderReturns: unknown;
    isLoading: boolean;
    isSubmitting: boolean;
    showCreateModal: boolean;
    orderNumber: string | null;
    reasons: Array<{ id: string; label: string }>;
    draftPositions: DraftPosition[];
    draftType: string;
    draftReasonId: string | null;
    draftNote: string;
    canEdit: boolean;
    canSubmit: boolean;
    selectedPositions: DraftPosition[];
    typeOptions: Array<{ value: string; label: string }>;
    reasonOptions: Array<{ value: string; label: string }>;
    createdComponent: () => Promise<void>;
    loadOrderReturns: () => Promise<void>;
    onOpenCreateModal: () => Promise<void>;
    onCloseCreateModal: () => void;
    onSubmit: () => void;
    stateLabel: (item: unknown) => string;
    stateVariant: (item: unknown) => string;
    typeLabel: (type: string | null) => string;
    deadlineLabel: (item: unknown) => string;
    isOverdue: (item: unknown) => boolean;
    errorMessage: (error: unknown) => string;
    createNotificationSuccess: (payload: { message: string }) => void;
    createNotificationError: (payload: { message: string }) => void;
};

type TypedWrapper = { vm: OrderDetailReturnVm } & VueWrapper;

function collection<T>(items: unknown[]): T {
    return Object.assign([...items], { total: items.length }) as unknown as T;
}

/**
 * The n-th argument a mock was called with, typed by the caller - `jest.Mock` hands out `any`, which
 * the lint rules of the repository do not allow to be read.
 */
function callArgument<T>(mock: jest.Mock, call = 0, argument = 0): T {
    const calls = mock.mock.calls as unknown as T[][];

    return calls[call][argument];
}

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

async function createWrapper(
    can: (privilege: string) => boolean = () => true,
    api: Record<string, unknown> = {},
): Promise<{ wrapper: TypedWrapper; search: jest.Mock; formDataMock: jest.Mock; createMock: jest.Mock }> {
    const search = jest.fn(() => Promise.resolve(collection<EntityCollection<'order_return'>>(orderReturns)));
    const formDataMock = jest.fn(() => Promise.resolve(formData));
    const createMock = jest.fn(() => Promise.resolve({ id: 'return-2', returnNumber: 'RT-2' }));

    const wrapper = mount(await wrapTestComponent('sw-order-detail-return', { sync: true }), {
        props: { orderId: 'order-id' },
        global: {
            stubs: {
                'sw-data-grid': true,
                'sw-loader': true,
                // the card passes its body through the `grid` slot, so the stub has to render both -
                // otherwise the grid and the toolbar would never reach the template under test
                'mt-card': { template: '<div class="mt-card-stub"><slot></slot><slot name="grid"></slot></div>' },
                'sw-card-section': { template: '<div><slot></slot></div>' },
                'sw-modal': {
                    template: '<div class="sw-modal-stub"><slot></slot><slot name="modal-footer"></slot></div>',
                },
                'mt-empty-state': true,
                'mt-button': true,
                'mt-badge': true,
                'mt-banner': true,
                'mt-checkbox': true,
                'mt-number-field': true,
                'mt-select': true,
                'mt-textarea': true,
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

    return { wrapper: wrapper as unknown as TypedWrapper, search, formDataMock, createMock };
}

describe('src/module/sw-order-return/page/sw-order-detail-return', () => {
    afterEach(() => {
        activeWrapper?.unmount();
        activeWrapper = null;
        jest.clearAllMocks();
    });

    it('loads the returns of this order only', async () => {
        const { wrapper, search } = await createWrapper();

        const criteria = callArgument<{
            filters: Array<{ field: string; value: string; type: string }>;
            associations: Array<{ association: string }>;
        }>(search);

        expect(criteria.filters).toEqual([expect.objectContaining({ field: 'orderId', value: 'order-id' })]);
        expect(criteria.associations.map((association) => association.association)).toContain('stateMachineState');
        expect(wrapper.vm.orderReturns).not.toBeNull();
    });

    it('offers the create button to an editor only', async () => {
        const { wrapper } = await createWrapper((privilege) => privilege === 'order_return.viewer');

        expect(wrapper.vm.canEdit).toBe(false);
    });

    it('builds the draft from the positions the server offers', async () => {
        const { wrapper, formDataMock } = await createWrapper();

        await wrapper.vm.onOpenCreateModal();

        expect(formDataMock).toHaveBeenCalledWith('order-id');
        expect(wrapper.vm.showCreateModal).toBe(true);
        expect(wrapper.vm.draftPositions).toEqual([
            {
                lineItemId: 'line-item-1',
                label: 'a product',
                quantity: 3,
                returnable: 2,
                unitPrice: 10.5,
                selected: false,
                draftQuantity: 2,
            },
        ]);
        // the reason is the vocabulary a return is opened with, so the first arranged one is preselected
        expect(wrapper.vm.draftReasonId).toBe('reason-1');
        expect(wrapper.vm.canSubmit).toBe(false);
    });

    it('renders one row per returnable position in the create modal', async () => {
        const { wrapper } = await createWrapper();

        await wrapper.vm.onOpenCreateModal();
        await flushPromises();

        // one row per product, and no row when the server offered none - the empty case is a banner
        expect(wrapper.findAll('.sw-order-detail-return__position')).toHaveLength(1);
    });

    it('opens no modal when the positions can not be read', async () => {
        const { wrapper } = await createWrapper(undefined, {
            formData: jest.fn(() => Promise.reject(httpError('order is gone'))),
        });

        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.vm.onOpenCreateModal();

        expect(wrapper.vm.showCreateModal).toBe(false);
        expect(callArgument<{ message: string }>(wrapper.vm.createNotificationError as jest.Mock)).toEqual({
            message: 'order is gone',
        });
    });

    it('sends only the selected positions', async () => {
        const { wrapper, createMock } = await createWrapper();

        wrapper.vm.createNotificationSuccess = jest.fn();

        await wrapper.vm.onOpenCreateModal();

        wrapper.vm.draftPositions[0].selected = true;
        wrapper.vm.draftPositions[0].draftQuantity = 2;
        wrapper.vm.draftType = 'refund_only';
        wrapper.vm.draftNote = '  the customer called  ';

        wrapper.vm.onSubmit();

        await flushPromises();

        expect(createMock).toHaveBeenCalledWith('order-id', {
            type: 'refund_only',
            positions: [{ lineItemId: 'line-item-1', quantity: 2 }],
            reasonId: 'reason-1',
            customerNote: 'the customer called',
        });
        expect(wrapper.vm.showCreateModal).toBe(false);
        expect(wrapper.vm.createNotificationSuccess).toHaveBeenCalled();
    });

    it('does not send a return without a position', async () => {
        const { wrapper, createMock } = await createWrapper();

        await wrapper.vm.onOpenCreateModal();

        wrapper.vm.onSubmit();

        await flushPromises();

        expect(createMock).not.toHaveBeenCalled();
    });

    it('reports the refusal of the server instead of a status code', async () => {
        const createMock = jest.fn(() => Promise.reject(httpError('the return window is over')));

        const { wrapper } = await createWrapper(undefined, { create: createMock });

        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.vm.onOpenCreateModal();
        wrapper.vm.draftPositions[0].selected = true;
        wrapper.vm.onSubmit();

        await flushPromises();

        expect(callArgument<{ message: string }>(wrapper.vm.createNotificationError as jest.Mock)).toEqual({
            message: 'the return window is over',
        });
        expect(wrapper.vm.isSubmitting).toBe(false);
    });

    it('renders the state of a return with the vocabulary of the operator', async () => {
        const { wrapper } = await createWrapper();

        const item = orderReturns[0];

        // the state machine names the state for the customer, the module for the operator: without a
        // translation of the module the name of the machine is the fallback, and a type without a
        // translation falls back to its technical name instead of printing a key
        expect(wrapper.vm.stateLabel(item)).toBe('Waiting');
        expect(wrapper.vm.stateVariant(item)).toBe('attention');
        expect(wrapper.vm.typeLabel('return_refund')).toBe('return_refund');
        expect(wrapper.vm.isOverdue({ deadlineAt: '2020-01-01T00:00:00.000Z' })).toBe(true);
        expect(wrapper.vm.deadlineLabel({ deadlineAt: '2020-01-01T00:00:00.000Z' })).toBe(
            'sw-order-return.orderTab.textOverdue',
        );
    });
});
