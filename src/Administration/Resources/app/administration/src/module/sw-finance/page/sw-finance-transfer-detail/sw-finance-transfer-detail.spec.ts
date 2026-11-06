/**
 * @sw-package framework
 */
import { shallowMount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';

/**
 * The states as the state machine migration creates them, names included: `Migration1792700010` names a
 * `created` payout 已创建 and a `succeeded` one 打款成功. Those words were written for the payment gateway.
 */
const transferStates = [
    {
        id: 'state-awaiting',
        name: 'awaiting_user_confirm',
        technicalName: 'awaiting_user_confirm',
        translated: { name: '待用户确认' },
    },
    { id: 'state-succeeded', name: 'succeeded', technicalName: 'succeeded', translated: { name: '打款成功' } },
    { id: 'state-created', name: 'created', technicalName: 'created', translated: { name: '已创建' } },
];

/**
 * The words of the finance pages for the states above: the test environment answers every key with the key
 * itself, so the label is only observable with a translation that actually translates.
 */
const STATE_LABELS: Record<string, string> = {
    'sw-finance.transfer.stateLabel.awaiting_user_confirm': '待用户确认',
    'sw-finance.transfer.stateLabel.succeeded': '成功',
    'sw-finance.transfer.stateLabel.created': '待提交',
};

const transfer = {
    id: 'transfer-1',
    transferNo: 'T-1',
    outNo: 'O-1',
    provider: 'wechat',
    channel: 'wechat_transfer',
    amount: 1250,
    currency: 'CNY',
    statusId: 'state-awaiting',
    submitAttempts: 2,
    providerRef: 'transfer_bill_no-1',
    providerStageRef: null,
    confirmPayload: { package_info: 'SECRET-PACKAGE-INFO' },
    failureCode: 'NOT_ENOUGH',
    failureReason: '余额不足',
    metadata: { note: '九月供应商结算', rejectReason: null },
    createdAt: '2026-01-02T03:04:05.000Z',
    submittedAt: '2026-01-02T04:05:06.000Z',
    settledAt: null,
    returnedAt: null,
    canceledAt: null,
    expireAt: '2026-01-03T04:05:06.000Z',
    paymentApp: { id: 'app-1', name: '演示应用', technicalName: 'demo' },
    recipient: {
        holderName: '张三',
        identityType: 'wechat_open_id',
        providerAccountId: 'openid-1',
        onboardingStatus: 'registered',
        payoutEnabled: true,
        verifiedAt: '2026-01-01T00:00:00.000Z',
    },
    statusMachineState: { id: 'state-awaiting', technicalName: 'awaiting_user_confirm' },
};

function collection<T>(items: unknown[]): T {
    return Object.assign([...items], { total: items.length }) as unknown as T;
}

let activeWrapper: VueWrapper | null = null;

async function createWrapper(
    entity: Record<string, unknown> = transfer,
    can: (privilege: string) => boolean = () => true,
    stateLabels: Record<string, string> = {},
): Promise<{
    wrapper: VueWrapper;
    get: jest.Mock;
    stateSearch: jest.Mock;
}> {
    const get = jest.fn(() => Promise.resolve(entity));

    const stateSearch = jest.fn(() => Promise.resolve(collection<EntityCollection<'state_machine_state'>>(transferStates)));

    const wrapper = shallowMount(await wrapTestComponent('sw-finance-transfer-detail', { sync: true }), {
        props: {
            transferId: 'transfer-1',
        },
        global: {
            stubs: {
                'sw-page': {
                    template: `
                        <div>
                            <slot name="smart-bar-header"></slot>
                            <slot name="smart-bar-actions"></slot>
                            <slot name="content"></slot>
                        </div>`,
                },
                'sw-card-view': { template: '<div><slot></slot></div>' },
                'sw-description-list': { template: '<dl><slot></slot></dl>' },
                'mt-card': { template: '<div class="mt-card"><slot></slot></div>' },
                'router-link': { template: '<a><slot></slot></a>' },
                'mt-button': { props: ['disabled'], template: '<button :disabled="disabled"><slot></slot></button>' },
                'mt-badge': {
                    props: ['variant'],
                    template: '<span class="transfer-state" :data-variant="variant"><slot></slot></span>',
                },
                'mt-icon': true,
                'mt-link': true,
                'mt-empty-state': true,
                'sw-finance-transfer-approve-modal': {
                    template:
                        '<section class="approve-modal"><button class="close" @click="$emit(\'modal-close\')">Close</button><button class="decide" @click="$emit(\'transfer-decided\')">Decide</button></section>',
                },
                'sw-finance-transfer-reject-modal': {
                    template:
                        '<section class="reject-modal"><button class="close" @click="$emit(\'modal-close\')">Close</button><button class="decide" @click="$emit(\'transfer-decided\')">Decide</button></section>',
                },
            },
            provide: {
                repositoryFactory: {
                    create: (entityName: string) => {
                        return entityName === 'state_machine_state' ? { search: stateSearch } : { get };
                    },
                },
                acl: {
                    can,
                },
            },
            mocks: {
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

    return { wrapper, get, stateSearch };
}

describe('module/sw-finance/page/sw-finance-transfer-detail', () => {
    afterEach(() => {
        activeWrapper?.unmount();
        activeWrapper = null;
    });

    it('should load and display the transfer, recipient and app', async () => {
        const { wrapper, get, stateSearch } = await createWrapper();
        expect(get).toHaveBeenCalledWith('transfer-1', Shopwell.Context.api, expect.anything());
        expect(wrapper.text()).toContain('T-1');
        expect(wrapper.text()).toContain('张三');
        expect(wrapper.text()).toContain('演示应用');
        expect(stateSearch).toHaveBeenCalledTimes(1);
    });

    it.each([
        [
            'state-awaiting',
            'awaiting_user_confirm',
            '待用户确认',
            'attention',
        ],
        [
            'state-succeeded',
            'succeeded',
            '成功',
            'positive',
        ],
        [
            'state-created',
            'created',
            '待提交',
            'neutral',
        ],
    ])('should display the finance state %s with its amount and app', async (id, technicalName, label, variant) => {
        const { wrapper } = await createWrapper(
            { ...transfer, statusId: id, statusMachineState: { id, technicalName } },
            undefined,
            STATE_LABELS,
        );
        expect(wrapper.get('.transfer-state').text()).toBe(label);
        expect(wrapper.get('.transfer-state').attributes('data-variant')).toBe(variant);
        expect(wrapper.get('.transfer-state').text()).not.toContain('打款');
        expect(wrapper.text()).toContain(Shopwell.Utils.format.currency(12.5, 'CNY', 2));
        expect(wrapper.text()).toContain('演示应用');
    });

    it('should display a dash for missing timestamps and format the supplied dates', async () => {
        const { wrapper } = await createWrapper({ ...transfer, returnedAt: undefined });
        const dateFilter = Shopwell.Filter.getByName('date') as (value: string) => string;
        const timestamps = wrapper
            .get('[position-identifier="sw-finance-transfer-detail-timeline"]')
            .findAll('dd')
            .map((item) => item.text());
        expect(timestamps).toEqual([
            dateFilter('2026-01-02T03:04:05.000Z'),
            dateFilter('2026-01-02T04:05:06.000Z'),
            '–',
            '–',
            '–',
            dateFilter('2026-01-03T04:05:06.000Z'),
        ]);
    });

    it('should display the raw channel failure and metadata', async () => {
        const { wrapper } = await createWrapper();
        const failure = wrapper.get('[position-identifier="sw-finance-transfer-detail-failure"]');
        expect(failure.text()).toContain('NOT_ENOUGH');
        expect(failure.text()).toContain('余额不足');
        expect(wrapper.get('pre').text()).toBe(JSON.stringify({ note: '九月供应商结算', rejectReason: null }, null, 2));
    });

    it('should display the creator note without inventing a rejection', async () => {
        const { wrapper } = await createWrapper();
        expect(wrapper.get('[position-identifier="sw-finance-transfer-detail-note"]').text()).toContain('九月供应商结算');
        expect(wrapper.find('[position-identifier="sw-finance-transfer-detail-reject"]').exists()).toBe(false);
    });

    it('should display the rejection reason without inventing a creator note', async () => {
        const { wrapper } = await createWrapper({ ...transfer, metadata: { note: null, rejectReason: '收款账号有误' } });
        expect(wrapper.find('[position-identifier="sw-finance-transfer-detail-note"]').exists()).toBe(false);
        expect(wrapper.get('[position-identifier="sw-finance-transfer-detail-reject"]').text()).toContain('收款账号有误');
    });

    it('should report a generated confirmation without rendering its secret payload', async () => {
        const { wrapper } = await createWrapper();
        expect(wrapper.get('[position-identifier="sw-finance-transfer-detail-confirmation"]').text()).toContain(
            'valueConfirmationGenerated',
        );
        expect(wrapper.html()).not.toContain('SECRET-PACKAGE-INFO');
        expect(wrapper.html()).not.toContain('package_info');
    });

    it('should omit the confirmation card outside the confirmation state', async () => {
        const { wrapper } = await createWrapper({
            ...transfer,
            confirmPayload: null,
            statusId: 'state-succeeded',
            statusMachineState: { id: 'state-succeeded', technicalName: 'succeeded' },
        });
        expect(wrapper.find('[position-identifier="sw-finance-transfer-detail-confirmation"]').exists()).toBe(false);
    });

    it.each([
        ['created', true, true],
        ['created', false, false],
        ['awaiting_user_confirm', true, false],
    ])('should gate decisions for state %s and approver %s', async (state, canApprove, visible) => {
        const { wrapper } = await createWrapper(
            {
                ...transfer,
                statusId: 'state-created',
                statusMachineState: { technicalName: state },
            },
            (privilege) => privilege !== 'payment_transfer.approver' || canApprove,
        );
        expect(wrapper.find('.sw-finance-transfer-detail__button-submit').exists()).toBe(visible);
        expect(wrapper.find('.sw-finance-transfer-detail__button-reject').exists()).toBe(visible);
    });

    it('should mount both decision modals and reload the transfer after a decision', async () => {
        const { wrapper, get } = await createWrapper({
            ...transfer,
            statusId: 'state-created',
            statusMachineState: { technicalName: 'created' },
        });
        await wrapper.get('.sw-finance-transfer-detail__button-submit').trigger('click');
        expect(wrapper.find('.approve-modal').exists()).toBe(true);
        await wrapper.get('.approve-modal .close').trigger('click');
        expect(wrapper.find('.approve-modal').exists()).toBe(false);
        await wrapper.get('.sw-finance-transfer-detail__button-reject').trigger('click');
        expect(wrapper.find('.reject-modal').exists()).toBe(true);
        get.mockClear();
        await wrapper.get('.reject-modal .decide').trigger('click');
        await flushPromises();
        expect(wrapper.find('.reject-modal').exists()).toBe(false);
        expect(get).toHaveBeenCalledTimes(1);
    });

    it('should reload the transfer when refresh is clicked', async () => {
        const { wrapper, get } = await createWrapper();
        get.mockClear();
        const refresh = wrapper
            .findAll('button')
            .find((button) => button.text() === 'sw-finance.transfer.detail.buttonRefresh');
        expect(refresh).toBeDefined();
        await wrapper
            .findAll('button')
            .filter((button) => button.text() === 'sw-finance.transfer.detail.buttonRefresh')[0]
            .trigger('click');
        await flushPromises();
        expect(get).toHaveBeenCalledTimes(1);
    });
});
