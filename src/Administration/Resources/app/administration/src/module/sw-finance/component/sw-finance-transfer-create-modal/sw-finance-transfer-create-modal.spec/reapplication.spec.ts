/** @sw-package framework */
import { apps, createWrapper, field, input, select, saveButton, cleanupWrapper } from './create-wrapper';

describe('reapplying a transfer', () => {
    afterEach(() => {
        cleanupWrapper();
        jest.restoreAllMocks();
    });

    const initialDraft = {
        paymentAppId: 'app-1',
        accountId: 'alipay-account',
        channel: 'alipay_transfer',
        recipientId: 'recipient-1',
        amount: 12345,
        currency: 'CNY',
        note: 'Correct the invoice',
    };

    it('prefills an editable form, previews it and books through the reapplication endpoint with a fresh stable out number', async () => {
        const { wrapper, reapplyTransfer, createTransfer } = await createWrapper(
            {},
            { initialDraft, sourceTransferId: 'old-transfer' },
        );
        expect((field(wrapper, 'recipient').element as HTMLSelectElement).value).toBe('recipient-1');
        expect(field(wrapper, 'amount').get('input').element.value).toBe('123.45');
        expect(wrapper.get('.preview-probe').text()).toContain('12345 CNY alipay-account recipient-1');
        await input(wrapper, 'amount', '125.01');
        await saveButton(wrapper).trigger('click');
        await flushPromises();
        expect(createTransfer).not.toHaveBeenCalled();
        const [source, payload] = reapplyTransfer.mock.calls[0] as [string, { amount: number; outNo: string }];
        expect(source).toBe('old-transfer');
        expect(payload.amount).toBe(12501);
        expect(payload.outNo).toMatch(/^[a-zA-Z0-9]{1,30}$/);
        expect(wrapper.emitted('transfer-created')?.[0]).toEqual([{ id: 'new-transfer' }]);
    });

    it('submits an Approval Flow reapplication without loading the legacy preview', async () => {
        const { wrapper, reapplyTransfer } = await createWrapper(
            {
                getOptions: jest.fn().mockResolvedValue({
                    apps,
                    defaultAppId: 'app-1',
                    workflowAvailable: true,
                    approvalFlowActive: true,
                }),
            },
            { initialDraft, sourceTransferId: 'old-transfer', sourceUsesApprovalFlow: true },
        );

        expect(wrapper.find('.preview-probe').exists()).toBe(false);
        await saveButton(wrapper).trigger('click');
        await flushPromises();

        expect(reapplyTransfer).toHaveBeenCalledWith('old-transfer', expect.objectContaining({ amount: 12345 }));
    });

    it('requires reselecting an unavailable account instead of silently using another account', async () => {
        const { wrapper } = await createWrapper(
            {},
            { initialDraft: { ...initialDraft, accountId: 'disabled-account' }, sourceTransferId: 'old-transfer' },
        );
        expect((field(wrapper, 'account').element as HTMLSelectElement).value).toBe('');
        expect(wrapper.find('.sw-finance-transfer-create-modal__field-recipient').exists()).toBe(false);
        expect(saveButton(wrapper).attributes('disabled')).toBeDefined();
        expect(wrapper.text()).toContain('sw-finance.transfer.reapply.selectionUnavailable');
    });
    it('lets the user replace an unavailable channel and currency even when only one option remains', async () => {
        const { wrapper } = await createWrapper(
            {},
            {
                initialDraft: { ...initialDraft, channel: 'removed-channel', currency: 'USD' },
                sourceTransferId: 'old-transfer',
            },
        );
        expect(field(wrapper, 'channel').attributes('disabled')).toBeUndefined();
        expect(field(wrapper, 'currency').attributes('disabled')).toBeUndefined();
        await select(wrapper, 'channel', 'alipay_transfer');
        await select(wrapper, 'recipient', 'recipient-1');
        await select(wrapper, 'currency', 'CNY');
        expect(saveButton(wrapper).attributes('disabled')).toBeUndefined();
    });

    it('keeps the new out number stable after a failed submission so retrying cannot create a second request', async () => {
        const reapplyTransfer = jest
            .fn()
            .mockRejectedValueOnce(new Error('Connection lost'))
            .mockResolvedValueOnce({ transfer: { id: 'new-transfer' }, replayed: true });
        const { wrapper } = await createWrapper({ reapplyTransfer }, { initialDraft, sourceTransferId: 'old-transfer' });
        await saveButton(wrapper).trigger('click');
        await flushPromises();
        expect(wrapper.emitted('transfer-created')).toBeUndefined();
        await saveButton(wrapper).trigger('click');
        await flushPromises();
        const calls = reapplyTransfer.mock.calls as Array<[string, { outNo: string }]>;
        expect(calls).toHaveLength(2);
        expect(calls[0][1].outNo).toMatch(/^[a-zA-Z0-9]{1,30}$/);
        expect(calls[1][1].outNo).toBe(calls[0][1].outNo);
        expect(wrapper.emitted('transfer-created')?.[0]).toEqual([{ id: 'new-transfer' }]);
    });
});
