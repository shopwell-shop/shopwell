/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';

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

type Notification = {
    variant: string;
    message: string;
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

type ApproveModalVm = {
    confirmed: boolean;
    submitInProgress: boolean;
    providerLabel: string;
    channelLabel: string;
    amountLabel: string;
    recipientLabel: string;
    appLabel: string;
    warningText: string;
    isConfirmDisabled: boolean;
    onClose: () => void;
    onConfirm: () => void;
    handleOutcome: (result: unknown) => void;
    createNotification: jest.Mock<void, [Notification]>;
};

type TypedWrapper = { vm: ApproveModalVm } & VueWrapper;

let activeWrapper: VueWrapper | null = null;

async function createWrapper(
    submitTransfer: jest.Mock,
): Promise<{ wrapper: TypedWrapper; notify: jest.Mock<void, [Notification]> }> {
    const wrapper = mount(await wrapTestComponent('sw-finance-transfer-approve-modal', { sync: true }), {
        props: {
            transfer,
        },
        global: {
            stubs: {
                'sw-modal': {
                    template: '<div><slot></slot><slot name="modal-footer"></slot></div>',
                },
                'sw-description-list': { template: '<dl><slot></slot></dl>' },
                'mt-banner': { template: '<div><slot></slot></div>' },
                'mt-checkbox': true,
                'mt-button': true,
            },
            mocks: {
                $t: translate,
            },
            provide: {
                paymentTransferApiService: { submitTransfer },
            },
        },
    }) as unknown as TypedWrapper;

    const notify = jest.fn<void, [Notification]>();
    wrapper.vm.createNotification = notify;

    activeWrapper = wrapper;

    await flushPromises();

    return { wrapper, notify };
}

describe('module/sw-finance/component/sw-finance-transfer-approve-modal', () => {
    afterEach(() => {
        activeWrapper?.unmount();
        activeWrapper = null;
    });

    it('should summarise the payout before anything is submitted', async () => {
        const { wrapper } = await createWrapper(jest.fn());

        expect(wrapper.vm.recipientLabel).toBe('张三');
        expect(wrapper.vm.appLabel).toBe('演示应用');
        // the snippet of the interface is not translated in a unit test, its key is the label
        expect(wrapper.vm.channelLabel).toBe('sw-settings-payment-provider.provider.alipay · alipay_transfer');
        expect(wrapper.vm.amountLabel).toBe(Shopwell.Utils.format.currency(12.5, 'CNY', 2));
        // `$t` answers with the key in the test environment - the interpolation needs the real snippet
        expect(wrapper.vm.warningText).toBe('sw-finance.transfer.approve.warning');
    });

    /**
     * The checkbox is the confirmation: without it the button of a money movement does not work.
     */
    it('should keep the confirmation disabled until the operator ticks the box', async () => {
        const { wrapper } = await createWrapper(jest.fn());

        expect(wrapper.vm.isConfirmDisabled).toBe(true);

        wrapper.vm.confirmed = true;
        await flushPromises();

        expect(wrapper.vm.isConfirmDisabled).toBe(false);
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

        wrapper.vm.confirmed = true;
        await flushPromises();

        wrapper.vm.onConfirm();
        wrapper.vm.onConfirm();
        wrapper.vm.onConfirm();
        await flushPromises();

        expect(submitTransfer).toHaveBeenCalledTimes(1);
        expect(submitTransfer).toHaveBeenCalledWith('transfer-1');
        expect(wrapper.vm.submitInProgress).toBe(true);
        expect(wrapper.vm.isConfirmDisabled).toBe(true);

        // the modal can not be closed while the request runs either
        wrapper.vm.onClose();
        expect(wrapper.emitted('modal-close')).toBeUndefined();

        resolveSubmit({ outcome: 'accepted', transfer, message: null });
        await flushPromises();

        expect(wrapper.vm.submitInProgress).toBe(false);
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

        wrapper.vm.handleOutcome({ outcome: 'accepted', transfer: answered, message: null });
        await flushPromises();

        expect(notify).toHaveBeenCalledTimes(1);
        expect(notify.mock.calls[0][0].variant).toBe('success');
        expect(notify.mock.calls[0][0].message).toContain('处理中');
        // the merchant transfer no. is not a state and must not stand in for one
        expect(notify.mock.calls[0][0].message).not.toContain('T-1');
        expect(wrapper.emitted('transfer-decided')).toHaveLength(1);
    });

    it('should report an accepted submission without a state without inventing one', async () => {
        const { wrapper, notify } = await createWrapper(jest.fn());

        wrapper.vm.handleOutcome({ outcome: 'accepted', transfer: { id: 'transfer-1' }, message: null });
        await flushPromises();

        expect(notify.mock.calls[0][0].variant).toBe('success');
        expect(notify.mock.calls[0][0].message).toBe('已提交至渠道，刷新后可查看当前状态。');
    });

    it('should report a refusal of the channel with its code and reason', async () => {
        const { wrapper, notify } = await createWrapper(jest.fn());

        wrapper.vm.handleOutcome({
            outcome: 'failed',
            transfer: { ...transfer, failureCode: 'PAYEE_NOT_EXIST', failureReason: '收款方不存在' },
            message: null,
        });
        await flushPromises();

        expect(notify.mock.calls[0][0].variant).toBe('error');
        expect(wrapper.emitted('transfer-decided')).toHaveLength(1);
    });

    /**
     * The most important message of the whole feature: "we do not know" must not look like a failure, and it
     * must not be retried automatically.
     */
    it('should report an inconclusive answer as a warning without retrying', async () => {
        const submitTransfer = jest.fn(() => Promise.resolve({ outcome: 'unknown', transfer, message: null }));

        const { wrapper, notify } = await createWrapper(submitTransfer);

        wrapper.vm.handleOutcome({ outcome: 'unknown', transfer, message: '渠道未返回明确结果' });
        await flushPromises();

        expect(notify.mock.calls[0][0].variant).toBe('warning');
        expect(wrapper.emitted('transfer-decided')).toHaveLength(1);
        expect(submitTransfer).not.toHaveBeenCalled();
    });

    it('should show the readable error of a failed request', async () => {
        const submitTransfer = jest.fn(() =>
            // the admin api rejects with a response object, not with an Error - the message of the
            // server is the whole point of the assertion below
            // eslint-disable-next-line @typescript-eslint/prefer-promise-reject-errors
            Promise.reject({ response: { data: { errors: [{ detail: 'PAYMENT__TRANSFER_OUT_NO_TAKEN' }] } } }),
        );

        const { wrapper, notify } = await createWrapper(submitTransfer);

        wrapper.vm.confirmed = true;
        await flushPromises();

        wrapper.vm.onConfirm();
        await flushPromises();

        expect(notify.mock.calls[0][0].variant).toBe('error');
        expect(notify.mock.calls[0][0].message).toBe('PAYMENT__TRANSFER_OUT_NO_TAKEN');
        expect(wrapper.vm.submitInProgress).toBe(false);
    });
});
