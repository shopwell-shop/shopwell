/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
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

type TransferDetailVm = {
    transfer: Record<string, unknown> | null;
    isLoading: boolean;
    transferStates: unknown[] | null;
    paymentAppLabel: string;
    providerLabel: string;
    isAwaitingUserConfirm: boolean;
    hasConfirmPayload: boolean;
    hasFailure: boolean;
    note: string;
    rejectReason: string;
    metadataJson: string;
    isDecidable: boolean;
    canApprove: boolean;
    canDecide: boolean;
    showApproveModal: boolean;
    showRejectModal: boolean;
    transferStateLabel: (stateId: string) => string;
    transferStateVariant: (stateId: string) => string;
    formatAmount: (amount: number, currency: string) => string;
    formatDateOrDash: (value: string | null | undefined) => string;
    onRefresh: () => Promise<void>;
    onApprove: () => void;
    onReject: () => void;
    onTransferDecided: () => void;
    onCloseApproveModal: () => void;
    onCloseRejectModal: () => void;
};

type TypedWrapper = { vm: TransferDetailVm } & VueWrapper;

function collection<T>(items: unknown[]): T {
    return Object.assign([...items], { total: items.length }) as unknown as T;
}

let activeWrapper: VueWrapper | null = null;

async function createWrapper(
    entity: Record<string, unknown> = transfer,
    can: (privilege: string) => boolean = () => true,
    stateLabels: Record<string, string> = {},
): Promise<{
    wrapper: TypedWrapper;
    get: jest.Mock;
    stateSearch: jest.Mock;
}> {
    const get = jest.fn(() => Promise.resolve(entity));

    const stateSearch = jest.fn(() =>
        Promise.resolve(collection<EntityCollection<'state_machine_state'>>(transferStates)),
    );

    const wrapper = mount(await wrapTestComponent('sw-finance-transfer-detail', { sync: true }), {
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
                'mt-button': true,
                'mt-badge': true,
                'mt-icon': true,
                'mt-link': true,
                'mt-empty-state': true,
                'sw-finance-transfer-approve-modal': true,
                'sw-finance-transfer-reject-modal': true,
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
    }) as unknown as TypedWrapper;

    activeWrapper = wrapper;

    await flushPromises();

    return { wrapper, get, stateSearch };
}

