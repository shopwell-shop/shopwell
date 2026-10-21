/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import Criteria from 'src/core/data/criteria.data';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopwell.Service().register('filterService', () => {
    return {
        // the real service is async (src/app/service/filter.service.js), the double has to be too -
        // otherwise a missing await in the page is invisible to this spec
        mergeWithStoredFilters: (_storeKey: string, criteria: unknown) => Promise.resolve(criteria),
    };
});

/**
 * The states as the state machine migration creates them, names included: `Migration1792700010` names a
 * `created` payout 已创建 and a `succeeded` one 打款成功. Those words were written for the payment gateway
 * and are not the ones the finance pages use, which is exactly why the labels below are asserted.
 */
const transferStates = [
    { id: 'state-created', name: 'created', technicalName: 'created', translated: { name: '已创建' } },
    { id: 'state-succeeded', name: 'succeeded', technicalName: 'succeeded', translated: { name: '打款成功' } },
    { id: 'state-failed', name: 'failed', technicalName: 'failed', translated: { name: '打款失败' } },
    { id: 'state-canceled', name: 'canceled', technicalName: 'canceled', translated: { name: '已撤销' } },
];

/**
 * The words of the finance pages for the four states above. The test environment answers every key with the
 * key itself, so the labels are only observable with a translation that actually translates.
 */
const STATE_LABELS: Record<string, string> = {
    'sw-finance.transfer.stateLabel.created': '待提交',
    'sw-finance.transfer.stateLabel.succeeded': '成功',
    'sw-finance.transfer.stateLabel.failed': '失败',
    'sw-finance.transfer.stateLabel.canceled': '已取消',
};

const transfers = [
    {
        id: 'transfer-1',
        transferNo: 'T-1',
        outNo: 'O-1',
        provider: 'wechat',
        channel: 'wechat_transfer',
        amount: 500,
        currency: 'CNY',
        statusId: 'state-succeeded',
        submitAttempts: 1,
        failureCode: null,
        failureReason: null,
        metadata: null,
        createdAt: '2026-01-02T03:04:05.000Z',
        recipient: { holderName: '张三', providerAccountId: 'openid-1' },
        paymentApp: { id: 'app-1', name: '演示应用' },
        statusMachineState: { id: 'state-succeeded', technicalName: 'succeeded' },
    },
];

type TransferListVm = {
    defaultFilters: string[];
    listFilterOptions: Record<string, unknown>;
    criteria: {
        term: string;
        sortings: unknown[];
        associations: Array<{ association: string }>;
        filters: unknown[];
    };
    stateFilterOptions: Array<{ id: string; name: string }>;
    hasActiveSearchOrFilter: boolean;
    activeFilterNumber: number;
    page: number;
    total: number;
    transfers: unknown[];
    isLoading: boolean;
    term: string;
    canCreate: boolean;
    canApprove: boolean;
    transferColumns: Array<{ property: string }>;
    showCreateModal: boolean;
    transferToApprove: Record<string, unknown> | null;
    transferToReject: Record<string, unknown> | null;
    updateCriteria: (filters: unknown[]) => void;
    transferStateLabel: (stateId: string) => string;
    transferStateVariant: (stateId: string) => string;
    recipientLabel: (item: unknown) => string;
    providerLabel: (provider: string | null) => string;
    failureLabel: (item: unknown) => string;
    isDecidable: (item: unknown) => boolean;
    canDecide: (item: unknown) => boolean;
    formatAmount: (amount: number, currency: string) => string;
    formatDateOrDash: (value: string | null) => string;
    onOpenCreateModal: () => void;
    onCloseCreateModal: () => void;
    onTransferCreated: () => void;
    onApprove: (item: unknown) => void;
    onReject: (item: unknown) => void;
    onCloseApproveModal: () => void;
};

type TypedWrapper = { vm: TransferListVm } & VueWrapper;

function collection<T>(items: unknown[]): T {
    return Object.assign([...items], { total: items.length }) as unknown as T;
}

let activeWrapper: VueWrapper | null = null;

