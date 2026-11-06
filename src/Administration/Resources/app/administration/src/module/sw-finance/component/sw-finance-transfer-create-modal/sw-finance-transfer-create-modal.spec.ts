/**
 * @sw-package framework
 */
import { shallowMount } from '@vue/test-utils';
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

let activeWrapper: VueWrapper | null = null;

const selectStub = {
    props: ['modelValue', 'options', 'disabled'],
    template: `<select :value="modelValue ?? ''" :disabled="disabled"
        @change="$emit('update:modelValue', $event.target.value || null)">
        <option value=""></option>
        <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
    </select>`,
};

const inputStub = {
    props: ['modelValue', 'error'],
    template: `<label><input :value="modelValue" @input="$emit('update:modelValue', $event.target.value)">
        <span v-if="error" role="alert">{{ error }}</span></label>`,
};

async function createWrapper(
    overrides: { getOptions?: jest.Mock; getRecipients?: jest.Mock; createTransfer?: jest.Mock } = {},
) {
    const getOptions = overrides.getOptions ?? jest.fn(() => Promise.resolve({ apps, defaultAppId: 'app-1' }));
    const getRecipients = overrides.getRecipients ?? jest.fn(() => Promise.resolve(recipients));
    const createTransfer =
        overrides.createTransfer ?? jest.fn(() => Promise.resolve({ transfer: { id: 'transfer-1' }, replayed: false }));
    const notify = jest.spyOn(Shopwell.Store.get('notification'), 'createNotification').mockReturnValue('notification-1');
    const wrapper = shallowMount(await wrapTestComponent('sw-finance-transfer-create-modal', { sync: true }), {
        global: {
            stubs: {
                'sw-modal': {
                    template:
                        '<div><slot></slot><slot name="modal-footer"></slot><button class="modal-close" @click="$emit(\'modal-close\')">Close</button></div>',
                },
                'sw-loader': true,
                'mt-banner': { template: '<div><slot></slot></div>' },
                'mt-select': selectStub,
                'mt-text-field': inputStub,
                'mt-textarea': inputStub,
                'mt-button': {
                    props: ['disabled', 'isLoading'],
                    template: '<button :disabled="disabled" :data-loading="isLoading"><slot></slot></button>',
                },
            },
            provide: { paymentTransferApiService: { getOptions, getRecipients, createTransfer } },
        },
    });
    activeWrapper = wrapper;
    await flushPromises();
    return { wrapper, notify, createTransfer, getRecipients };
}

function field(wrapper: VueWrapper, name: string) {
    return wrapper.get(`.sw-finance-transfer-create-modal__field-${name}`);
}

async function select(wrapper: VueWrapper, name: string, value: string) {
    await field(wrapper, name).setValue(value);
    await flushPromises();
}

async function input(wrapper: VueWrapper, name: string, value: string) {
    await field(wrapper, name).get('input').setValue(value);
    await flushPromises();
}

function saveButton(wrapper: VueWrapper) {
    return wrapper.get('.sw-finance-transfer-create-modal__button-save');
}

async function fillExistingRecipient(wrapper: VueWrapper, amount = '1') {
    await select(wrapper, 'recipient', 'recipient-1');
    await input(wrapper, 'amount', amount);
}