describe('module/sw-finance/page/sw-finance-transfer-detail', () => {
    afterEach(() => {
        activeWrapper?.unmount();
        activeWrapper = null;
    });

    it('should load the transfer with its recipient, app and status', async () => {
        const { wrapper, get, stateSearch } = await createWrapper();

        expect(get).toHaveBeenCalledWith('transfer-1', Shopwell.Context.api, expect.anything());
        expect(wrapper.vm.transfer).toEqual(transfer);
        expect(wrapper.vm.transferStates).toHaveLength(3);

        // the states are read once, not with every refresh
        expect(stateSearch).toHaveBeenCalledTimes(1);
    });

    /**
     * The snippet of this module wins over the name the state machine carries: the migration calls the same
     * state 打款成功 and a `created` payout 已创建, which are words of the payment gateway and not of these
     * pages.
     */
    it('should render the state, the amount and the app of the transfer', async () => {
        const { wrapper } = await createWrapper(transfer, undefined, STATE_LABELS);

        expect(wrapper.vm.transferStateLabel('state-awaiting')).toBe('待用户确认');
        expect(wrapper.vm.transferStateLabel('state-succeeded')).toBe('成功');
        expect(wrapper.vm.transferStateLabel('state-created')).toBe('待提交');
        expect(wrapper.vm.transferStateLabel('state-succeeded')).not.toContain('打款');
        expect(wrapper.vm.transferStateVariant('state-awaiting')).toBe('attention');
        expect(wrapper.vm.transferStateVariant('state-succeeded')).toBe('positive');
        expect(wrapper.vm.formatAmount(1250, 'CNY')).toBe(Shopwell.Utils.format.currency(12.5, 'CNY', 2));
        expect(wrapper.vm.paymentAppLabel).toBe('演示应用');
    });

    it('should show a dash for every missing timestamp instead of an invalid date', async () => {
        const { wrapper } = await createWrapper();
        const dateFilter = Shopwell.Filter.getByName('date') as (value: string) => string;

        expect(wrapper.vm.formatDateOrDash(null)).toBe('–');
        expect(wrapper.vm.formatDateOrDash(undefined)).toBe('–');
        expect(wrapper.vm.formatDateOrDash('2026-01-02T03:04:05.000Z')).toBe(
            dateFilter('2026-01-02T03:04:05.000Z'),
        );
    });

    it('should report the failure of the channel as it came', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.hasFailure).toBe(true);
        expect(wrapper.vm.metadataJson).toBe(JSON.stringify({ note: '九月供应商结算', rejectReason: null }, null, 2));
    });

    it('should show the note of the creator and the reason of a rejection', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.note).toBe('九月供应商结算');
        expect(wrapper.vm.rejectReason).toBe('');

        activeWrapper?.unmount();

        const rejected = await createWrapper({
            ...transfer,
            statusId: 'state-succeeded',
            statusMachineState: { id: 'state-succeeded', technicalName: 'succeeded' },
            metadata: { note: null, rejectReason: '收款账号有误' },
        });

        expect(rejected.wrapper.vm.note).toBe('');
        expect(rejected.wrapper.vm.rejectReason).toBe('收款账号有误');
    });

    it('should never render the wechat confirmation payload', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.isAwaitingUserConfirm).toBe(true);
        expect(wrapper.vm.hasConfirmPayload).toBe(true);

        const html = wrapper.html();

        expect(html).toContain('sw-finance.transfer.detail.cardConfirmationTitle');
        expect(html).not.toContain('SECRET-PACKAGE-INFO');
        expect(html).not.toContain('package_info');
    });

    it('should not offer the confirmation card outside of the confirmation state', async () => {
        const { wrapper } = await createWrapper({
            ...transfer,
            confirmPayload: null,
            statusId: 'state-succeeded',
            statusMachineState: { id: 'state-succeeded', technicalName: 'succeeded' },
        });

        expect(wrapper.vm.isAwaitingUserConfirm).toBe(false);
        expect(wrapper.vm.hasConfirmPayload).toBe(false);
        expect(wrapper.html()).not.toContain('sw-finance.transfer.detail.cardConfirmationTitle');
    });

    /**
     * The action visibility matrix of the detail page: a decision needs the `created` state *and* the
     * approver privilege. Either one alone must not put a button on the screen.
     */
    it('should offer the decisions only for a created transfer and with the approver privilege', async () => {
        const decidable = {
            ...transfer,
            statusId: 'state-created',
            statusMachineState: { id: 'state-created', technicalName: 'created' },
        };

        // The buttons are stubbed, so they are matched by their class instead of their slot text.
        const approver = await createWrapper(decidable);
        expect(approver.wrapper.vm.isDecidable).toBe(true);
        expect(approver.wrapper.vm.canDecide).toBe(true);
        expect(approver.wrapper.find('.sw-finance-transfer-detail__button-submit').exists()).toBe(true);
        expect(approver.wrapper.find('.sw-finance-transfer-detail__button-reject').exists()).toBe(true);

        activeWrapper?.unmount();

        const viewer = await createWrapper(decidable, (privilege) => privilege !== 'payment_transfer.approver');
        expect(viewer.wrapper.vm.isDecidable).toBe(true);
        expect(viewer.wrapper.vm.canDecide).toBe(false);
        expect(viewer.wrapper.find('.sw-finance-transfer-detail__button-submit').exists()).toBe(false);

        activeWrapper?.unmount();

        const finished = await createWrapper();
        expect(finished.wrapper.vm.isDecidable).toBe(false);
        expect(finished.wrapper.vm.canDecide).toBe(false);
        expect(finished.wrapper.find('.sw-finance-transfer-detail__button-submit').exists()).toBe(false);
    });

    it('should open the confirmation and the rejection modal and reload after a decision', async () => {
        const decidable = {
            ...transfer,
            statusId: 'state-created',
            statusMachineState: { id: 'state-created', technicalName: 'created' },
        };

        const { wrapper, get } = await createWrapper(decidable);

        wrapper.vm.onApprove();
        await flushPromises();
        expect(wrapper.vm.showApproveModal).toBe(true);

        wrapper.vm.onCloseApproveModal();
        expect(wrapper.vm.showApproveModal).toBe(false);

        wrapper.vm.onReject();
        await flushPromises();
        expect(wrapper.vm.showRejectModal).toBe(true);

        get.mockClear();
        wrapper.vm.onTransferDecided();
        await flushPromises();

        expect(wrapper.vm.showRejectModal).toBe(false);
        expect(get).toHaveBeenCalledTimes(1);
    });

    it('should reload the transfer on refresh', async () => {
        const { wrapper, get } = await createWrapper();

        get.mockClear();
        await wrapper.vm.onRefresh();

        expect(get).toHaveBeenCalledTimes(1);
    });
});
