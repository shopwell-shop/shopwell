/**
 * @sw-package after-sales
 */

import { flushPromises } from '@vue/test-utils';
import SwOrderReturnList from './index';

type State = {
    id: string;
    technicalName: string;
    name: string;
    translated?: { name: string };
};

type Customer = {
    firstName?: string | null;
    lastName?: string | null;
    email?: string | null;
};

type OrderReturn = {
    id: string;
    returnNumber: string;
    type: string;
    stateId: string | null;
    refundAmount: number | null;
    currency: string | null;
    deadlineAt: string | null;
    customer?: Customer | null;
    guestEmail?: string | null;
};

type RepositoryMock = {
    create: jest.Mock;
    search: jest.Mock;
};

type OrderReturnListVm = {
    orderReturns: unknown;
    total: number;
    isLoading: boolean;
    page: number;
    limit: number;
    term: string | null;
    sortBy: string;
    sortDirection: 'ASC' | 'DESC';
    naturalSorting: boolean;
    states: State[] | null;
    storeKey: string;
    activeFilterNumber: number;
    acl: { can: jest.Mock };
    repositoryFactory: { create: jest.Mock };
    filterFactory: { create: jest.Mock };
    orderReturnRepository: RepositoryMock;
    dateFilter: (value: string | Date) => string;
    criteria: { filters: unknown[] };
    isValidTerm: jest.Mock;
    $t: (key: string) => string;
    $te: (key: string) => boolean;
    orderReturnColumns: Array<Record<string, unknown>>;
    listFilterOptions: Record<string, Record<string, unknown>>;
    stateFilterOptions: Array<{ id: string; name: string }>;
    hasActiveSearchOrFilter: boolean;
    getList: jest.Mock;
    updateCriteria: (filters: unknown[]) => void;
    onRefresh: () => void;
    stateLabelOf: (technicalName: string | null | undefined, fallback: string) => string;
    stateLabel: (stateId: string | null) => string;
    stateVariant: (stateId: string | null) => string;
    stateById: (stateId: string | null) => State | undefined;
    typeLabel: (type: string | null | undefined) => string;
    customerLabel: (item: OrderReturn) => string;
    formatAmount: (amount: number | null, currency: string | null) => string;
    formatDateOrDash: (value: string | null) => string;
    deadlineLabel: (item: OrderReturn) => string;
    isOverdue: (item: OrderReturn) => boolean;
};

type OrderReturnList = {
    computed: Record<string, (this: OrderReturnListVm) => unknown>;
    methods: Record<string, (this: OrderReturnListVm, ...args: unknown[]) => unknown>;
};

const page = SwOrderReturnList as unknown as OrderReturnList;

const requested: State = { id: 'state-1', technicalName: 'requested', name: 'Requested' };
const returning: State = { id: 'state-2', technicalName: 'returning', name: 'Returning' };

const orderReturn: OrderReturn = {
    id: 'return-1',
    returnNumber: 'R-1001',
    type: 'return_refund',
    stateId: 'state-1',
    refundAmount: 19900,
    currency: 'CNY',
    deadlineAt: null,
    customer: { firstName: 'Ada', lastName: 'Lovelace', email: 'ada@example.com' },
};

function createVm(options: { translatedKeys?: string[]; states?: State[]; isValidTerm?: boolean } = {}): OrderReturnListVm {
    const repository: RepositoryMock = {
        create: jest.fn(() => ({})),
        search: jest.fn().mockResolvedValue({ total: 1, length: 1 }),
    };

    const vm = {
        orderReturns: null,
        total: 0,
        isLoading: false,
        page: 1,
        limit: 25,
        term: null,
        sortBy: 'createdAt',
        sortDirection: 'DESC',
        naturalSorting: false,
        states: options.states ?? [requested, returning],
        storeKey: 'grid.filter.order_return',
        activeFilterNumber: 0,
        acl: { can: jest.fn(() => true) },
        repositoryFactory: { create: jest.fn(() => repository) },
        filterFactory: { create: jest.fn((entity: string, filters: unknown) => ({ entity, filters })) },
        orderReturnRepository: repository,
        isValidTerm: jest.fn(() => options.isValidTerm ?? false),
        $t: (key: string) => key,
        $te: (key: string) => (options.translatedKeys ?? []).includes(key),
    } as unknown as OrderReturnListVm;

    Object.entries(page.computed).forEach(([name, computed]) => {
        Object.defineProperty(vm, name, { get: () => computed.call(vm), configurable: true });
    });

    Object.entries(page.methods).forEach(([name, method]) => {
        (vm as unknown as Record<string, unknown>)[name] = (...args: unknown[]) => method.apply(vm, args);
    });

    // the filter of the administration is registered by the application, so the page gets a plain one
    Object.defineProperty(vm, 'dateFilter', {
        value: (value: string | Date) => `date:${String(value)}`,
        configurable: true,
    });
    vm.getList = jest.fn().mockResolvedValue(undefined);

    return vm;
}

