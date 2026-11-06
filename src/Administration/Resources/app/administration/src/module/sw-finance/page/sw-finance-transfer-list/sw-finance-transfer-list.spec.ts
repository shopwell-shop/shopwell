/**
 * @sw-package framework
 */
import { shallowMount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import Criteria from 'src/core/data/criteria.data';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations -- Keep the service double asynchronous like the production filter service.
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

function collection<T>(items: unknown[]): T {
    return Object.assign([...items], { total: items.length }) as unknown as T;
}

let activeWrapper: VueWrapper | null = null;

async function createWrapper(
    can: (privilege: string) => boolean = () => true,
    stateLabels: Record<string, string> = {},
    items: unknown[] = transfers,
): Promise<{
    wrapper: VueWrapper;
    search: jest.Mock<Promise<EntityCollection<'payment_transfer'>>, [InstanceType<typeof Criteria>]>;
    stateSearch: jest.Mock;
}> {
    const search = jest.fn<Promise<EntityCollection<'payment_transfer'>>, [InstanceType<typeof Criteria>]>(() =>
        Promise.resolve(collection<EntityCollection<'payment_transfer'>>(items)),
    );

    const stateSearch = jest.fn(() => Promise.resolve(collection<EntityCollection<'state_machine_state'>>(transferStates)));

    const wrapper = shallowMount(await wrapTestComponent('sw-finance-transfer-list', { sync: true }), {
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
                'sw-search-bar': { template: '<input class="search" @input="$emit(\'search\', $event.target.value)">' },
                'sw-entity-listing': {
                    props: ['dataSource', 'columns', 'isLoading'],
                    template: `<div><table><thead><tr><th v-for="column in columns" :key="column.property" :data-column="column.property">{{ column.label }}</th></tr></thead>
                    <tbody><tr v-for="item in dataSource" :key="item.id" :data-row="item.id">
                    <td v-for="column in columns" :key="column.property" :data-cell="column.property"><slot :name="'column-' + column.property" :item="item">{{ item[column.property] }}</slot></td>
                    <td><slot name="more-actions" :item="item"></slot></td></tr></tbody></table>
                    <button class="next-page" @click="$emit('page-change', { page: 2, limit: 25 })">Next page</button></div>`,
                },
                'sw-data-grid': true,
                'sw-context-button': true,
                'sw-context-menu-item': { template: '<button><slot></slot></button>' },
                'sw-pagination': true,
                'sw-data-grid-settings': true,
                'sw-data-grid-skeleton': true,
                'sw-sidebar': { template: '<aside><slot></slot></aside>' },
                'sw-sidebar-item': { template: '<button class="refresh">Refresh</button>' },
                'sw-sidebar-filter-panel': {
                    props: ['filters', 'defaults'],
                    template: `<section class="filters"><label v-for="filter in filters" :key="filter.property" :data-filter="filter.property">{{ filter.label }}
                    <select v-if="filter.options" @change="$emit('criteria-changed', [{ type: 'equalsAny', field: filter.property, value: $event.target.value }])"><option v-for="option in filter.options" :key="option.id" :value="option.id">{{ option.name }}</option></select>
                    <input v-else @change="$emit('criteria-changed', [{ type: 'equalsAny', field: filter.property, value: $event.target.value }])"></label></section>`,
                },
                'sw-finance-transfer-create-modal': {
                    template:
                        '<section class="create-modal"><button class="close" @click="$emit(\'modal-close\')">Close</button><button class="decide" @click="$emit(\'transfer-created\')">Decide</button></section>',
                },
                'sw-finance-transfer-approve-modal': {
                    template:
                        '<section class="approve-modal"><button class="close" @click="$emit(\'modal-close\')">Close</button><button class="decide" @click="$emit(\'transfer-decided\')">Decide</button></section>',
                },
                'sw-finance-transfer-reject-modal': {
                    template:
                        '<section class="reject-modal"><button class="close" @click="$emit(\'modal-close\')">Close</button><button class="decide" @click="$emit(\'transfer-decided\')">Decide</button></section>',
                },
                'router-link': { template: '<a><slot></slot></a>' },
                'mt-button': { template: '<button class="create-button"><slot></slot></button>' },
                'mt-badge': {
                    props: ['variant'],
                    template: '<span class="state" :data-variant="variant"><slot></slot></span>',
                },
                'mt-icon': true,
                'mt-tooltip': { template: '<span><slot></slot></span>' },
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
    });

    activeWrapper = wrapper;

    await flushPromises();

    return { wrapper, search, stateSearch };
}

function cell(wrapper: VueWrapper, column: string) {
    return wrapper.get(`[data-cell="${column}"]`);
}

const createdTransfer = { ...transfers[0], statusId: 'state-created', statusMachineState: { technicalName: 'created' } };

describe('module/sw-finance/page/sw-finance-transfer-list', () => {
    afterEach(() => {
        activeWrapper?.unmount();
        activeWrapper = null;
    });

    it('should offer every default filter in the sidebar', async () => {
        const { wrapper } = await createWrapper();
        expect(wrapper.findAll('[data-filter]').map((filter) => filter.attributes('data-filter'))).toEqual([
            'transferNo',
            'outNo',
            'channel',
            'provider',
            'statusId',
        ]);
    });

    it('should request search, ordering and recipient/app/status associations', async () => {
        const { wrapper, search } = await createWrapper();
        search.mockClear();
        await wrapper.get('.search').setValue('T-1');
        await wrapper.get('.refresh').trigger('click');
        await flushPromises();
        const criteria = search.mock.calls[0][0];
        expect(criteria.term).toBe('T-1');
        expect(criteria.sortings).toEqual([{ field: 'createdAt', order: 'DESC', naturalSorting: false }]);
        expect(criteria.associations.map((association) => association.association)).toEqual([
            'recipient',
            'paymentApp',
            'statusMachineState',
        ]);
    });

    it('should load and render the rows and read states only once', async () => {
        const { wrapper, search, stateSearch } = await createWrapper();
        expect(search).toHaveBeenCalled();
        expect(wrapper.get('.sw-page__smart-bar-amount').text()).toBe('(1)');
        expect(wrapper.findAll('tbody tr')).toHaveLength(1);
        expect(stateSearch).toHaveBeenCalledTimes(1);
    });

    it('should offer finance vocabulary in the state filter', async () => {
        const { wrapper } = await createWrapper(undefined, STATE_LABELS);
        expect(wrapper.findAll('[data-filter="statusId"] option').map((option) => option.text())).toEqual([
            '待提交',
            '成功',
            '失败',
            '已取消',
        ]);
        expect(wrapper.get('[data-filter="statusId"]').text()).not.toContain('打款');
    });

    it('should apply emitted filters, reset pagination and reload the list', async () => {
        const { wrapper, search } = await createWrapper();
        await wrapper.get('.next-page').trigger('click');
        await flushPromises();
        search.mockClear();
        await wrapper.get('[data-filter="channel"] input').setValue('wechat_transfer');
        await wrapper.get('[data-filter="channel"] input').trigger('change');
        await flushPromises();
        const criteria = search.mock.calls[0][0];
        expect(criteria.page).toBe(1);
        expect(criteria.filters).toEqual([Criteria.equalsAny('channel', ['wechat_transfer'])]);
    });

    it('should render columns in the approver reading order', async () => {
        const { wrapper } = await createWrapper();
        expect(wrapper.findAll('th').map((header) => header.attributes('data-column'))).toEqual([
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

    it('should render the state, recipient and minor-unit amount', async () => {
        const { wrapper } = await createWrapper(undefined, STATE_LABELS);
        expect(cell(wrapper, 'statusId').text()).toBe('成功');
        expect(cell(wrapper, 'statusId').get('.state').attributes('data-variant')).toBe('positive');
        expect(cell(wrapper, 'recipientId').text()).toBe('张三');
        expect(cell(wrapper, 'amount').text()).toBe(Shopwell.Utils.format.currency(5, 'CNY', 2));
    });

    it.each([
        ['state-created', '待提交', 'neutral'],
        ['state-canceled', '已取消', 'neutral'],
        ['state-failed', '失败', 'critical'],
    ])('should display finance state %s instead of gateway vocabulary', async (statusId, label, variant) => {
        const { wrapper } = await createWrapper(undefined, STATE_LABELS, [{ ...transfers[0], statusId }]);
        expect(cell(wrapper, 'statusId').text()).toBe(label);
        expect(cell(wrapper, 'statusId').get('.state').attributes('data-variant')).toBe(variant);
    });

    it('should use the machine translation when a finance snippet is absent', async () => {
        const { wrapper } = await createWrapper(undefined, { 'sw-finance.transfer.stateLabel.created': '待提交' });
        expect(cell(wrapper, 'statusId').text()).toBe('打款成功');
    });

    it.each([
        [{ providerAccountId: 'openid-1' }, 'openid-1'],
        [null, '-'],
    ])('should display the recipient account fallback %s', async (recipient, label) => {
        const { wrapper } = await createWrapper(undefined, {}, [{ ...transfers[0], recipient }]);
        expect(cell(wrapper, 'recipientId').text()).toBe(label);
    });

    it.each([
        ['wechat', 'sw-settings-payment-provider.provider.wechat'],
        [null, '-'],
    ])('should display provider %s', async (provider, label) => {
        const { wrapper } = await createWrapper(undefined, {}, [{ ...transfers[0], provider }]);
        expect(cell(wrapper, 'provider').text()).toBe(label);
    });

    it.each([
        [{ metadata: { rejectReason: '账号有误' } }, 'sw-finance.transfer.actions.cancel: 账号有误'],
        [{ failureReason: '余额不足', failureCode: 'NOT_ENOUGH' }, '余额不足'],
        [{ failureCode: 'NOT_ENOUGH' }, 'NOT_ENOUGH'],
        [{}, ''],
    ])('should distinguish the rejection and channel failure %s', async (failure, label) => {
        const { wrapper } = await createWrapper(undefined, {}, [{ ...transfers[0], ...failure }]);
        expect(cell(wrapper, 'failureCode').text()).toBe(label);
    });

    it.each([null, '2026-01-02T03:04:05.000Z'])('should render timestamp %s', async (createdAt) => {
        const { wrapper } = await createWrapper(undefined, {}, [{ ...transfers[0], createdAt }]);
        const dateFilter = Shopwell.Filter.getByName('date') as (value: string) => string;
        expect(cell(wrapper, 'createdAt').text()).toBe(createdAt === null ? '–' : dateFilter(createdAt));
    });

    it.each([
        ['created', true, true],
        ['submitted', true, false],
        ['created', false, false],
    ])('should gate decisions for state %s and approver %s', async (state, approver, visible) => {
        const { wrapper } = await createWrapper((privilege) => privilege !== 'payment_transfer.approver' || approver, {}, [
            { ...createdTransfer, statusMachineState: { technicalName: state } },
        ]);
        expect(wrapper.find('.sw-finance-transfer-list__action-submit').exists()).toBe(visible);
        expect(wrapper.find('.sw-finance-transfer-list__action-cancel').exists()).toBe(visible);
        expect(wrapper.find('.approve-modal').exists()).toBe(false);
        expect(wrapper.find('.reject-modal').exists()).toBe(false);
    });

    it.each([true, false])('should gate creation for creator privilege %s', async (creator) => {
        const { wrapper } = await createWrapper((privilege) => privilege !== 'payment_transfer.creator' || creator);
        expect(wrapper.find('.create-button').exists()).toBe(creator);
    });

    it('should open and close creation and decision modals and refresh after creation', async () => {
        const { wrapper, search } = await createWrapper(undefined, {}, [createdTransfer]);
        await wrapper.get('.create-button').trigger('click');
        expect(wrapper.find('.create-modal').exists()).toBe(true);
        await wrapper.get('.create-modal .close').trigger('click');
        expect(wrapper.find('.create-modal').exists()).toBe(false);
        await wrapper.get('.create-button').trigger('click');
        search.mockClear();
        await wrapper.get('.create-modal .decide').trigger('click');
        await flushPromises();
        expect(wrapper.find('.create-modal').exists()).toBe(false);
        expect(search).toHaveBeenCalled();
        await wrapper.get('.sw-finance-transfer-list__action-submit').trigger('click');
        expect(wrapper.find('.approve-modal').exists()).toBe(true);
        await wrapper.get('.approve-modal .close').trigger('click');
        expect(wrapper.find('.approve-modal').exists()).toBe(false);
        await wrapper.get('.sw-finance-transfer-list__action-cancel').trigger('click');
        expect(wrapper.find('.reject-modal').exists()).toBe(true);
    });

    it('should mount creation from the empty state', async () => {
        const { wrapper } = await createWrapper(() => true, {}, []);
        await wrapper.get('.sw-finance-transfer-list__empty-state .create-button').trigger('click');
        expect(wrapper.find('.create-modal').exists()).toBe(true);
    });
});
