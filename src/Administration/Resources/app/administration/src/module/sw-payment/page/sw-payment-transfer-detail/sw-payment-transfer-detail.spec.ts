/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';

const transferStates = [
    {
        id: 'state-awaiting',
        name: 'awaiting_user_confirm',
        technicalName: 'awaiting_user_confirm',
        translated: { name: '等待用户确认' },
    },
    { id: 'state-succeeded', name: 'succeeded', technicalName: 'succeeded', translated: { name: '已成功' } },
];

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
    metadata: { orderId: 'order-1' },
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
    isAwaitingUserConfirm: boolean;
    hasConfirmPayload: boolean;
    hasFailure: boolean;
    metadataJson: string;
    transferStateLabel: (stateId: string) => string;
    transferStateVariant: (stateId: string) => string;
    formatAmount: (amount: number, currency: string) => string;
    formatDateOrDash: (value: string | null | undefined) => string;
    onRefresh: () => Promise<void>;
};

type TypedWrapper = { vm: TransferDetailVm } & VueWrapper;

function collection<T>(items: unknown[]): T {
    return Object.assign([...items], { total: items.length }) as unknown as T;
}

let activeWrapper: VueWrapper | null = null;

async function createWrapper(entity: Record<string, unknown> = transfer): Promise<{
    wrapper: TypedWrapper;
    get: jest.Mock;
    stateSearch: jest.Mock;
}> {
    const get = jest.fn(() => Promise.resolve(entity));

    const stateSearch = jest.fn(() =>
        Promise.resolve(collection<EntityCollection<'state_machine_state'>>(transferStates)),
    );

    const wrapper = mount(await wrapTestComponent('sw-payment-transfer-detail', { sync: true }), {
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
            },
            provide: {
                repositoryFactory: {
                    create: (entityName: string) => {
                        return entityName === 'state_machine_state' ? { search: stateSearch } : { get };
                    },
                },
            },
            mocks: {
                $router: {
                    push: jest.fn(),
                    replace: jest.fn(),
                },
            },
        },
    }) as unknown as TypedWrapper;

    activeWrapper = wrapper;

    await flushPromises();

    return { wrapper, get, stateSearch };
}

describe('module/sw-payment/page/sw-payment-transfer-detail', () => {
    afterEach(() => {
        activeWrapper?.unmount();
        activeWrapper = null;
    });

    it('should load the transfer with its recipient, app and status', async () => {
        const { wrapper, get, stateSearch } = await createWrapper();

        expect(get).toHaveBeenCalledWith('transfer-1', Shopwell.Context.api, expect.anything());
        expect(wrapper.vm.transfer).toEqual(transfer);
        expect(wrapper.vm.transferStates).toHaveLength(2);

        // the states are read once, not with every refresh
        expect(stateSearch).toHaveBeenCalledTimes(1);
    });

    it('should render the state, the amount and the app of the transfer', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.transferStateLabel('state-awaiting')).toBe('等待用户确认');
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
        expect(wrapper.vm.metadataJson).toBe(JSON.stringify({ orderId: 'order-1' }, null, 2));
    });

    it('should never render the wechat confirmation payload', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.isAwaitingUserConfirm).toBe(true);
        expect(wrapper.vm.hasConfirmPayload).toBe(true);

        const html = wrapper.html();

        expect(html).toContain('sw-payment.transfer.detail.cardConfirmationTitle');
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
        expect(wrapper.html()).not.toContain('sw-payment.transfer.detail.cardConfirmationTitle');
    });

    it('should reload the transfer on refresh', async () => {
        const { wrapper, get } = await createWrapper();

        get.mockClear();
        await wrapper.vm.onRefresh();

        expect(get).toHaveBeenCalledTimes(1);
    });
});