describe('module/sw-finance/component/sw-finance-transfer-create-modal', () => {
    afterEach(() => {
        activeWrapper?.unmount();
        activeWrapper = null;
        jest.restoreAllMocks();
    });

    it('should preselect and lock the default app interface, channel and currency', async () => {
        const { wrapper } = await createWrapper();
        expect(wrapper.find('.sw-finance-transfer-create-modal__loading').exists()).toBe(false);
        expect((field(wrapper, 'app').element as HTMLInputElement).value).toBe('app-1');
        expect((field(wrapper, 'provider').element as HTMLInputElement).value).toBe('alipay');
        expect((field(wrapper, 'channel').element as HTMLInputElement).value).toBe('alipay_transfer');
        expect(field(wrapper, 'channel').attributes('disabled')).toBeDefined();
        expect((field(wrapper, 'currency').element as HTMLInputElement).value).toBe('CNY');
        expect(field(wrapper, 'currency').attributes('disabled')).toBeDefined();
    });

    it('should clear recipient identities when switching the merchant app', async () => {
        const { wrapper, createTransfer } = await createWrapper();
        await select(wrapper, 'recipient-source', 'new');
        await select(wrapper, 'identity-type', 'alipay_logon_id');
        await input(wrapper, 'identity-value', 'zhangsan@example.com');
        await input(wrapper, 'holder-name', '张三');
        await select(wrapper, 'app', 'app-2');
        expect((field(wrapper, 'provider').element as HTMLInputElement).value).toBe('');
        expect(field(wrapper, 'provider').findAll('option')).toHaveLength(1);
        expect((field(wrapper, 'channel').element as HTMLInputElement).value).toBe('');
        expect((field(wrapper, 'identity-type').element as HTMLInputElement).value).toBe('');
        expect((field(wrapper, 'identity-value').get('input').element as HTMLInputElement).value).toBe('');
        expect(wrapper.find('.sw-finance-transfer-create-modal__field-holder-name').exists()).toBe(false);
        await saveButton(wrapper).trigger('click');
        expect(createTransfer).not.toHaveBeenCalled();
    });

    it('should clear the recipient when changing interface and offer its channels and currencies', async () => {
        const { wrapper } = await createWrapper();
        await select(wrapper, 'recipient', 'recipient-1');
        await select(wrapper, 'provider', 'paypal');
        expect((field(wrapper, 'recipient').element as HTMLInputElement).value).toBe('');
        expect((field(wrapper, 'channel').element as HTMLInputElement).value).toBe('');
        expect(field(wrapper, 'channel').findAll('option')).toHaveLength(3);
        expect((field(wrapper, 'currency').element as HTMLInputElement).value).toBe('EUR');
        expect(field(wrapper, 'currency').attributes('disabled')).toBeUndefined();
    });

    it('should show only payable recipients with their name and masked identity', async () => {
        const { wrapper } = await createWrapper();
        const options = field(wrapper, 'recipient').findAll('option');
        expect(options).toHaveLength(2);
        expect(options[1].text()).toContain('张三');
        expect(options[1].text()).toContain('zh****23');
        expect(field(wrapper, 'recipient').text()).not.toContain('li****99');
    });

    it('should request holder and bank details according to the selected identity', async () => {
        const { wrapper } = await createWrapper();
        await select(wrapper, 'recipient-source', 'new');
        await select(wrapper, 'identity-type', 'alipay_logon_id');
        expect(wrapper.find('.sw-finance-transfer-create-modal__field-holder-name').exists()).toBe(true);
        expect(wrapper.find('.sw-finance-transfer-create-modal__field-bank-account-type').exists()).toBe(false);
        await select(wrapper, 'identity-type', 'bankcard');
        expect(wrapper.find('.sw-finance-transfer-create-modal__field-holder-name').exists()).toBe(true);
        expect((field(wrapper, 'bank-account-type').element as HTMLInputElement).value).toBe('1');
        expect(wrapper.find('.sw-finance-transfer-create-modal__field-bank-name').exists()).toBe(true);
    });

    it('should send typed yuan as exact fen and reject excessive decimals', async () => {
        const { wrapper, createTransfer } = await createWrapper();
        await fillExistingRecipient(wrapper, '0.1');
        await saveButton(wrapper).trigger('click');
        await flushPromises();
        expect(createTransfer).toHaveBeenCalledWith(expect.objectContaining({ amount: 10, currency: 'CNY' }));
        await input(wrapper, 'amount', '1.005');
        expect(field(wrapper, 'amount').get('[role="alert"]').text()).toBe(
            'sw-finance.transfer.create.validationAmountInvalid',
        );
        expect(saveButton(wrapper).attributes('disabled')).toBeDefined();
    });

    it('should respect the selected zero-decimal currency when booking an amount', async () => {
        const japaneseApps = [{ ...apps[0], providers: [{ ...apps[0].providers[0], currencies: ['JPY'] }] }];
        const { wrapper, createTransfer } = await createWrapper({
            getOptions: jest.fn(() => Promise.resolve({ apps: japaneseApps, defaultAppId: 'app-1' })),
        });
        await fillExistingRecipient(wrapper, '1234');
        await saveButton(wrapper).trigger('click');
        await flushPromises();
        expect(createTransfer).toHaveBeenCalledWith(expect.objectContaining({ amount: 1234, currency: 'JPY' }));
        await input(wrapper, 'amount', '12.05');
        expect(saveButton(wrapper).attributes('disabled')).toBeDefined();
        expect(field(wrapper, 'amount').find('[role="alert"]').exists()).toBe(true);
    });

    it('should reject an identity longer than the encrypted column', async () => {
        const { wrapper } = await createWrapper();
        await select(wrapper, 'recipient-source', 'new');
        await select(wrapper, 'identity-type', 'alipay_logon_id');
        await input(wrapper, 'holder-name', '张三');
        await input(wrapper, 'identity-value', 'a'.repeat(150));
        expect(field(wrapper, 'identity-value').get('[role="alert"]').text()).toContain('validationIdentityValueTooLong');
        expect(saveButton(wrapper).attributes('disabled')).toBeDefined();
    });

    it('should reject malformed or oversized idempotency keys', async () => {
        const { wrapper } = await createWrapper();
        await fillExistingRecipient(wrapper);
        await input(wrapper, 'out-no', 'has spaces');
        expect(field(wrapper, 'out-no').find('[role="alert"]').exists()).toBe(true);
        expect(saveButton(wrapper).attributes('disabled')).toBeDefined();
        await input(wrapper, 'out-no', 'A'.repeat(30));
        expect(field(wrapper, 'out-no').find('[role="alert"]').exists()).toBe(false);
        expect(saveButton(wrapper).attributes('disabled')).toBeUndefined();
        await input(wrapper, 'out-no', 'A'.repeat(31));
        expect(field(wrapper, 'out-no').find('[role="alert"]').exists()).toBe(true);
    });

    it('should not save before the required recipient and amount are present', async () => {
        const { wrapper, createTransfer } = await createWrapper();
        expect((field(wrapper, 'recipient').element as HTMLInputElement).value).toBe('');
        expect(saveButton(wrapper).attributes('disabled')).toBeDefined();
        await saveButton(wrapper).trigger('click');
        expect(createTransfer).not.toHaveBeenCalled();
        await fillExistingRecipient(wrapper, '12.05');
        expect(saveButton(wrapper).attributes('disabled')).toBeUndefined();
    });

    it('should submit an existing recipient without identity fields', async () => {
        const { wrapper, createTransfer } = await createWrapper();
        await fillExistingRecipient(wrapper, '12.05');
        await input(wrapper, 'note', ' 九月结算 ');
        await input(wrapper, 'out-no', 'OUT1');
        await saveButton(wrapper).trigger('click');
        await flushPromises();
        expect(createTransfer).toHaveBeenCalledWith({
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
            outNo: 'OUT1',
            note: '九月结算',
        });
    });

    it('should submit new bankcard identity and bank details', async () => {
        const { wrapper, createTransfer } = await createWrapper();
        await select(wrapper, 'recipient-source', 'new');
        await select(wrapper, 'identity-type', 'bankcard');
        await input(wrapper, 'identity-value', '6222020000000000000');
        await input(wrapper, 'holder-name', '张三');
        await input(wrapper, 'bank-name', '招商银行');
        await input(wrapper, 'amount', '7');
        await saveButton(wrapper).trigger('click');
        await flushPromises();
        expect(createTransfer).toHaveBeenCalledWith(
            expect.objectContaining({
                recipientId: null,
                identityType: 'bankcard',
                identityValue: '6222020000000000000',
                amount: 700,
                bankInfo: { account_type: '1', inst_name: '招商银行' },
                outNo: null,
            }),
        );
    });

    it.each([false, true])('should report the server replay result %s', async (replayed) => {
        const { wrapper, notify, createTransfer } = await createWrapper({
            createTransfer: jest.fn(() => Promise.resolve({ transfer: { id: 'transfer-1' }, replayed })),
        });
        await fillExistingRecipient(wrapper);
        await saveButton(wrapper).trigger('click');
        await flushPromises();
        expect(createTransfer).toHaveBeenCalledTimes(1);
        expect(notify).toHaveBeenCalledWith(
            expect.objectContaining({
                variant: 'success',
                message: replayed
                    ? 'sw-finance.transfer.create.messageReplayed'
                    : 'sw-finance.transfer.create.messageCreated',
            }),
        );
        expect(wrapper.emitted('transfer-created')).toEqual([[{ id: 'transfer-1' }]]);
        expect(saveButton(wrapper).attributes('data-loading')).toBe('false');
    });

    it('should retain the modal and expose the readable save failure', async () => {
        const failure = Object.assign(new Error('Transfer failed'), {
            response: { data: { errors: [{ detail: 'PAYMENT__TRANSFER_OUT_NO_TAKEN' }] } },
        });
        const { wrapper, notify } = await createWrapper({ createTransfer: jest.fn(() => Promise.reject(failure)) });
        await fillExistingRecipient(wrapper);
        await saveButton(wrapper).trigger('click');
        await flushPromises();
        expect(notify).toHaveBeenCalledWith(
            expect.objectContaining({ variant: 'error', message: 'PAYMENT__TRANSFER_OUT_NO_TAKEN' }),
        );
        expect(wrapper.emitted('transfer-created')).toBeUndefined();
        expect(saveButton(wrapper).attributes('data-loading')).toBe('false');
    });

    it('should prevent closing the modal while saving, then allow closing', async () => {
        let resolveCreate: (value: unknown) => void = () => {};
        const { wrapper } = await createWrapper({
            createTransfer: jest.fn(
                () =>
                    new Promise((resolve) => {
                        resolveCreate = resolve;
                    }),
            ),
        });
        await fillExistingRecipient(wrapper);
        await saveButton(wrapper).trigger('click');
        await flushPromises();
        expect(saveButton(wrapper).attributes('data-loading')).toBe('true');
        await wrapper.get('.modal-close').trigger('click');
        expect(wrapper.emitted('modal-close')).toBeUndefined();
        resolveCreate({ transfer: { id: 'transfer-1' }, replayed: false });
        await flushPromises();
        await wrapper.get('.modal-close').trigger('click');
        expect(wrapper.emitted('modal-close')).toHaveLength(1);
    });
});
