/**
 * @sw-package framework
 */
import { shallowMount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';

/** @private */
export const apps = [
    {
        id: 'app-1',
        technicalName: 'platform',
        name: '平台自有',
        type: 'platform',
        isPlatform: true,
        accounts: [
            {
                technicalName: 'alipay',
                accountId: 'alipay-account',
                name: 'Alipay account',
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
                accountId: 'paypal-account',
                name: 'PayPal account',
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
        accounts: [],
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

/** @private */
export async function createWrapper(
    overrides: {
        getOptions?: jest.Mock;
        getRecipients?: jest.Mock;
        createTransfer?: jest.Mock;
        reapplyTransfer?: jest.Mock;
    } = {},
    props: Record<string, unknown> = {},
) {
    const getOptions =
        overrides.getOptions ?? jest.fn(() => Promise.resolve({ apps, defaultAppId: 'app-1', workflowAvailable: true }));
    const getRecipients = overrides.getRecipients ?? jest.fn(() => Promise.resolve(recipients));
    const createTransfer =
        overrides.createTransfer ?? jest.fn(() => Promise.resolve({ transfer: { id: 'transfer-1' }, replayed: false }));
    const reapplyTransfer =
        overrides.reapplyTransfer ?? jest.fn(() => Promise.resolve({ transfer: { id: 'new-transfer' }, replayed: false }));
    const notify = jest.spyOn(Shopwell.Store.get('notification'), 'createNotification').mockReturnValue('notification-1');
    const wrapper = shallowMount(await wrapTestComponent('sw-finance-transfer-create-modal', { sync: true }), {
        props,
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
                'sw-finance-transfer-approval-preview': {
                    props: ['payload'],
                    emits: ['update:blocked'],
                    template: `<div class="preview-probe">
                        <span>{{ payload?.amount }} {{ payload?.currency }} {{ payload?.accountId }} {{ payload?.recipientId }}</span>
                        <button class="preview-block" @click="$emit('update:blocked', true)">Unavailable</button>
                        <button class="preview-ready" @click="$emit('update:blocked', false)">Ready</button>
                    </div>`,
                },
                'mt-button': {
                    props: ['disabled', 'isLoading'],
                    template: '<button :disabled="disabled" :data-loading="isLoading"><slot></slot></button>',
                },
            },
            provide: { paymentTransferApiService: { getOptions, getRecipients, createTransfer, reapplyTransfer } },
        },
    });
    activeWrapper = wrapper;
    await flushPromises();
    return { wrapper, notify, createTransfer, getRecipients, reapplyTransfer };
}

/** @private */
export function field(wrapper: VueWrapper, name: string) {
    return wrapper.get(`.sw-finance-transfer-create-modal__field-${name}`);
}

/** @private */
export async function select(wrapper: VueWrapper, name: string, value: string) {
    await field(wrapper, name).setValue(value);
    await flushPromises();
}

/** @private */
export async function input(wrapper: VueWrapper, name: string, value: string) {
    await field(wrapper, name).get('input').setValue(value);
    await flushPromises();
}

/** @private */
export function saveButton(wrapper: VueWrapper) {
    return wrapper.get('.sw-finance-transfer-create-modal__button-save');
}

/** @private */
export async function fillExistingRecipient(wrapper: VueWrapper, amount = '1') {
    await select(wrapper, 'recipient', 'recipient-1');
    await input(wrapper, 'amount', amount);
}

/** @private */
export function cleanupWrapper(): void {
    activeWrapper?.unmount();
    activeWrapper = null;
}