async function createWrapper(
    can: (privilege: string) => boolean = () => true,
    stateLabels: Record<string, string> = {},
    items: unknown[] = transfers,
): Promise<{
    wrapper: TypedWrapper;
    search: jest.Mock;
    stateSearch: jest.Mock;
}> {
    const search = jest.fn(() => Promise.resolve(collection<EntityCollection<'payment_transfer'>>(items)));

    const stateSearch = jest.fn(() => Promise.resolve(collection<EntityCollection<'state_machine_state'>>(transferStates)));

    const wrapper = mount(await wrapTestComponent('sw-finance-transfer-list', { sync: true }), {
        global: {
            stubs: {
                'sw-page': {
                    template: `
                        <div>
                            <slot name="search-bar"></slot>
                            <slot name="smart-bar-header"></slot>
                            <slot name="smart-bar-actions"></slot>
                            <slot name="content"></slot>
                            <slot name="sidebar"></slot>
                        </div>`,
                },
                'sw-search-bar': true,
                'sw-entity-listing': true,
                'sw-data-grid': true,
                'sw-context-button': true,
                'sw-context-menu-item': true,
                'sw-pagination': true,
                'sw-data-grid-settings': true,
                'sw-data-grid-skeleton': true,
                'sw-sidebar': true,
                'sw-sidebar-item': true,
                'sw-sidebar-filter-panel': true,
                'sw-finance-transfer-create-modal': true,
                'sw-finance-transfer-approve-modal': true,
                'sw-finance-transfer-reject-modal': true,
                'router-link': { template: '<a><slot></slot></a>' },
                'mt-button': true,
                'mt-badge': true,
                'mt-icon': true,
                'mt-tooltip': true,
                // the stub renders the button slot: the empty state carries the second entry point of
                // the create modal, and the default stub would swallow it
                'mt-empty-state': {
                    template: '<div class="mt-empty-state-stub"><slot name="button"></slot></div>',
                },
            },
            provide: {
                repositoryFactory: {
                    create: (entity: string) => {
                        return entity === 'state_machine_state' ? { search: stateSearch } : { search };
                    },
                },
                filterFactory: {
                    create: (_entity: string, filters: Record<string, unknown>) => Object.values(filters),
                },
                acl: {
                    can,
                },
                searchRankingService: {
                    getSearchFieldsByEntity: () => Promise.resolve({}),
                    buildSearchQueriesForEntity: (_searchFields: unknown, _term: string, criteria: unknown) => criteria,
                    isValidTerm: (term: string) => Boolean(term) && term.trim().length >= 1,
                },
            },
            mocks: {
                $route: {
                    name: 'sw.finance.transfer',
                    query: {},
                    meta: { $module: { icon: 'regular-money-bill' } },
                },
                $router: {
                    push: jest.fn(),
                    replace: jest.fn(),
                },
                // only a test that asserts the rendered words gets a dictionary - the rest of the spec
                // relies on the environment answering every key with the key itself
                ...(Object.keys(stateLabels).length === 0
                    ? {}
                    : {
                          $te: (key: string) => Object.prototype.hasOwnProperty.call(stateLabels, key),
                          $t: (key: string) => stateLabels[key] ?? key,
                      }),
            },
        },
    }) as unknown as TypedWrapper;

    activeWrapper = wrapper;

    await flushPromises();

    return { wrapper, search, stateSearch };
}

