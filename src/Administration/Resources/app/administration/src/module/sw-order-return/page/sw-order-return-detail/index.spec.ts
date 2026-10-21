/**
 * @sw-package after-sales
 */

import SwOrderReturnDetail from './index';

type LineItem = {
    id: string;
    orderLineItemId: string;
    orderLineItem?: { label?: string | null } | null;
};

type Message = {
    id: string;
    authorRole: string;
    content: string;
};

type OrderReturn = {
    id: string;
    returnNumber: string;
    orderId: string;
    type: string;
    stateMachineState?: { technicalName: string; name: string; translated?: { name: string } } | null;
    customer?: { firstName?: string | null; lastName?: string | null; email?: string | null } | null;
    guestEmail?: string | null;
    reason?: { label?: string | null; translated?: { label?: string | null } } | null;
    reasonText?: string | null;
    trackingCode?: string | null;
    trackingCarrier?: string | null;
    refundAmount: number | null;
    currency: string | null;
    createdAt: string | null;
    deadlineAt: string | null;
    order?: { orderNumber: string } | null;
    lineItems?: LineItem[] | null;
    messages?: Message[] | null;
};

type ApiServiceMock = {
    approve: jest.Mock;
    decline: jest.Mock;
    cancel: jest.Mock;
    receive: jest.Mock;
    refund: jest.Mock;
    addMessage: jest.Mock;
};

type DetailVm = {
    orderReturnId: string;
    orderReturn: OrderReturn | null;
    isLoading: boolean;
    isSubmitting: boolean;
    messageDraft: string;
    showDeclineModal: boolean;
    acl: { can: jest.Mock };
    repositoryFactory: { create: jest.Mock };
    orderReturnApiService: ApiServiceMock;
    orderReturnRepository: { create: jest.Mock; search: jest.Mock };
    dateFilter: (value: string | Date) => string;
    loadOrderReturn: jest.Mock;
    stateName: string | null;
    canDecide: boolean;
    availableActions: string[];
    stateLabel: string;
    stateVariant: string;
    typeLabel: string;
    customerLabel: string;
    reasonLabel: string;
    trackingLabel: string;
    isRefundOnly: boolean;
    lineItemColumns: Array<Record<string, unknown>>;
    generalFacts: Array<{ label: string; value: string }>;
    $t: (key: string) => string;
    $te: (key: string) => boolean;
    createNotificationSuccess: jest.Mock;
    createNotificationError: jest.Mock;
    criteria: () => unknown;
    runAction: (request: () => Promise<unknown>, successSnippet: string) => Promise<boolean>;
    onApprove: () => void;
    onDecline: (reason: string) => void;
    onCancel: () => void;
    onReceive: () => void;
    onRefund: () => void;
    onOpenDeclineModal: () => void;
    onCloseDeclineModal: () => void;
    onSendMessage: () => void;
    errorMessage: (error: unknown) => string;
    stateLabelOf: (technicalName: string | null | undefined, fallback: string) => string;
    formatAmount: (amount: number | null, currency: string | null) => string;
    formatDateOrDash: (value: unknown) => string;
    lineItemLabel: (lineItem: LineItem) => string;
    messageAuthor: (message: Message) => string;
};

type Detail = {
    computed: Record<string, (this: DetailVm) => unknown>;
    methods: Record<string, (this: DetailVm, ...args: unknown[]) => unknown>;
};

const page = SwOrderReturnDetail as unknown as Detail;

const orderReturn: OrderReturn = {
    id: 'return-1',
    returnNumber: 'R-1001',
    orderId: 'order-1',
    type: 'return_refund',
    stateMachineState: { technicalName: 'requested', name: 'Requested' },
    customer: { firstName: 'Ada', lastName: 'Lovelace', email: 'ada@example.com' },
    reason: { label: 'damaged' },
    trackingCode: 'SF123',
    trackingCarrier: 'SF',
    refundAmount: 19900,
    currency: 'CNY',
    createdAt: '2026-10-21T10:00:00Z',
    deadlineAt: null,
    order: { orderNumber: '10001' },
    lineItems: [{ id: 'line-1', orderLineItemId: 'order-line-1', orderLineItem: { label: 'Shoe' } }],
    messages: [{ id: 'message-1', authorRole: 'agent', content: 'called the customer' }],
};

