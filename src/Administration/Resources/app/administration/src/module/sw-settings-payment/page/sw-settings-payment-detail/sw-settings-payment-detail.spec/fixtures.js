import { mount } from '@vue/test-utils';

const mountedWrappers = [];
export function cleanUpWrappers() {
    mountedWrappers.splice(0).forEach((wrapper) => wrapper.unmount());
}

/**
 * @sw-package checkout
 */

/**
 * The service the card for the collection channel talks to. Every method is a jest mock, so a test can
 * say what the server answered and which call the page made. The store behind the mocks is written by
 * a save, so a reload after the save answers the state the save created.
 */
export function createGatewayService(apps = [], channel = {}) {
    const stored = {
        apps,
        binding: null,
        paymentApp: apps[0] || null,
        collectThroughGateway: false,
        taken: [],
        ...channel,
    };

    return {
        listProviderDefinitions: jest.fn(() => Promise.reject(new Error('Definitions read must not be required'))),
        listApps: jest.fn(() => Promise.reject(new Error('App read must not be required'))),
        getPaymentMethodChannel: jest.fn(() => Promise.resolve(stored)),
        savePaymentMethodChannel: jest.fn((id, methodTechnicalName, paymentAppId = null, routingPolicy = 'priority') => {
            const appId = paymentAppId || (stored.paymentApp ? stored.paymentApp.id : null);

            stored.binding = { paymentAppId: appId, methodTechnicalName, routingPolicy };
            stored.paymentApp = apps.find((app) => app.id === appId) || stored.paymentApp;
            stored.collectThroughGateway = true;

            return Promise.resolve(stored);
        }),
    };
}

export async function createWrapper(privileges = [], paymentMethod = {}, gatewayService = createGatewayService()) {
    const wrapper = mount(
        await wrapTestComponent('sw-settings-payment-detail', {
            sync: true,
        }),
        {
            global: {
                renderStubDefaultSlot: true,
                mocks: {
                    $route: {
                        query: {
                            page: 1,
                            limit: 25,
                        },
                        params: {
                            id: '12312',
                        },
                    },
                },
                provide: {
                    repositoryFactory: {
                        create: () => ({
                            create: () => {
                                return {
                                    id: '1a2b3c',
                                    name: 'Test settings-payment',
                                    entity: 'settings-payment',
                                    pluginId: '12321-a',
                                    ...paymentMethod,
                                };
                            },
                            get: () =>
                                Promise.resolve({
                                    id: '1a2b3c',
                                    name: 'Test settings-payment',
                                    entity: 'settings-payment',
                                    pluginId: '12321-a',
                                    ...paymentMethod,
                                }),
                            search: () =>
                                Promise.resolve({
                                    first: () =>
                                        Promise.resolve({
                                            id: '1a2b3c',
                                            name: 'Test settings-payment',
                                            entity: 'settings-payment',
                                            pluginId: '12321-a',
                                            getEntityName: () => 'payment-method',
                                            ...paymentMethod,
                                        }),
                                }),
                        }),
                    },
                    acl: {
                        can: (identifier) => {
                            if (!identifier) {
                                return true;
                            }

                            return privileges.includes(identifier);
                        },
                    },
                    customFieldDataProviderService: {
                        getCustomFieldSets: () => Promise.resolve([]),
                    },
                    feature: {
                        isActive: () => true,
                    },
                    paymentGatewayService: gatewayService,
                },
                stubs: {
                    'sw-page': {
                        template: `
                        <div class="sw-page">
                            <slot name="smart-bar-actions"></slot>
                            <slot name="content"></slot>
                        </div>
                    `,
                    },
                    'sw-button-process': true,
                    'sw-language-switch': true,
                    'sw-card-view': true,
                    'sw-container': true,
                    'mt-switch': true,
                    'mt-number-field': true,
                    'mt-select': {
                        props: ['modelValue', 'options', 'disabled'],
                        template:
                            '<select :disabled="disabled" :value="modelValue" @change="$emit(\'update:modelValue\', $event.target.value)"><option v-for="option in options" :key="option.value" :value="option.value" :disabled="option.disabled">{{ option.label }}</option></select>',
                    },
                    'mt-text-field': {
                        props: ['modelValue', 'disabled'],
                        template:
                            '<input :disabled="disabled" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)">',
                    },
                    'mt-banner': {
                        template: '<div class="mt-banner"><slot></slot></div>',
                    },
                    'sw-text-field': true,
                    'sw-language-info': true,
                    'sw-upload-listener': true,
                    'sw-media-upload-v2': true,
                    'sw-plugin-box': true,
                    'mt-textarea': true,
                    'sw-select-rule-create': { props: ['ruleFilter'], template: '<div></div>' },
                    'sw-sidebar': true,
                    'sw-sidebar-media-item': true,
                    'sw-skeleton': true,
                    'sw-context-menu-item': true,
                    'sw-custom-field-set-renderer': true,
                    'sw-confirm-modal': {
                        template:
                            '<div class="sw-confirm-modal-stub"><button class="confirm-routing" @click="$emit(\'confirm\')"></button></div>',
                    },
                    'router-link': {
                        template: '<a class="router-link-stub" href="#"><slot></slot></a>',
                    },
                },
            },
        },
    );
    mountedWrappers.push(wrapper);

    return wrapper;
}

/**
 * The tenant of the platform: the account the shop's own checkout collects on.
 */
export const PLATFORM_APP = {
    id: 'platform-app-id',
    technicalName: 'platform',
    name: 'Shopfront',
    type: 'platform',
    active: true,
    methods: [{ technicalName: 'alipay', translationKey: 'payment.method.alipay' }],
};

/**
 * An app the merchant onboarded - it collects on its own merchant account.
 */
export const SECOND_APP = {
    id: 'second-app-id',
    technicalName: 'second-app',
    name: 'Second app',
    type: 'external',
    active: true,
    methods: [
        { technicalName: 'alipay', translationKey: 'payment.method.alipay' },
        { technicalName: 'card', translationKey: 'payment.method.card' },
    ],
};