describe('module/sw-finance/page/sw-finance-transfer-list', () => {
    afterEach(() => {
        activeWrapper?.unmount();
        activeWrapper = null;
    });

    it('should have a default filter for every filter option', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.defaultFilters).toEqual([
            'transfer-no-filter',
            'out-no-filter',
            'channel-filter',
            'provider-filter',
            'state-filter',
        ]);

        wrapper.vm.defaultFilters.forEach((filterKey) => {
            expect(wrapper.vm.listFilterOptions).toHaveProperty(filterKey);
        });
    });

    it('should keep the search, the sorting, the recipient and the app in the criteria', async () => {
        const { wrapper } = await createWrapper();

        wrapper.vm.term = 'T-1';

        const criteria = wrapper.vm.criteria;

        expect(criteria.term).toBe('T-1');
        expect(criteria.sortings).toEqual([{ field: 'createdAt', order: 'DESC', naturalSorting: false }]);

        const associations = criteria.associations.map((association) => association.association);

        expect(associations).toContain('recipient');
        expect(associations).toContain('paymentApp');
        expect(associations).toContain('statusMachineState');
    });

    it('should load the transfers into the listing state', async () => {
        const { wrapper, search, stateSearch } = await createWrapper();

        expect(search).toHaveBeenCalled();
        expect(wrapper.vm.total).toBe(1);
        expect(wrapper.vm.transfers).toHaveLength(1);
        expect(wrapper.vm.isLoading).toBe(false);

        // the states are read once, not with every list request
        expect(stateSearch).toHaveBeenCalledTimes(1);
    });

    /**
     * The filter dropdown lists the same names as the column, so it has to be translated the same way -
     * otherwise the operator filters by 打款成功 and the column answers 成功.
     */
    it('should offer the states of the transfer machine as filter options', async () => {
        const { wrapper } = await createWrapper(undefined, STATE_LABELS);

        expect(wrapper.vm.stateFilterOptions).toEqual([
            { id: 'state-created', name: '待提交' },
            { id: 'state-succeeded', name: '成功' },
            { id: 'state-failed', name: '失败' },
            { id: 'state-canceled', name: '已取消' },
        ]);

        // the names the migration gives the same states are not the words of these pages
        expect(JSON.stringify(wrapper.vm.stateFilterOptions)).not.toContain('打款');
        expect(wrapper.vm.hasActiveSearchOrFilter).toBe(false);

        wrapper.vm.activeFilterNumber = 1;
        expect(wrapper.vm.hasActiveSearchOrFilter).toBe(true);
    });

    it('should apply the filters the filter panel emits and reload the list', async () => {
        const { wrapper, search } = await createWrapper();

        search.mockClear();

        const filters = [Criteria.equalsAny('channel', ['wechat_transfer'])];
        wrapper.vm.updateCriteria(filters);
        await flushPromises();

        expect(wrapper.vm.page).toBe(1);
        expect(wrapper.vm.criteria.filters).toEqual(filters);
        expect(search).toHaveBeenCalled();
    });

    /**
     * The prd orders the columns, and the order is the reading flow of an approver: what is it, who gets
     * it, how much, in which state, why did it fail, when.
     */
    it('should expose the columns of the prd in its order', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.transferColumns.map((column) => column.property)).toEqual([
            'transferNo',
            'outNo',
            'recipientId',
            'provider',
            'channel',
            'amount',
            'statusId',
            'failureCode',
            'createdAt',
        ]);
    });

    it('should render the state, the recipient and the amount of a transfer', async () => {
        const { wrapper } = await createWrapper(undefined, STATE_LABELS);

        expect(wrapper.vm.transferStateLabel('state-succeeded')).toBe('成功');
        expect(wrapper.vm.transferStateVariant('state-succeeded')).toBe('positive');
        expect(wrapper.vm.transferStateVariant('state-failed')).toBe('critical');
        expect(wrapper.vm.recipientLabel(transfers[0])).toBe('张三');
        expect(wrapper.vm.recipientLabel({ recipient: { providerAccountId: 'openid-1' } })).toBe('openid-1');
        expect(wrapper.vm.recipientLabel({})).toBe('-');
        expect(wrapper.vm.formatAmount(500, 'CNY')).toBe(Shopwell.Utils.format.currency(5, 'CNY', 2));
    });

    /**
     * The snippet of this module wins over the name the state machine carries. The migration calls a
     * `created` payout 已创建 and a `succeeded` one 打款成功 - words of the payment gateway, and the first of
     * them tells an approver the opposite of what the row is waiting for.
     */
    it('should name a state with the words of the finance pages instead of the ones of the migration', async () => {
        const { wrapper } = await createWrapper(undefined, STATE_LABELS);

        expect(wrapper.vm.transferStateLabel('state-created')).toBe('待提交');
        expect(wrapper.vm.transferStateLabel('state-canceled')).toBe('已取消');
        expect(wrapper.vm.transferStateLabel('state-succeeded')).not.toContain('打款');
        expect(wrapper.vm.transferStateLabel('state-created')).not.toBe('已创建');
    });

    /**
     * A state this administration has no snippet for is not a reason to show a key - the name of the machine
     * is the fallback, even though it is a word of the payment gateway.
     */
    it('should fall back to the name of the machine for a state without a snippet', async () => {
        const { wrapper } = await createWrapper(undefined, {
            'sw-finance.transfer.stateLabel.created': '待提交',
        });

        expect(wrapper.vm.transferStateLabel('state-created')).toBe('待提交');
        expect(wrapper.vm.transferStateLabel('state-succeeded')).toBe('打款成功');
    });

    it('should translate an interface name and fall back to its technical name', async () => {
        const { wrapper } = await createWrapper();

        // the environment answers every `$te` with true, so the translation is the key itself here
        expect(wrapper.vm.providerLabel('wechat')).toBe('sw-settings-payment-provider.provider.wechat');
        expect(wrapper.vm.providerLabel(null)).toBe('-');
    });

    /**
     * The failure column carries three stories: a rejection (an operator decision), a channel failure and a
     * bare code. They must never be mixed up, because only the second one came from the provider.
     */
    it('should tell a rejection, a channel failure and a bare code apart', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.failureLabel({ metadata: { rejectReason: '账号有误' } })).toBe(
            'sw-finance.transfer.actions.cancel: 账号有误',
        );
        expect(wrapper.vm.failureLabel({ failureReason: '余额不足', failureCode: 'NOT_ENOUGH' })).toBe('余额不足');
        expect(wrapper.vm.failureLabel({ failureCode: 'NOT_ENOUGH' })).toBe('NOT_ENOUGH');
        expect(wrapper.vm.failureLabel({})).toBe('');
    });

    it('should render a dash instead of an invalid date', async () => {
        const { wrapper } = await createWrapper();
        const dateFilter = Shopwell.Filter.getByName('date') as (value: string) => string;

        expect(wrapper.vm.formatDateOrDash(null)).toBe('–');
        expect(wrapper.vm.formatDateOrDash('2026-01-02T03:04:05.000Z')).toBe(dateFilter('2026-01-02T03:04:05.000Z'));
    });

    /**
     * The action matrix of the prd: an action needs the `created` state *and* the approver privilege.
     */
    it('should decide the visibility of the actions by state and privilege', async () => {
        const { wrapper } = await createWrapper();

        const created = { statusMachineState: { technicalName: 'created' } };
        const submitted = { statusMachineState: { technicalName: 'submitted' } };

        expect(wrapper.vm.isDecidable(created)).toBe(true);
        expect(wrapper.vm.isDecidable(submitted)).toBe(false);
        expect(wrapper.vm.canDecide(created)).toBe(true);
        expect(wrapper.vm.canDecide(submitted)).toBe(false);
    });

    it('should not let a viewer decide a created transfer', async () => {
        const { wrapper } = await createWrapper((privilege) => privilege !== 'payment_transfer.approver');

        const created = { statusMachineState: { technicalName: 'created' } };

        expect(wrapper.vm.canApprove).toBe(false);
        expect(wrapper.vm.canDecide(created)).toBe(false);

        wrapper.vm.onApprove(created);
        wrapper.vm.onReject(created);

        // a click can not open a modal the privilege does not allow - the endpoint would refuse it anyway
        expect(wrapper.vm.transferToApprove).toBeNull();
        expect(wrapper.vm.transferToReject).toBeNull();
    });

    it('should only offer the create button to a creator', async () => {
        const creator = await createWrapper();
        expect(creator.wrapper.vm.canCreate).toBe(true);

        activeWrapper?.unmount();

        const viewer = await createWrapper((privilege) => privilege !== 'payment_transfer.creator');
        expect(viewer.wrapper.vm.canCreate).toBe(false);
    });

    it('should open the create and the decision modals', async () => {
        const { wrapper, search } = await createWrapper();

        wrapper.vm.onOpenCreateModal();
        expect(wrapper.vm.showCreateModal).toBe(true);

        wrapper.vm.onCloseCreateModal();
        expect(wrapper.vm.showCreateModal).toBe(false);

        search.mockClear();
        wrapper.vm.onTransferCreated();
        await flushPromises();
        expect(wrapper.vm.showCreateModal).toBe(false);
        expect(search).toHaveBeenCalled();

        const created = { statusMachineState: { technicalName: 'created' } };
        wrapper.vm.onApprove(created);
        expect(wrapper.vm.transferToApprove).toEqual(created);

        wrapper.vm.onCloseApproveModal();
        expect(wrapper.vm.transferToApprove).toBeNull();

        wrapper.vm.onReject(created);
        expect(wrapper.vm.transferToReject).toEqual(created);
    });

    /**
     * Regression: the modals used to sit directly under `<sw-page>` - in the default slot, which
     * `sw-page` never renders (it only has named slots). The button set its flag, nothing appeared.
     * Both entry points of the create modal must mount the modal for real.
     */
    it('should mount the create modal when the smart bar button is clicked', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.find('sw-finance-transfer-create-modal-stub').exists()).toBe(false);

        // the smart bar carries the only button while the list has rows - the empty state button only
        // renders for an empty list
        const smartBarButtons = wrapper.findAll('mt-button-stub');

        expect(smartBarButtons.length).toBeGreaterThan(0);
        await smartBarButtons[0].trigger('click');

        expect(wrapper.vm.showCreateModal).toBe(true);
        expect(wrapper.find('sw-finance-transfer-create-modal-stub').exists()).toBe(true);
    });

    it('should mount the create modal when the button of the empty state is clicked', async () => {
        const { wrapper } = await createWrapper(() => true, {}, []);

        expect(wrapper.find('sw-finance-transfer-create-modal-stub').exists()).toBe(false);

        const emptyStateButton = wrapper.find('.sw-finance-transfer-list__empty-state mt-button-stub');

        expect(emptyStateButton.exists()).toBe(true);
        await emptyStateButton.trigger('click');

        expect(wrapper.vm.showCreateModal).toBe(true);
        expect(wrapper.find('sw-finance-transfer-create-modal-stub').exists()).toBe(true);
    });
});