describe('src/module/sw-order-return/page/sw-order-return-list/index', () => {
    it('reads the queue in the order an operator works it', () => {
        const columns = createVm().orderReturnColumns;

        expect(columns.map((column) => column.property)).toEqual([
            'returnNumber',
            'order.orderNumber',
            'customerId',
            'type',
            'stateId',
            'refundAmount',
            'deadlineAt',
            'createdAt',
        ]);

        expect(columns[0].routerLink).toBe('sw.order.return.detail');
        expect(columns[5].align).toBe('right');
    });

    it('offers the number, the order number and the state as filters', () => {
        const options = createVm().listFilterOptions;

        expect(Object.keys(options)).toEqual(['return-no-filter', 'order-no-filter', 'state-filter']);
        expect(options['return-no-filter'].criteriaFilterType).toBe('equalsAny');
        expect(options['order-no-filter'].property).toBe('order.orderNumber');
        expect(options['state-filter'].property).toBe('stateId');
    });

    it('translates the states of the filter through the snippet of the module', () => {
        const vm = createVm({ translatedKeys: ['sw-order-return.stateLabel.requested'] });

        expect(vm.stateFilterOptions).toEqual([
            { id: 'state-1', name: 'sw-order-return.stateLabel.requested' },
            // no snippet for `returning`, so the name of the state machine is the fallback
            { id: 'state-2', name: 'Returning' },
        ]);
    });

    it('falls back to the name of the machine when no technical name exists', () => {
        const vm = createVm();

        expect(vm.stateLabelOf(null, 'Requested')).toBe('Requested');
        expect(vm.stateLabelOf('requested', 'Requested')).toBe('Requested');
    });

    it('finds the state of a row', () => {
        const vm = createVm();

        expect(vm.stateById('state-2')).toEqual(returning);
        expect(vm.stateById(null)).toBeUndefined();
        expect(vm.stateById('unknown')).toBeUndefined();
    });

    it('renders a row without a state or a known type with a dash', () => {
        const vm = createVm();

        expect(vm.stateLabel(null)).toBe('–');
        expect(vm.stateVariant(null)).toBe('neutral');
        expect(vm.typeLabel(null)).toBe('–');
        expect(vm.typeLabel('return_refund')).toBe('return_refund');
    });

    it('names the holder of the case: the customer, the mail of a guest, or nothing', () => {
        const vm = createVm();

        expect(vm.customerLabel(orderReturn)).toBe('Ada Lovelace');
        expect(
            vm.customerLabel({ ...orderReturn, customer: { firstName: null, lastName: '', email: 'ada@example.com' } }),
        ).toBe('ada@example.com');
        expect(vm.customerLabel({ ...orderReturn, customer: null, guestEmail: 'guest@example.com' })).toBe(
            'guest@example.com',
        );
        expect(vm.customerLabel({ ...orderReturn, customer: null, guestEmail: null })).toBe('–');
    });

    it('renders a passed deadline as overdue instead of a date', () => {
        const vm = createVm();

        expect(vm.deadlineLabel(orderReturn)).toBe('–');
        expect(vm.deadlineLabel({ ...orderReturn, deadlineAt: '2999-01-01T00:00:00Z' })).toBe('date:2999-01-01T00:00:00Z');

        const overdue = { ...orderReturn, deadlineAt: '2000-01-01T00:00:00Z' };
        expect(vm.isOverdue(overdue)).toBe(true);
        expect(vm.deadlineLabel(overdue)).toBe('sw-order-return.list.textOverdue');
        expect(vm.isOverdue(orderReturn)).toBe(false);
    });

    it('renders a missing date as a dash', () => {
        const vm = createVm();

        expect(vm.formatDateOrDash(null)).toBe('–');
        expect(vm.formatDateOrDash('2026-10-21T10:00:00Z')).toBe('date:2026-10-21T10:00:00Z');
    });

    it('knows whether the operator filtered or searched', () => {
        const vm = createVm({ isValidTerm: false });

        expect(vm.hasActiveSearchOrFilter).toBe(false);

        vm.term = 'R-1001';
        vm.isValidTerm = jest.fn(() => true);
        expect(vm.hasActiveSearchOrFilter).toBe(true);

        vm.term = null;
        vm.activeFilterNumber = 2;
        expect(vm.hasActiveSearchOrFilter).toBe(true);
    });

    it('merges the stored filters before it searches', async () => {
        const vm = createVm();
        const merged = { filters: [{ field: 'stateId' }] };
        const filterService = { mergeWithStoredFilters: jest.fn().mockResolvedValue(merged) };
        const serviceSpy = jest
            .spyOn(Shopwell, 'Service')
            .mockImplementation((() => filterService) as unknown as typeof Shopwell.Service);

        await page.methods.getList.call(vm);

        expect(filterService.mergeWithStoredFilters).toHaveBeenCalledWith('grid.filter.order_return', vm.criteria);
        expect(vm.orderReturnRepository.search).toHaveBeenCalledWith(merged);
        expect(vm.total).toBe(1);
        expect(vm.activeFilterNumber).toBe(1);
        expect(vm.isLoading).toBe(false);

        serviceSpy.mockRestore();
    });

    it('jumps back to the first page when the filters change', async () => {
        const vm = createVm();
        vm.page = 3;

        vm.updateCriteria([{ field: 'stateId' }]);
        await flushPromises();

        expect(vm.page).toBe(1);
        expect(vm.getList).toHaveBeenCalledTimes(1);
    });

    it('refreshes the queue', () => {
        const vm = createVm();

        vm.onRefresh();

        expect(vm.getList).toHaveBeenCalledTimes(1);
    });
});
