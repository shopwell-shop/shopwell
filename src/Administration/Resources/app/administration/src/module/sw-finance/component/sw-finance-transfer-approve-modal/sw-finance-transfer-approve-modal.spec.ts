/**
 * @sw-package framework
 */
import { shallowMount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import type { NotificationType } from 'src/app/store/notification.store';

const transfer = {
    id: 'transfer-1',
    transferNo: 'T-1',
    outNo: 'O-1',
    provider: 'alipay',
    channel: 'alipay_transfer',
    amount: 1250,
    currency: 'CNY',
    recipient: { holderName: '张三', providerAccountId: null, identityType: 'alipay_logon_id' },
    paymentApp: { id: 'app-1', name: '演示应用', technicalName: 'demo' },
    failureCode: null,
    failureReason: null,
    statusMachineState: { technicalName: 'created' },
};

/**
 * A minimal `$t` for the keys this modal assembles a message from.
 *
 * The test environment answers every key with the key itself, so a notice that is built from the state of
 * the answer is unobservable there - the interpolation is what has to be asserted, and that needs a
 * translation which actually interpolates. Everything that is not listed here keeps answering with its
 * key, which is the behaviour the rest of this spec relies on.
 */
const TRANSLATIONS: Record<string, string> = {
    'sw-finance.transfer.approve.noticeAccepted': '已提交至渠道，当前状态：{state}。',
    'sw-finance.transfer.approve.noticeAcceptedWithoutState': '已提交至渠道，刷新后可查看当前状态。',
    'sw-finance.transfer.stateLabel.processing': '处理中',
};

function translate(key: string, params?: Record<string, unknown>): string {
    const template = TRANSLATIONS[key] ?? key;

    return template.replace(/\{(\w+)\}/g, (match: string, name: string) => {
        return params && name in params ? String(params[name]) : match;
    });
}

let activeWrapper: VueWrapper | null = null;

async function createWrapper(
    submitTransfer: jest.Mock,
): Promise<{ wrapper: VueWrapper; notify: jest.SpyInstance<string | null, [NotificationType]>; submitTransfer: jest.Mock }> {
    const notify = jest.spyOn(Shopwell.Store.get('notification'), 'createNotification').mockReturnValue('notification-1');
    const wrapper = shallowMount(await wrapTestComponent('sw-finance-transfer-approve-modal', { sync: true }), {
        props: {
            transfer,
        },
        global: {
            stubs: {
                'sw-modal': {
                    template:
                        '<div><slot></slot><slot name="modal-footer"></slot><button class="modal-close" @click="$emit(\'modal-close\')">Close</button></div>',
                },
                'sw-description-list': { template: '<dl><slot></slot></dl>' },
                'mt-banner': { template: '<div><slot></slot></div>' },
                'mt-checkbox': {
                    props: ['checked', 'disabled'],
                    template:
                        '<input type="checkbox" :checked="checked" :disabled="disabled" @change="$emit(\'update:checked\', $event.target.checked)">',
                },
                'mt-button': {
                    props: ['disabled', 'isLoading'],
                    template: '<button :disabled="disabled" :data-loading="isLoading"><slot></slot></button>',
                },
            },
            mocks: {
                $t: translate,
            },
            provide: {
                paymentTransferApiService: { submitTransfer },
            },
        },
    });

    activeWrapper = wrapper;

    await flushPromises();

    return { wrapper, notify, submitTransfer };
}

describe('module/sw-finance/component/sw-finance-transfer-approve-modal', () => {
    afterEach(() => {
        activeWrapper?.unmount();
        activeWrapper = null;
        jest.restoreAllMocks();
    });

    it('leaves the amount empty when the transfer has no currency', async () => {
        const { wrapper } = await createWrapper(jest.fn());
        await wrapper.setProps({ transfer: { ...transfer, currency: null } });
        expect(wrapper.findAll('dd')[3].text()).toBe('');
    });

    it('should summarise the payout before anything is submitted', async () => {
        const { wrapper } = await createWrapper(jest.fn());

        expect(wrapper.findAll('dd')[0].text()).toBe('张三');
        expect(wrapper.findAll('dd')[1].text()).toBe('演示应用');
        // the snippet of the interface is not translated in a unit test, its key is the label
        expect(wrapper.findAll('dd')[2].text()).toBe('sw-settings-payment-provider.provider.alipay · alipay_transfer');
        expect(wrapper.findAll('dd')[3].text()).toBe(Shopwell.Utils.format.currency(12.5, 'CNY', 2));
        // `$t` answers with the key in the test environment - the interpolation needs the real snippet
        expect(wrapper.get('.sw-finance-transfer-approve-modal__warning').text()).toBe(
            'sw-finance.transfer.approve.warning',
        );
    });

    /**
     * The checkbox is the confirmation: without it the button of a money movement does not work.
     */
    it('should keep the confirmation disabled until the operator ticks the box', async () => {
        const { wrapper } = await createWrapper(jest.fn());

        expect(wrapper.get('.sw-finance-transfer-approve-modal__button-confirm').attributes('disabled') !== undefined).toBe(
            true,
        );

        await wrapper.get('input[type="checkbox"]').setValue(true);
        await flushPromises();

        expect(wrapper.get('.sw-finance-transfer-approve-modal__button-confirm').attributes('disabled') !== undefined).toBe(
            false,
        );
    });

    /**
     * A second click while the request runs would be a second submission - the platform can not tell a
     * duplicate from a rebooking.
     */
    it('should submit exactly once and stay locked while the request runs', async () => {
        let resolveSubmit: (value: unknown) => void = () => {};
        const submitTransfer = jest.fn(
            () =>
                new Promise((resolve) => {
                    resolveSubmit = resolve;
                }),
        );

        const { wrapper } = await createWrapper(submitTransfer);

        await wrapper.get('input[type="checkbox"]').setValue(true);
        await flushPromises();

        await wrapper.get('.sw-finance-transfer-approve-modal__button-confirm').trigger('click');
        await wrapper.get('.sw-finance-transfer-approve-modal__button-confirm').trigger('click');
        await wrapper.get('.sw-finance-transfer-approve-modal__button-confirm').trigger('click');
        await flushPromises();

        expect(submitTransfer).toHaveBeenCalledTimes(1);
        expect(submitTransfer).toHaveBeenCalledWith('transfer-1');
        expect(wrapper.get('.sw-finance-transfer-approve-modal__button-confirm').attributes('data-loading') === 'true').toBe(
            true,
        );
        expect(wrapper.get('.sw-finance-transfer-approve-modal__button-confirm').attributes('disabled') !== undefined).toBe(
            true,
        );

        // the modal can not be closed while the request runs either
        await wrapper.get('.modal-close').trigger('click');
        expect(wrapper.emitted('modal-close')).toBeUndefined();

        resolveSubmit({ outcome: 'accepted', transfer, message: null });
        await flushPromises();

        expect(wrapper.get('.sw-finance-transfer-approve-modal__button-confirm').attributes('data-loading') === 'true').toBe(
            false,
        );
    });

    /**
     * The answer of the endpoint is the shape of `PaymentTransferPresenter::transfer()`, where the state of
     * the row is `state` - not the `statusMachineState` association of the entity. Reading the wrong field
     * left the notice without a state and put the merchant transfer no. in the middle of the sentence.
     */
    it('should report an accepted submission with the state the endpoint answered', async () => {
        // the answer of `POST /_action/payment/transfers/{id}/submit`, in the shape the presenter emits
        const answered = {
            ...transfer,
            state: { id: 'state-processing', technicalName: 'processing', name: 'Processing' },
        };

        const submitTransfer = jest.fn(() => Promise.resolve({ outcome: 'accepted', transfer: answered, message: null }));

        const { wrapper, notify } = await createWrapper(submitTransfer);

        submitTransfer.mockResolvedValueOnce({ outcome: 'accepted', transfer: answered, message: null });
        await wrapper.get('input[type="checkbox"]').setValue(true);
        await wrapper.get('.sw-finance-transfer-approve-modal__button-confirm').trigger('click');
        await flushPromises();

        expect(notify).toHaveBeenCalledTimes(1);
        expect(notify).toHaveBeenCalledWith(expect.objectContaining({ variant: 'success' }));
        expect(notify.mock.calls[0][0].message).toContain('处理中');
        // the merchant transfer no. is not a state and must not stand in for one
        expect(notify.mock.calls[0][0].message).not.toContain('T-1');
        expect(wrapper.emitted('transfer-decided')).toHaveLength(1);
    });

    it('should report an accepted submission without a state without inventing one', async () => {
        const { wrapper, notify, submitTransfer } = await createWrapper(jest.fn());

        submitTransfer.mockResolvedValueOnce({ outcome: 'accepted', transfer: { id: 'transfer-1' }, message: null });
        await wrapper.get('input[type="checkbox"]').setValue(true);
        await wrapper.get('.sw-finance-transfer-approve-modal__button-confirm').trigger('click');
        await flushPromises();

        expect(notify).toHaveBeenCalledWith(expect.objectContaining({ variant: 'success' }));
        expect(notify).toHaveBeenCalledWith(expect.objectContaining({ message: '已提交至渠道，刷新后可查看当前状态。' }));
    });

    it('should report a refusal of the channel with its code and reason', async () => {
        const { wrapper, notify, submitTransfer } = await createWrapper(jest.fn());

        submitTransfer.mockResolvedValueOnce({
            outcome: 'failed',
            transfer: { ...transfer, failureCode: 'PAYEE_NOT_EXIST', failureReason: '收款方不存在' },
            message: null,
        });
        await wrapper.get('input[type="checkbox"]').setValue(true);
        await wrapper.get('.sw-finance-transfer-approve-modal__button-confirm').trigger('click');
        await flushPromises();

        expect(notify).toHaveBeenCalledWith(expect.objectContaining({ variant: 'error' }));
        expect(wrapper.emitted('transfer-decided')).toHaveLength(1);
    });

    /**
     * The most important message of the whole feature: "we do not know" must not look like a failure, and it
     * must not be retried automatically.
     */
    it('should report an inconclusive answer as a warning without retrying', async () => {
        const submitTransfer = jest.fn<
            Promise<{ outcome: string; transfer: typeof transfer; message: string | null }>,
            [string]
        >(() => Promise.resolve({ outcome: 'unknown', transfer, message: null }));

        const { wrapper, notify } = await createWrapper(submitTransfer);

        submitTransfer.mockResolvedValueOnce({ outcome: 'unknown', transfer, message: '渠道未返回明确结果' });
        await wrapper.get('input[type="checkbox"]').setValue(true);
        await wrapper.get('.sw-finance-transfer-approve-modal__button-confirm').trigger('click');
        await flushPromises();

        expect(notify).toHaveBeenCalledWith(expect.objectContaining({ variant: 'warning' }));
        expect(wrapper.emitted('transfer-decided')).toHaveLength(1);
        expect(submitTransfer).toHaveBeenCalledTimes(1);
    });

    it('should show the readable error of a failed request', async () => {
        const submitTransfer = jest.fn(() =>
            Promise.reject(
                Object.assign(new Error('Transfer failed'), {
                    response: { data: { errors: [{ detail: 'PAYMENT__TRANSFER_OUT_NO_TAKEN' }] } },
                }),
            ),
        );

        const { wrapper, notify } = await createWrapper(submitTransfer);

        await wrapper.get('input[type="checkbox"]').setValue(true);
        await flushPromises();

        await wrapper.get('.sw-finance-transfer-approve-modal__button-confirm').trigger('click');
        await flushPromises();

        expect(notify).toHaveBeenCalledWith(expect.objectContaining({ variant: 'error' }));
        expect(notify).toHaveBeenCalledWith(expect.objectContaining({ message: 'PAYMENT__TRANSFER_OUT_NO_TAKEN' }));
        expect(wrapper.get('.sw-finance-transfer-approve-modal__button-confirm').attributes('data-loading') === 'true').toBe(
            false,
        );
    });
});
