/** @sw-package framework */
import { STATE_LABELS, transfer, createWrapper, cleanupWrapper } from './sw-finance-transfer-detail.spec/create-wrapper';
import type { TransferApproval } from '../../service/payment-transfer.api.service';

describe('module/sw-finance/page/sw-finance-transfer-detail', () => {
    afterEach(() => {
        cleanupWrapper();
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
        expect(wrapper.find('.sw-finance-transfer-detail__button-reject').exists()).toBe(true);
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

    it.each([
        'pending',
        'rejected',
        'canceled',
        'unavailable',
        'not_started',
    ] as const)('should prevent executing a transfer with approval status %s', async (status) => {
        const { wrapper } = await createWrapper(
            { ...transfer, statusMachineState: { technicalName: 'created' } },
            undefined,
            {},
            { status, canCancel: false },
        );
        expect(wrapper.find('.sw-finance-transfer-detail__button-submit').exists()).toBe(false);
        expect(wrapper.find('.sw-finance-transfer-detail__button-reject').exists()).toBe(false);
        expect(wrapper.get('.sw-finance-transfer-detail__approval-status').text()).toContain(status);
    });

    it('should fail closed when approval cannot be loaded', async () => {
        const { wrapper } = await createWrapper(
            { ...transfer, statusMachineState: { technicalName: 'created' } },
            undefined,
            {},
            new Error('Offline'),
        );
        expect(wrapper.find('.sw-finance-transfer-detail__button-submit').exists()).toBe(false);
        expect(wrapper.get('.sw-finance-transfer-detail__approval-status').text()).toContain('unavailable');
    });

    it('should show only the runtime detail route provided by the server', async () => {
        const { wrapper } = await createWrapper(
            transfer,
            undefined,
            {},
            {
                status: 'pending',
                detailRoute: { name: 'test.runtime.detail', params: { id: 'instance-1' } },
            },
        );
        expect(wrapper.get('.sw-finance-transfer-detail__approval-link').attributes('data-route')).toBe(
            'test.runtime.detail',
        );
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
    it('shows frozen reviewed identities instead of changed recipient data for a bound transfer', async () => {
        const { wrapper } = await createWrapper(
            {
                ...transfer,
                recipient: { ...transfer.recipient, holderName: 'Changed payee', providerAccountId: 'changed-account' },
            },
            undefined,
            {},
            {
                status: 'pending',
                releaseId: 'release-1',
                recipientLabel: 'Reviewed payee · ****1234',
                paymentAccountLabel: 'Reviewed merchant account',
            },
        );
        expect(wrapper.get('.sw-finance-transfer-detail__reviewed-recipient').text()).toBe('Reviewed payee · ****1234');
        expect(wrapper.get('.sw-finance-transfer-detail__reviewed-account').text()).toBe('Reviewed merchant account');
        expect(wrapper.find('[position-identifier="sw-finance-transfer-detail-recipient"]').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('Changed payee');
        expect(wrapper.text()).not.toContain('changed-account');
    });

    it('allows the initiating creator to cancel without execution privileges', async () => {
        const { wrapper } = await createWrapper(
            transfer,
            (privilege) => privilege !== 'payment_transfer.approver',
            {},
            { status: 'pending', canCancel: true },
        );
        expect(wrapper.find('.sw-finance-transfer-detail__button-submit').exists()).toBe(false);
        await wrapper.get('.sw-finance-transfer-detail__button-reject').trigger('click');
        expect(wrapper.find('.reject-modal').exists()).toBe(true);
    });
    it('reloads reused detail from approved A to pending B and closes A decision dialogs', async () => {
        const { wrapper, get, getApproval } = await createWrapper({
            ...transfer,
            statusMachineState: { technicalName: 'created' },
        });
        await wrapper.get('.sw-finance-transfer-detail__button-submit').trigger('click');
        await wrapper.get('.sw-finance-transfer-detail__button-reject').trigger('click');
        let resolveApproval: (value: Partial<TransferApproval>) => void = () => {};
        get.mockResolvedValueOnce({
            ...transfer,
            id: 'transfer-2',
            transferNo: 'T-2',
            statusMachineState: { technicalName: 'created' },
        });
        getApproval.mockReturnValueOnce(
            new Promise<Partial<TransferApproval>>((resolve) => {
                resolveApproval = resolve;
            }),
        );

        await wrapper.setProps({ transferId: 'transfer-2' });
        await flushPromises();
        expect(getApproval).toHaveBeenLastCalledWith('transfer-2');
        expect(get).toHaveBeenLastCalledWith('transfer-2', Shopwell.Context.api, expect.anything());
        expect(wrapper.text()).toContain('T-2');
        expect(wrapper.text()).not.toContain('T-1');
        expect(wrapper.find('.approve-modal').exists()).toBe(false);
        expect(wrapper.find('.reject-modal').exists()).toBe(false);
        expect(wrapper.find('.sw-finance-transfer-detail__button-submit').exists()).toBe(false);
        resolveApproval({ status: 'pending', canCancel: false });
        await flushPromises();
        expect(wrapper.get('.sw-finance-transfer-detail__approval-status').text()).toContain('pending');
        expect(wrapper.find('.sw-finance-transfer-detail__button-submit').exists()).toBe(false);
    });

    it.each([
        ['success', 'transfer-2'],
        ['failure', 'transfer-2'],
        ['success', 'transfer-1'],
        ['failure', 'transfer-1'],
    ])('ignores late A %s after navigating through B to %s', async (outcome, targetId) => {
        const { wrapper, get, getApproval } = await createWrapper({
            ...transfer,
            statusMachineState: { technicalName: 'created' },
        });
        let settleTransfer: () => void = () => {};
        let settleApproval: () => void = () => {};
        get.mockReturnValueOnce(
            new Promise((resolve, reject) => {
                settleTransfer = () => (outcome === 'success' ? resolve(transfer) : reject(new Error('A failed')));
            }),
        );
        getApproval.mockReturnValueOnce(
            new Promise((resolve, reject) => {
                settleApproval = () =>
                    outcome === 'success' ? resolve({ status: 'approved', canCancel: true }) : reject(new Error('A failed'));
            }),
        );
        await wrapper
            .findAll('button')
            .filter((button) => button.text() === 'sw-finance.transfer.detail.buttonRefresh')[0]
            .trigger('click');
        get.mockResolvedValueOnce({
            ...transfer,
            id: 'transfer-2',
            transferNo: 'T-2',
            statusMachineState: { technicalName: 'created' },
        });
        getApproval.mockResolvedValueOnce({ status: 'pending', canCancel: false });
        await wrapper.setProps({ transferId: 'transfer-2' });
        await flushPromises();
        if (targetId === 'transfer-1') {
            get.mockResolvedValueOnce({
                ...transfer,
                transferNo: 'T-1-new',
                statusMachineState: { technicalName: 'created' },
            });
            getApproval.mockResolvedValueOnce({ status: 'pending', canCancel: false });
            await wrapper.setProps({ transferId: 'transfer-1' });
            await flushPromises();
        }
        settleTransfer();
        settleApproval();
        await flushPromises();
        expect(wrapper.get('[position-identifier="sw-finance-transfer-detail-general"] dd').text()).toBe(
            targetId === 'transfer-1' ? 'T-1-new' : 'T-2',
        );
        expect(wrapper.get('.sw-finance-transfer-detail__approval-status').text()).toContain('pending');
        expect(wrapper.find('.sw-finance-transfer-detail__button-submit').exists()).toBe(false);
        expect(wrapper.find('.sw-finance-transfer-detail__button-reject').exists()).toBe(false);
    });
});
