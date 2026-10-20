/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';

const transfer = {
    id: 'transfer-1',
    transferNo: 'T-1',
    outNo: 'O-1',
};

type Notification = {
    variant: string;
    message: string;
};

type RejectModalVm = {
    reason: string;
    reasonLength: number;
    reasonLimit: number;
    rejectInProgress: boolean;
    isReasonTooLong: boolean;
    isConfirmDisabled: boolean;
    onClose: () => void;
    onConfirm: () => void;
    createNotification: jest.Mock<void, [Notification]>;
};

type TypedWrapper = { vm: RejectModalVm } & VueWrapper;

let activeWrapper: VueWrapper | null = null;

async function createWrapper(
    cancelTransfer: jest.Mock,
): Promise<{ wrapper: TypedWrapper; notify: jest.Mock<void, [Notification]> }> {
    const wrapper = mount(await wrapTestComponent('sw-finance-transfer-reject-modal', { sync: true }), {
        props: {
            transfer,
        },
        global: {
            stubs: {
                'sw-modal': {
                    template: '<div><slot></slot><slot name="modal-footer"></slot></div>',
                },
                'mt-banner': { template: '<div><slot></slot></div>' },
                'mt-textarea': true,
                'mt-button': true,
            },
            provide: {
                paymentTransferApiService: { cancelTransfer },
            },
        },
    }) as unknown as TypedWrapper;

    const notify = jest.fn<void, [Notification]>();
    wrapper.vm.createNotification = notify;

    activeWrapper = wrapper;

    await flushPromises();

    return { wrapper, notify };
}

describe('module/sw-finance/component/sw-finance-transfer-reject-modal', () => {
    afterEach(() => {
        activeWrapper?.unmount();
        activeWrapper = null;
    });

    /**
     * The reason is the only record a rejected payout has - without one the button does nothing.
     */
    it('should refuse to reject without a reason', async () => {
        const cancelTransfer = jest.fn();
        const { wrapper } = await createWrapper(cancelTransfer);

        expect(wrapper.vm.isConfirmDisabled).toBe(true);

        wrapper.vm.reason = '   ';
        await flushPromises();
        expect(wrapper.vm.isConfirmDisabled).toBe(true);

        wrapper.vm.onConfirm();
        await flushPromises();
        expect(cancelTransfer).not.toHaveBeenCalled();

        wrapper.vm.reason = '收款账号有误';
        await flushPromises();
        expect(wrapper.vm.isConfirmDisabled).toBe(false);
    });

    it('should refuse a reason longer than the column allows', async () => {
        const { wrapper } = await createWrapper(jest.fn());

        wrapper.vm.reason = 'a'.repeat(256);
        await flushPromises();

        expect(wrapper.vm.isReasonTooLong).toBe(true);
        expect(wrapper.vm.isConfirmDisabled).toBe(true);
        expect(wrapper.vm.reasonLimit).toBe(255);
    });

    it('should send the trimmed reason and close afterwards', async () => {
        const cancelTransfer = jest.fn(() => Promise.resolve({ outcome: 'canceled', transfer }));
        const { wrapper, notify } = await createWrapper(cancelTransfer);

        wrapper.vm.reason = '  收款账号有误  ';
        await flushPromises();

        wrapper.vm.onConfirm();
        await flushPromises();

        expect(cancelTransfer).toHaveBeenCalledWith('transfer-1', '收款账号有误');
        expect(notify.mock.calls[0][0].variant).toBe('success');
        expect(wrapper.emitted('transfer-decided')).toHaveLength(1);
        expect(wrapper.vm.rejectInProgress).toBe(false);
    });

    /**
     * A rejection is final - a second click would only produce a second 409.
     */
    it('should reject exactly once and stay locked while the request runs', async () => {
        let resolveCancel: (value: unknown) => void = () => {};
        const cancelTransfer = jest.fn(
            () =>
                new Promise((resolve) => {
                    resolveCancel = resolve;
                }),
        );

        const { wrapper } = await createWrapper(cancelTransfer);

        wrapper.vm.reason = '收款账号有误';
        await flushPromises();

        wrapper.vm.onConfirm();
        wrapper.vm.onConfirm();
        await flushPromises();

        expect(cancelTransfer).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.rejectInProgress).toBe(true);

        wrapper.vm.onClose();
        expect(wrapper.emitted('modal-close')).toBeUndefined();

        resolveCancel({ outcome: 'canceled', transfer });
        await flushPromises();

        expect(wrapper.vm.rejectInProgress).toBe(false);
    });

    it('should show the readable error of a failed request', async () => {
        const cancelTransfer = jest.fn(() =>
            // the admin api rejects with a response object, not with an Error - the message of the
            // server is the whole point of the assertion below
            // eslint-disable-next-line @typescript-eslint/prefer-promise-reject-errors
            Promise.reject({ response: { data: { errors: [{ detail: 'PAYMENT__TRANSFER_ALREADY_FINALIZED' }] } } }),
        );

        const { wrapper, notify } = await createWrapper(cancelTransfer);

        wrapper.vm.reason = '收款账号有误';
        await flushPromises();

        wrapper.vm.onConfirm();
        await flushPromises();

        expect(notify.mock.calls[0][0].variant).toBe('error');
        expect(notify.mock.calls[0][0].message).toBe('PAYMENT__TRANSFER_ALREADY_FINALIZED');
        expect(wrapper.emitted('transfer-decided')).toBeUndefined();
    });
});
