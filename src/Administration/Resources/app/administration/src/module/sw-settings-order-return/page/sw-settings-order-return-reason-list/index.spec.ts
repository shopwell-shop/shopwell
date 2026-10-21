/**
 * @sw-package after-sales
 */

import SwSettingsOrderReturnReasonList from './index';

type Reason = {
    id: string;
    label: string | null;
    position: number;
    active: boolean;
};

type RepositoryMock = {
    create: jest.Mock;
    search: jest.Mock;
};

type ReasonListVm = {
    reasons: unknown;
    total: number;
    isLoading: boolean;
    page: number;
    limit: number;
    term: string | null;
    sortBy: string;
    sortDirection: 'ASC' | 'DESC';
    naturalSorting: boolean;
    showModal: boolean;
    reasonToEdit: Reason | null;
    acl: { can: jest.Mock };
    repositoryFactory: { create: jest.Mock };
    reasonRepository: RepositoryMock;
    canEdit: boolean;
    columns: Array<Record<string, unknown>>;
    criteria: unknown;
    getList: jest.Mock;
    onRefresh: () => void;
    onCreate: () => void;
    onEdit: (reason: Reason) => void;
    onCloseModal: () => void;
    onSaved: () => void;
};

type ReasonList = {
    computed: Record<string, (this: ReasonListVm) => unknown>;
    methods: Record<string, (this: ReasonListVm, ...args: unknown[]) => unknown>;
};

const page = SwSettingsOrderReturnReasonList as unknown as ReasonList;

const reason: Reason = { id: 'reason-1', label: 'damaged', position: 10, active: true };

function createVm(
    canEdit = true,
    search: jest.Mock = jest.fn().mockResolvedValue({ total: 1, length: 1 }),
    mockGetList = true,
): ReasonListVm {
    const repository: RepositoryMock = {
        create: jest.fn(() => ({})),
        search,
    };

    const vm = {
        reasons: null,
        total: 0,
        isLoading: false,
        page: 1,
        limit: 25,
        term: null,
        sortBy: 'position',
        sortDirection: 'ASC',
        naturalSorting: false,
        showModal: false,
        reasonToEdit: null,
        acl: { can: jest.fn(() => canEdit) },
        repositoryFactory: { create: jest.fn(() => repository) },
        reasonRepository: repository,
    } as unknown as ReasonListVm;

    Object.entries(page.computed).forEach(([name, computed]) => {
        Object.defineProperty(vm, name, { get: () => computed.call(vm) });
    });

    Object.entries(page.methods).forEach(([name, method]) => {
        (vm as unknown as Record<string, unknown>)[name] = (...args: unknown[]) => method.apply(vm, args);
    });

    if (mockGetList) {
        vm.getList = jest.fn().mockResolvedValue(undefined);
    }

    return vm;
}

describe('src/module/sw-settings-order-return/page/sw-settings-order-return-reason-list/index', () => {
    it('reads the label, the position and the switch of a reason in that order', () => {
        const columns = createVm().columns;

        expect(columns.map((column) => column.property)).toEqual(['position', 'label', 'active']);
        expect(columns[1].primary).toBe(true);
    });

    it('only lets an editor maintain the reasons', () => {
        expect(createVm(true).canEdit).toBe(true);
        expect(createVm(false).canEdit).toBe(false);
    });

    it('reads the reasons sorted by position', async () => {
        const result = { total: 1, length: 1, first: () => reason };
        const vm = createVm(true, jest.fn().mockResolvedValue(result), false);

        await page.methods.getList.call(vm);

        expect(vm.reasonRepository.search).toHaveBeenCalledTimes(1);
        expect(vm.reasonRepository.search).toHaveBeenCalledWith(vm.criteria);
        expect(vm.total).toBe(1);
        expect(vm.reasons).toBe(result);
        expect(vm.isLoading).toBe(false);
    });

    it('opens the modal for a new reason', () => {
        const vm = createVm();
        vm.reasonToEdit = reason;

        vm.onCreate();

        expect(vm.reasonToEdit).toBeNull();
        expect(vm.showModal).toBe(true);
    });

    it('opens the modal for the reason that is edited', () => {
        const vm = createVm();

        vm.onEdit(reason);

        expect(vm.reasonToEdit).toEqual(reason);
        expect(vm.showModal).toBe(true);
    });

    it('closes the modal and forgets the reason', () => {
        const vm = createVm();
        vm.showModal = true;
        vm.reasonToEdit = reason;

        vm.onCloseModal();

        expect(vm.showModal).toBe(false);
        expect(vm.reasonToEdit).toBeNull();
    });

    it('re-reads the list after a reason was saved', () => {
        const vm = createVm();
        vm.showModal = true;
        vm.reasonToEdit = reason;

        vm.onSaved();

        expect(vm.showModal).toBe(false);
        expect(vm.reasonToEdit).toBeNull();
        expect(vm.getList).toHaveBeenCalledTimes(1);
    });

    it('refreshes the list', () => {
        const vm = createVm();

        vm.onRefresh();

        expect(vm.getList).toHaveBeenCalledTimes(1);
    });
});
