/** @sw-package framework */
import {
    apps,
    createWrapper,
    field,
    select,
    input,
    saveButton,
    fillExistingRecipient,
    cleanupWrapper,
} from './sw-finance-transfer-create-modal.spec/create-wrapper';

describe('module/sw-finance/component/sw-finance-transfer-create-modal', () => {
    afterEach(() => {
        cleanupWrapper();
        jest.restoreAllMocks();
    });

    it('passes complete minor-unit input to preview and blocks a known unavailable approval', async () => {
        const { wrapper } = await createWrapper();
        expect(wrapper.get('.preview-probe span').text()).toBe('');
        await fillExistingRecipient(wrapper, '5000.01');
        expect(wrapper.get('.preview-probe span').text()).toBe('500001 CNY alipay-account recipient-1');
        await wrapper.get('.preview-block').trigger('click');
        expect(saveButton(wrapper).attributes('disabled')).toBeDefined();
        await wrapper.get('.preview-ready').trigger('click');
        expect(saveButton(wrapper).attributes('disabled')).toBeUndefined();
    });

    it('should preselect and lock the default app interface, channel and currency', async () => {
        const { wrapper } = await createWrapper();
        expect(wrapper.find('.sw-finance-transfer-create-modal__loading').exists()).toBe(false);
        expect((field(wrapper, 'app').element as HTMLInputElement).value).toBe('app-1');
        expect((field(wrapper, 'account').element as HTMLInputElement).value).toBe('alipay-account');
        expect((field(wrapper, 'channel').element as HTMLInputElement).value).toBe('alipay_transfer');
        expect(field(wrapper, 'channel').attributes('disabled')).toBeDefined();
        expect((field(wrapper, 'currency').element as HTMLInputElement).value).toBe('CNY');
        expect(field(wrapper, 'currency').attributes('disabled')).toBeDefined();
    });

    it('should distinguish two payment accounts of the same provider', async () => {
        const accounts = [
            apps[0].accounts[0],
            { ...apps[0].accounts[0], accountId: 'second-alipay', name: 'Second account' },
        ];
        const { wrapper, createTransfer } = await createWrapper({
            getOptions: jest
                .fn()
                .mockResolvedValue({ apps: [{ ...apps[0], accounts }], defaultAppId: 'app-1', workflowAvailable: true }),
        });
        expect(field(wrapper, 'account').text()).toContain('Second account');
        await select(wrapper, 'account', 'second-alipay');
        await fillExistingRecipient(wrapper);
        await saveButton(wrapper).trigger('click');
        expect(createTransfer).toHaveBeenCalledWith(
            expect.objectContaining({ accountId: 'second-alipay', provider: 'alipay' }),
        );
    });

    it('should clear recipient identities when switching the merchant app', async () => {
        const { wrapper, createTransfer } = await createWrapper();
        await select(wrapper, 'recipient-source', 'new');
        await select(wrapper, 'identity-type', 'alipay_logon_id');
        await input(wrapper, 'identity-value', 'zhangsan@example.com');
        await input(wrapper, 'holder-name', '张三');
        await select(wrapper, 'app', 'app-2');
        expect((field(wrapper, 'account').element as HTMLInputElement).value).toBe('');
        expect(field(wrapper, 'account').findAll('option')).toHaveLength(1);
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
        await select(wrapper, 'account', 'paypal-account');
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
        const japaneseApps = [{ ...apps[0], accounts: [{ ...apps[0].accounts[0], currencies: ['JPY'] }] }];
        const { wrapper, createTransfer } = await createWrapper({
            getOptions: jest.fn(() =>
                Promise.resolve({ apps: japaneseApps, defaultAppId: 'app-1', workflowAvailable: true }),
            ),
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
            accountId: 'alipay-account',
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

    it('should preserve a settlement amount and use its booking handler', async () => {
        const bookTransfer = jest.fn(() => Promise.resolve({ transfer: { id: 'settlement-transfer' }, replayed: false }));
        const { wrapper, createTransfer } = await createWrapper(
            { getOptions: jest.fn().mockResolvedValue({ apps, defaultAppId: 'app-1', workflowAvailable: false }) },
            { fixedAmount: '90.00', fixedCurrency: 'CNY', bookTransfer },
        );
        await select(wrapper, 'recipient', 'recipient-1');
        expect(field(wrapper, 'amount').get('input').element.value).toBe('90.00');
        expect(field(wrapper, 'amount').attributes('disabled')).toBeDefined();
        await saveButton(wrapper).trigger('click');
        await flushPromises();
        expect(bookTransfer).toHaveBeenCalledWith(expect.objectContaining({ amount: 9000, currency: 'CNY' }));
        expect(createTransfer).not.toHaveBeenCalled();
        expect(wrapper.emitted('transfer-created')).toEqual([[{ id: 'settlement-transfer' }]]);
    });

    it('should refuse a channel that cannot pay the fixed settlement currency', async () => {
        const { wrapper } = await createWrapper({}, { fixedAmount: '90.00', fixedCurrency: 'EUR' });
        await select(wrapper, 'recipient', 'recipient-1');
        expect(saveButton(wrapper).attributes('disabled')).toBeDefined();
        expect(field(wrapper, 'currency').attributes('error')).toBe(
            'sw-finance.transfer.create.validationCurrencyUnsupported',
        );
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
    it('prevents booking when no approval workflow is available', async () => {
        const { wrapper, createTransfer } = await createWrapper({
            getOptions: jest.fn().mockResolvedValue({ apps, defaultAppId: 'app-1', workflowAvailable: false }),
        });
        await fillExistingRecipient(wrapper);
        expect(saveButton(wrapper).attributes('disabled')).toBeDefined();
        await saveButton(wrapper).trigger('click');
        expect(createTransfer).not.toHaveBeenCalled();
    });
});