function createVm(
    options: { canDecide?: boolean; translatedKeys?: string[]; orderReturn?: OrderReturn | null } = {},
): DetailVm {
    const api: ApiServiceMock = {
        approve: jest.fn().mockResolvedValue({}),
        decline: jest.fn().mockResolvedValue({}),
        cancel: jest.fn().mockResolvedValue({}),
        receive: jest.fn().mockResolvedValue({}),
        refund: jest.fn().mockResolvedValue({}),
        addMessage: jest.fn().mockResolvedValue({}),
    };

    const repository = {
        create: jest.fn(() => ({})),
        search: jest.fn().mockResolvedValue({ first: () => orderReturn }),
    };

    const canDecide = options.canDecide ?? true;

    const vm = {
        orderReturnId: 'return-1',
        orderReturn: options.orderReturn === undefined ? orderReturn : options.orderReturn,
        isLoading: false,
        isSubmitting: false,
        messageDraft: '',
        showDeclineModal: false,
        acl: { can: jest.fn(() => canDecide) },
        repositoryFactory: { create: jest.fn(() => repository) },
        orderReturnApiService: api,
        orderReturnRepository: repository,
        dateFilter: (value: string | Date) => `date:${String(value)}`,
        $t: (key: string) => key,
        $te: (key: string) => (options.translatedKeys ?? []).includes(key),
        createNotificationSuccess: jest.fn(),
        createNotificationError: jest.fn(),
    } as unknown as DetailVm;

    Object.entries(page.computed).forEach(([name, computed]) => {
        Object.defineProperty(vm, name, { get: () => computed.call(vm) });
    });

    Object.entries(page.methods).forEach(([name, method]) => {
        (vm as unknown as Record<string, unknown>)[name] = (...args: unknown[]) => method.apply(vm, args);
    });

    vm.loadOrderReturn = jest.fn().mockResolvedValue(undefined);

    return vm;
}

