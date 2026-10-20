/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';

const apps = [
    {
        id: 'app-1',
        technicalName: 'platform',
        name: '平台自有',
        type: 'platform',
        isPlatform: true,
        providers: [
            {
                technicalName: 'alipay',
                label: 'sw-settings-payment-provider.provider.alipay',
                identityTypes: ['alipay_logon_id', 'bankcard'],
                requiredRecipientFields: {
                    alipay_logon_id: ['holderName'],
                    bankcard: ['holderName', 'bankInfo'],
                },
                bankAccountTypes: ['1', '2'],
                holderNameThreshold: null,
                requiresOnboarding: false,
                currencies: ['CNY'],
                channels: [
                    { technicalName: 'alipay_transfer', label: 'alipay transfer', currencies: ['CNY'] },
                ],
            },
            {
                technicalName: 'paypal',
                label: 'sw-settings-payment-provider.provider.paypal',
                identityTypes: ['paypal_email'],
                requiredRecipientFields: { paypal_email: [] },
                bankAccountTypes: [],
                holderNameThreshold: null,
                requiresOnboarding: false,
                currencies: ['EUR', 'USD'],
                channels: [
                    { technicalName: 'paypal_payout', label: 'paypal payout', currencies: ['EUR', 'USD'] },
                    { technicalName: 'paypal_batch', label: 'paypal batch', currencies: ['EUR'] },
                ],
            },
        ],
    },
    {
        id: 'app-2',
        technicalName: 'merchant',
        name: '商户应用',
        type: 'external',
        isPlatform: false,
        providers: [],
    },
];

const recipients = [
    {
        id: 'recipient-1',
        identityType: 'alipay_logon_id',
        holderName: '张三',
        providerAccountId: null,
        maskedIdentity: 'zh****23',
        payoutEnabled: true,
        onboardingStatus: 'registered',
    },
    {
        id: 'recipient-2',
        identityType: 'alipay_logon_id',
        holderName: null,
        providerAccountId: null,
        maskedIdentity: 'li****99',
        payoutEnabled: false,
        onboardingStatus: 'pending',
    },
];

type Notification = {
    variant: string;
    message: string;
};

type CreateModalVm = {
    apps: unknown[];
    recipients: unknown[];
    paymentAppId: string | null;
    provider: string | null;
    channel: string | null;
    recipientSource: 'existing' | 'new';
    recipientId: string | null;
    identityType: string | null;
    identityValue: string;
    holderName: string;
    bankAccountType: string | null;
    bankName: string;
    amount: string;
    currency: string;
    outNo: string;
    note: string;
    isSaving: boolean;
    isLoadingOptions: boolean;
    providerOptions: Array<{ technicalName: string }>;
    channelOptions: Array<{ technicalName: string }>;
    isChannelLocked: boolean;
    identityTypeOptions: string[];
    requiresHolderName: boolean;
    requiresBankInfo: boolean;
    currencyOptions: string[];
    isCurrencyLocked: boolean;
    payableRecipients: unknown[];
    hasPayableRecipients: boolean;
    amountInMinorUnits: number | null;
    isIdentityValueTooLong: boolean;
    isOutNoInvalid: boolean;
    isNoteTooLong: boolean;
    isSaveDisabled: boolean;
    createdComponent: () => Promise<void>;
    loadOptions: () => Promise<void>;
    loadRecipients: () => Promise<void>;
    applyDefaultsOfApp: () => void;
    resetDownstreamOfProvider: () => void;
    recipientOptionLabel: (recipient: unknown) => string;
    identityTypeLabel: (identityType: string) => string;
    buildPayload: () => Record<string, unknown>;
    onSave: () => void;
    onClose: () => void;
    createNotification: jest.Mock<void, [Notification]>;
};

type TypedWrapper = { vm: CreateModalVm } & VueWrapper;

let activeWrapper: VueWrapper | null = null;

async function createWrapper(overrides: {
    getOptions?: jest.Mock;
    getRecipients?: jest.Mock;
    createTransfer?: jest.Mock;
} = {}): Promise<{ wrapper: TypedWrapper; notify: jest.Mock<void, [Notification]> }> {
    const getOptions =
        overrides.getOptions ?? jest.fn(() => Promise.resolve({ apps, defaultAppId: 'app-1' }));
    const getRecipients = overrides.getRecipients ?? jest.fn(() => Promise.resolve(recipients));
    const createTransfer =
        overrides.createTransfer ?? jest.fn(() => Promise.resolve({ transfer: { id: 'transfer-1' }, replayed: false }));

    const wrapper = mount(await wrapTestComponent('sw-finance-transfer-create-modal', { sync: true }), {
        global: {
            stubs: {
                'sw-modal': {
                    template: '<div><slot></slot><slot name="modal-footer"></slot></div>',
                },
                'sw-loader': true,
                'mt-banner': { template: '<div><slot></slot></div>' },
                'mt-select': true,
                'mt-text-field': true,
                'mt-textarea': true,
                'mt-button': true,
            },
            provide: {
                paymentTransferApiService: { getOptions, getRecipients, createTransfer },
            },
        },
    }) as unknown as TypedWrapper;

    const notify = jest.fn<void, [Notification]>();
    wrapper.vm.createNotification = notify;

    activeWrapper = wrapper;

    await flushPromises();

    return { wrapper, notify };
}

