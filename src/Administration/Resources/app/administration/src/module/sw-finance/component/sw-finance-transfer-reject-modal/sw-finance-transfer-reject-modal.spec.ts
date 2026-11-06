/**
 * @sw-package framework
 */
import { shallowMount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';

const transfer = {
    id: 'transfer-1',
    transferNo: 'T-1',
    outNo: 'O-1',
};

let activeWrapper: VueWrapper | null = null;

async function createWrapper(cancelTransfer: jest.Mock): Promise<{ wrapper: VueWrapper; notify: jest.SpyInstance }> {
    const notify = jest.spyOn(Shopwell.Store.get('notification'), 'createNotification').mockReturnValue('notification-1');
    const wrapper = shallowMount(await wrapTestComponent('sw-finance-transfer-reject-modal', { sync: true }), {
        props: {
            transfer,
        },
        global: {
            stubs: {
                'sw-modal': {
                    template:
                        '<div><slot></slot><slot name="modal-footer"></slot><button class="modal-close" @click="$emit(\'modal-close\')">Close</button></div>',
                },
                'mt-banner': { template: '<div><slot></slot></div>' },
                'mt-textarea': {
                    props: ['modelValue', 'error', 'maxLength'],
                    template:
                        '<label><textarea :value="modelValue" :maxlength="maxLength" @input="$emit(\'update:modelValue\', $event.target.value)"></textarea><span v-if="error" role="alert">{{ error }}</span></label>',
                },
                'mt-button': {
                    props: ['disabled', 'isLoading'],
                    template: '<button :disabled="disabled" :data-loading="isLoading"><slot></slot></button>',
                },
            },
            provide: {
                paymentTransferApiService: { cancelTransfer },
            },
        },
    });

    activeWrapper = wrapper;

    await flushPromises();

    return { wrapper, notify };
}

describe('module/sw-finance/component/sw-finance-transfer-reject-modal', () => {
    afterEach(() => {
        activeWrapper?.unmount();
        activeWrapper = null;
        jest.restoreAllMocks();
    });

    /**
     * The reason is the only record a rejected payout has - without one the button does nothing.
     */
    it('should refuse to reject without a reason', async () => {
        const cancelTransfer = jest.fn();
        const { wrapper } = await createWrapper(cancelTransfer);

        expect(wrapper.get('.sw-finance-transfer-reject-modal__button-confirm').attributes('disabled') !== undefined).toBe(
            true,
        );

        await wrapper.get('textarea').setValue('   ');
        await flushPromises();
        expect(wrapper.get('.sw-finance-transfer-reject-modal__button-confirm').attributes('disabled') !== undefined).toBe(
            true,
        );

        await wrapper.get('.sw-finance-transfer-reject-modal__button-confirm').trigger('click');
        await flushPromises();
        expect(cancelTransfer).not.toHaveBeenCalled();

        await wrapper.get('textarea').setValue('收款账号有误');
        await flushPromises();
        expect(wrapper.get('.sw-finance-transfer-reject-modal__button-confirm').attributes('disabled') !== undefined).toBe(
            false,
        );
    });

    it('should refuse a reason longer than the column allows', async () => {
        const { wrapper } = await createWrapper(jest.fn());

        await wrapper.get('textarea').setValue('a'.repeat(256));
        await flushPromises();

        expect(wrapper.find('[role="alert"]').exists()).toBe(true);
        expect(wrapper.get('.sw-finance-transfer-reject-modal__button-confirm').attributes('disabled') !== undefined).toBe(
            true,
        );
        expect(wrapper.get('textarea').attributes('maxlength')).toBe('255');
        expect(wrapper.get('.sw-finance-transfer-reject-modal__counter').text()).toBe('256 / 255');
    });

    it('should send the trimmed reason and close afterwards', async () => {
        const cancelTransfer = jest.fn(() => Promise.resolve({ outcome: 'canceled', transfer }));
        const { wrapper, notify } = await createWrapper(cancelTransfer);

        await wrapper.get('textarea').setValue('  收款账号有误  ');
        await flushPromises();

        await wrapper.get('.sw-finance-transfer-reject-modal__button-confirm').trigger('click');
        await flushPromises();

        expect(cancelTransfer).toHaveBeenCalledWith('transfer-1', '收款账号有误');
        expect(notify).toHaveBeenCalledWith(expect.objectContaining({ variant: 'success' }));
        expect(wrapper.emitted('transfer-decided')).toHaveLength(1);
        expect(wrapper.get('.sw-finance-transfer-reject-modal__button-confirm').attributes('data-loading') === 'true').toBe(
            false,
        );
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

        await wrapper.get('textarea').setValue('收款账号有误');
        await flushPromises();

        await wrapper.get('.sw-finance-transfer-reject-modal__button-confirm').trigger('click');
        await wrapper.get('.sw-finance-transfer-reject-modal__button-confirm').trigger('click');
        await flushPromises();

        expect(cancelTransfer).toHaveBeenCalledTimes(1);
        expect(wrapper.get('.sw-finance-transfer-reject-modal__button-confirm').attributes('data-loading') === 'true').toBe(
            true,
        );

        await wrapper.get('.modal-close').trigger('click');
        expect(wrapper.emitted('modal-close')).toBeUndefined();

        resolveCancel({ outcome: 'canceled', transfer });
        await flushPromises();

        expect(wrapper.get('.sw-finance-transfer-reject-modal__button-confirm').attributes('data-loading') === 'true').toBe(
            false,
        );
    });

    it('should show the readable error of a failed request', async () => {
        const cancelTransfer = jest.fn(() =>
            Promise.reject(
                Object.assign(new Error('Transfer failed'), {
                    response: { data: { errors: [{ detail: 'PAYMENT__TRANSFER_ALREADY_FINALIZED' }] } },
                }),
            ),
        );

        const { wrapper, notify } = await createWrapper(cancelTransfer);

        await wrapper.get('textarea').setValue('收款账号有误');
        await flushPromises();

        await wrapper.get('.sw-finance-transfer-reject-modal__button-confirm').trigger('click');
        await flushPromises();

        expect(notify).toHaveBeenCalledWith(expect.objectContaining({ variant: 'error' }));
        expect(notify).toHaveBeenCalledWith(expect.objectContaining({ message: 'PAYMENT__TRANSFER_ALREADY_FINALIZED' }));
        expect(wrapper.emitted('transfer-decided')).toBeUndefined();
    });
});