describe('src/module/sw-order-return/page/sw-order-return-detail/index', () => {
    it('reads the return with everything the page renders', () => {
        const addAssociation = jest.spyOn(Shopwell.Data.Criteria.prototype, 'addAssociation');
        const setIds = jest.spyOn(Shopwell.Data.Criteria.prototype, 'setIds');

        page.methods.criteria.call(createVm());

        expect(setIds).toHaveBeenCalledWith(['return-1']);
        expect(addAssociation.mock.calls.map((call) => call[0])).toEqual([
            'order',
            'customer',
            'reason',
            'stateMachineState',
            'lineItems.orderLineItem',
            'messages',
        ]);

        addAssociation.mockRestore();
        setIds.mockRestore();
    });

    it('keeps the first return of the search and stops loading', async () => {
        const vm = createVm();

        await page.methods.loadOrderReturn.call(vm);

        expect(vm.orderReturnRepository.search).toHaveBeenCalledTimes(1);
        expect(vm.orderReturn).toEqual(orderReturn);
        expect(vm.isLoading).toBe(false);
    });

    it('offers only the decisions the state and the role allow', () => {
        const vm = createVm();

        expect(vm.canDecide).toBe(true);
        expect(vm.availableActions).toEqual(['approve', 'decline', 'cancel']);

        vm.orderReturn = { ...orderReturn, stateMachineState: { technicalName: 'returning', name: 'Returning' } };
        expect(vm.availableActions).toEqual(['cancel', 'receive', 'refund']);

        vm.orderReturn = { ...orderReturn, stateMachineState: { technicalName: 'closed', name: 'Closed' } };
        expect(vm.availableActions).toEqual([]);

        expect(createVm({ canDecide: false }).availableActions).toEqual([]);
    });

    it('does not offer a decision without a state', () => {
        const vm = createVm({ orderReturn: { ...orderReturn, stateMachineState: null } });

        expect(vm.stateName).toBeNull();
        expect(vm.availableActions).toEqual([]);
    });

    it('reads the state, the type, the customer, the reason and the tracking of the case', () => {
        const vm = createVm({ translatedKeys: ['sw-order-return.stateLabel.requested'] });

        expect(vm.stateLabel).toBe('sw-order-return.stateLabel.requested');
        expect(vm.stateVariant).toBe('attention');
        expect(vm.typeLabel).toBe('return_refund');
        expect(vm.customerLabel).toBe('Ada Lovelace');
        expect(vm.reasonLabel).toBe('damaged');
        expect(vm.trackingLabel).toBe('SF · SF123');
        expect(vm.isRefundOnly).toBe(false);
    });

    it('falls back to the free text when the reason was deleted, and to the guest mail without a customer', () => {
        const vm = createVm({
            orderReturn: {
                ...orderReturn,
                reason: null,
                reasonText: 'the shoe is too small',
                customer: null,
                guestEmail: 'guest@example.com',
            },
        });

        expect(vm.reasonLabel).toBe('the shoe is too small');
        expect(vm.customerLabel).toBe('guest@example.com');
    });

    it('renders an empty case as dashes', () => {
        const vm = createVm({ orderReturn: null });

        expect(vm.customerLabel).toBe('–');
        expect(vm.reasonLabel).toBe('–');
        expect(vm.trackingLabel).toBe('–');
        expect(vm.typeLabel).toBe('–');
        expect(vm.generalFacts).toEqual([]);
        expect(vm.isRefundOnly).toBe(false);
    });

    it('renders the facts of the case in the order an operator asks them', () => {
        const facts = createVm().generalFacts;

        expect(facts.map((fact) => fact.label)).toEqual([
            'sw-order-return.detail.labelOrderNumber',
            'sw-order-return.detail.labelCustomer',
            'sw-order-return.detail.labelType',
            'sw-order-return.detail.labelRefundAmount',
            'sw-order-return.detail.labelReason',
            'sw-order-return.detail.labelCreatedAt',
            'sw-order-return.detail.labelDeadline',
            'sw-order-return.detail.labelTracking',
        ]);

        expect(facts[0].value).toBe('10001');
        expect(facts[4].value).toBe('damaged');
        expect(facts[6].value).toBe('–');
        expect(facts[7].value).toBe('SF · SF123');
    });

    it('names the position through the order, and a message through its role', () => {
        const vm = createVm();

        expect(vm.lineItemLabel({ id: 'line-1', orderLineItemId: 'order-line-1' })).toBe('order-line-1');
        expect(vm.lineItemLabel({ id: 'line-1', orderLineItemId: 'order-line-1', orderLineItem: { label: 'Shoe' } })).toBe(
            'Shoe',
        );

        expect(vm.messageAuthor({ id: 'message-1', authorRole: 'agent', content: '' })).toBe('agent');
    });

    it('reads the server detail of a refused decision', () => {
        const vm = createVm();

        expect(vm.errorMessage({ response: { data: { errors: [{ detail: 'the state moved' }] } } })).toBe('the state moved');
        expect(vm.errorMessage(new Error('offline'))).toBe('sw-order-return.notification.actionError');
    });

    it('runs a decision, re-reads the case and reports the success', async () => {
        const vm = createVm();
        const request = jest.fn().mockResolvedValue({});

        await expect(vm.runAction(request, 'sw-order-return.notification.receiveSuccess')).resolves.toBe(true);

        expect(request).toHaveBeenCalledTimes(1);
        expect(vm.loadOrderReturn).toHaveBeenCalledTimes(1);
        expect(vm.createNotificationSuccess).toHaveBeenCalledWith({
            message: 'sw-order-return.notification.receiveSuccess',
        });
        expect(vm.isSubmitting).toBe(false);
    });

    it('reports a refused decision and stops submitting', async () => {
        const vm = createVm();
        const request = jest.fn().mockRejectedValue({ response: { data: { errors: [{ detail: 'the state moved' }] } } });

        await expect(vm.runAction(request, 'snippet')).resolves.toBe(false);

        expect(vm.createNotificationError).toHaveBeenCalledWith({ message: 'the state moved' });
        expect(vm.createNotificationSuccess).not.toHaveBeenCalled();
        expect(vm.isSubmitting).toBe(false);
    });

    it('does not run a second decision while one is running', async () => {
        const vm = createVm();
        vm.isSubmitting = true;
        const request = jest.fn();

        await expect(vm.runAction(request, 'snippet')).resolves.toBe(false);
        expect(request).not.toHaveBeenCalled();
    });

    it('hands every decision to the service with the id of the case', () => {
        const approve = createVm();
        approve.onApprove();
        expect(approve.orderReturnApiService.approve).toHaveBeenCalledWith('return-1');

        const cancel = createVm();
        cancel.onCancel();
        expect(cancel.orderReturnApiService.cancel).toHaveBeenCalledWith('return-1', null);

        const receive = createVm();
        receive.onReceive();
        expect(receive.orderReturnApiService.receive).toHaveBeenCalledWith('return-1');

        const refund = createVm();
        refund.onRefund();
        expect(refund.orderReturnApiService.refund).toHaveBeenCalledWith('return-1');
    });

    it('runs one decision at a time', async () => {
        const vm = createVm();

        vm.onApprove();
        expect(vm.isSubmitting).toBe(true);

        vm.onReceive();
        expect(vm.orderReturnApiService.receive).not.toHaveBeenCalled();

        await Promise.resolve();
        await Promise.resolve();
        expect(vm.isSubmitting).toBe(false);
    });

    it('closes the refusal modal when the reason was handed in', () => {
        const vm = createVm();
        vm.showDeclineModal = true;

        vm.onDecline('the goods were damaged');

        expect(vm.showDeclineModal).toBe(false);
        expect(vm.orderReturnApiService.decline).toHaveBeenCalledWith('return-1', 'the goods were damaged');
    });

    it('opens and closes the refusal modal', () => {
        const vm = createVm();

        vm.onOpenDeclineModal();
        expect(vm.showDeclineModal).toBe(true);

        vm.onCloseDeclineModal();
        expect(vm.showDeclineModal).toBe(false);
    });

    it('refuses an empty note', () => {
        const vm = createVm();
        vm.messageDraft = '   ';

        vm.onSendMessage();

        expect(vm.orderReturnApiService.addMessage).not.toHaveBeenCalled();
        expect(vm.createNotificationError).toHaveBeenCalledWith({
            message: 'sw-order-return.detail.messageEmptyError',
        });
    });

    it('writes a note and clears the draft only when it was written', async () => {
        const vm = createVm();
        vm.messageDraft = '  called the customer  ';

        vm.onSendMessage();
        await Promise.resolve();
        await Promise.resolve();
        await Promise.resolve();

        expect(vm.orderReturnApiService.addMessage).toHaveBeenCalledWith('return-1', 'called the customer');
        expect(vm.messageDraft).toBe('');
    });
});