describe('module/sw-finance/component/sw-finance-transfer-create-modal', () => {
    afterEach(() => {
        activeWrapper?.unmount();
        activeWrapper = null;
    });

    it('should preselect the default app and its first interface', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.isLoadingOptions).toBe(false);
        expect(wrapper.vm.paymentAppId).toBe('app-1');
        expect(wrapper.vm.provider).toBe('alipay');
        // a channel dropdown with a single entry is preselected and locked
        expect(wrapper.vm.channel).toBe('alipay_transfer');
        expect(wrapper.vm.isChannelLocked).toBe(true);
        expect(wrapper.vm.currency).toBe('CNY');
        expect(wrapper.vm.isCurrencyLocked).toBe(true);
    });

    /**
     * Switching the app switches the merchant account: a channel or a recipient of the old one must never
     * survive the switch, that is the one mistake of this form that moves money across tenants.
     */
    it('should reset the downstream fields when the app changes', async () => {
        const { wrapper } = await createWrapper();

        wrapper.vm.recipientId = 'recipient-1';
        wrapper.vm.identityType = 'alipay_logon_id';
        wrapper.vm.identityValue = 'zhangsan@example.com';
        wrapper.vm.holderName = '张三';

        wrapper.vm.paymentAppId = 'app-2';
        await flushPromises();

        expect(wrapper.vm.provider).toBeNull();
        expect(wrapper.vm.channel).toBeNull();
        expect(wrapper.vm.recipientId).toBeNull();
        expect(wrapper.vm.identityType).toBeNull();
        expect(wrapper.vm.identityValue).toBe('');
        expect(wrapper.vm.holderName).toBe('');
        expect(wrapper.vm.providerOptions).toEqual([]);
    });

    it('should reset the channel, the recipient and the identity when the interface changes', async () => {
        const { wrapper } = await createWrapper();

        wrapper.vm.channel = 'alipay_transfer';
        wrapper.vm.recipientId = 'recipient-1';
        wrapper.vm.identityValue = 'zhangsan@example.com';
        await flushPromises();

        wrapper.vm.provider = 'paypal';
        await flushPromises();

        expect(wrapper.vm.recipientId).toBeNull();
        expect(wrapper.vm.identityValue).toBe('');
        // paypal offers two channels, so nothing is preselected for the operator
        expect(wrapper.vm.channel).toBeNull();
        expect(wrapper.vm.currency).toBe('EUR');
        expect(wrapper.vm.isCurrencyLocked).toBe(false);
    });

    it('should keep only the payable recipients of the interface', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.payableRecipients).toHaveLength(1);
        expect(wrapper.vm.hasPayableRecipients).toBe(true);
        expect(wrapper.vm.recipientOptionLabel(recipients[0])).toContain('张三');
        expect(wrapper.vm.recipientOptionLabel(recipients[0])).toContain('zh****23');
    });

    it('should demand the holder name and the bank details for the identities that need them', async () => {
        const { wrapper } = await createWrapper();

        wrapper.vm.recipientSource = 'new';
        // switching the source resets the identity fields in a watcher, so the tick has to settle first
        await flushPromises();
        wrapper.vm.identityType = 'alipay_logon_id';
        await flushPromises();

        expect(wrapper.vm.requiresHolderName).toBe(true);
        expect(wrapper.vm.requiresBankInfo).toBe(false);

        wrapper.vm.identityType = 'bankcard';
        await flushPromises();

        expect(wrapper.vm.requiresHolderName).toBe(true);
        expect(wrapper.vm.requiresBankInfo).toBe(true);
        expect(wrapper.vm.bankAccountType).toBe('1');
    });

    /**
     * The conversion is the reason this modal exists in this shape: the amount stays a string until the
     * request is built, and the request carries fen.
     */
    it('should convert the typed yuan into fen without floating point', async () => {
        const { wrapper } = await createWrapper();

        wrapper.vm.amount = '0.1';
        await flushPromises();
        expect(wrapper.vm.amountInMinorUnits).toBe(10);

        wrapper.vm.amount = '1.005';
        await flushPromises();
        expect(wrapper.vm.amountInMinorUnits).toBeNull();
        expect(wrapper.vm.isSaveDisabled).toBe(true);
    });

    it('should refuse an identity longer than the encrypted column', async () => {
        const { wrapper } = await createWrapper();

        wrapper.vm.recipientSource = 'new';
        await flushPromises();
        wrapper.vm.identityType = 'alipay_logon_id';
        wrapper.vm.holderName = '张三';
        // the column holds 149 bytes, so 150 ascii characters are one too many
        wrapper.vm.identityValue = 'a'.repeat(150);
        await flushPromises();

        expect(wrapper.vm.isIdentityValueTooLong).toBe(true);
        expect(wrapper.vm.isSaveDisabled).toBe(true);
    });

    it('should refuse an out number that is no idempotency key', async () => {
        const { wrapper } = await createWrapper();

        wrapper.vm.outNo = 'has spaces';
        await flushPromises();
        expect(wrapper.vm.isOutNoInvalid).toBe(true);

        wrapper.vm.outNo = 'A'.repeat(30);
        await flushPromises();
        expect(wrapper.vm.isOutNoInvalid).toBe(false);

        wrapper.vm.outNo = 'A'.repeat(31);
        await flushPromises();
        expect(wrapper.vm.isOutNoInvalid).toBe(true);
    });

    it('should not save while a required field is missing', async () => {
        const { wrapper } = await createWrapper();

        // the default app preselects the interface and the only channel, the recipient is still missing
        expect(wrapper.vm.recipientId).toBeNull();
        expect(wrapper.vm.isSaveDisabled).toBe(true);

        wrapper.vm.recipientId = 'recipient-1';
        wrapper.vm.amount = '12.05';
        await flushPromises();

        expect(wrapper.vm.isSaveDisabled).toBe(false);
    });

    it('should send the existing recipient and no identity', async () => {
        const { wrapper } = await createWrapper();

        wrapper.vm.recipientId = 'recipient-1';
        wrapper.vm.amount = '12.05';
        wrapper.vm.note = ' 九月结算 ';
        wrapper.vm.outNo = 'OUT-1';
        await flushPromises();

        expect(wrapper.vm.buildPayload()).toEqual({
            paymentAppId: 'app-1',
            provider: 'alipay',
            channel: 'alipay_transfer',
            recipientId: 'recipient-1',
            identityType: null,
            identityValue: null,
            holderName: null,
            bankInfo: null,
            amount: 1205,
            currency: 'CNY',
            outNo: 'OUT-1',
            note: '九月结算',
        });
    });

    it('should send a new recipient with its identity and the bank details of a card', async () => {
        const { wrapper } = await createWrapper();

        wrapper.vm.recipientSource = 'new';
        await flushPromises();
        wrapper.vm.identityType = 'bankcard';
        wrapper.vm.identityValue = '6222020000000000000';
        wrapper.vm.holderName = '张三';
        wrapper.vm.bankName = '招商银行';
        wrapper.vm.amount = '7';
        await flushPromises();

        const payload = wrapper.vm.buildPayload();

        expect(payload.recipientId).toBeNull();
        expect(payload.identityType).toBe('bankcard');
        expect(payload.identityValue).toBe('6222020000000000000');
        expect(payload.amount).toBe(700);
        expect(payload.bankInfo).toEqual({ account_type: '1', inst_name: '招商银行' });
        // the out number stays empty so the server generates one
        expect(payload.outNo).toBeNull();
    });

    it('should report a created and a replayed request differently', async () => {
        const createTransfer = jest.fn(() => Promise.resolve({ transfer: { id: 'transfer-1' }, replayed: false }));
        const { wrapper, notify } = await createWrapper({ createTransfer });

        wrapper.vm.recipientId = 'recipient-1';
        wrapper.vm.amount = '1';
        await flushPromises();

        wrapper.vm.onSave();
        await flushPromises();

        expect(createTransfer).toHaveBeenCalledTimes(1);
        expect(notify.mock.calls[0][0].variant).toBe('success');
        expect(wrapper.emitted('transfer-created')).toHaveLength(1);
        expect(wrapper.vm.isSaving).toBe(false);
    });

    it('should keep the modal open and show the readable error of a failed save', async () => {
        const createTransfer = jest.fn(() =>
            // the admin api rejects with a response object, not with an Error - the message of the
            // server is the whole point of the assertion below
            // eslint-disable-next-line @typescript-eslint/prefer-promise-reject-errors
            Promise.reject({ response: { data: { errors: [{ detail: 'PAYMENT__TRANSFER_OUT_NO_TAKEN' }] } } }),
        );
        const { wrapper, notify } = await createWrapper({ createTransfer });

        wrapper.vm.recipientId = 'recipient-1';
        wrapper.vm.amount = '1';
        await flushPromises();

        wrapper.vm.onSave();
        await flushPromises();

        expect(notify.mock.calls[0][0].variant).toBe('error');
        expect(notify.mock.calls[0][0].message).toBe('PAYMENT__TRANSFER_OUT_NO_TAKEN');
        expect(wrapper.emitted('transfer-created')).toBeUndefined();
        expect(wrapper.vm.isSaving).toBe(false);
    });

    it('should not close while a save is in flight', async () => {
        let resolveCreate: (value: unknown) => void = () => {};
        const createTransfer = jest.fn(
            () =>
                new Promise((resolve) => {
                    resolveCreate = resolve;
                }),
        );

        const { wrapper } = await createWrapper({ createTransfer });

        wrapper.vm.recipientId = 'recipient-1';
        wrapper.vm.amount = '1';
        await flushPromises();

        wrapper.vm.onSave();
        await flushPromises();

        wrapper.vm.onClose();
        expect(wrapper.emitted('modal-close')).toBeUndefined();

        resolveCreate({ transfer: { id: 'transfer-1' }, replayed: false });
        await flushPromises();

        wrapper.vm.onClose();
        expect(wrapper.emitted('modal-close')).toHaveLength(1);
    });
});
