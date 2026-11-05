/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import zhSnippets from '../../../snippet/zh.json';
import { availableAccounts, existingApp, otherApp, platformApp, registryProviders } from './fixtures';

const wrappers = [];
export function cleanup() {
    wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
}

/**
 * Mounts the page with the payment api replaced by the fixtures above.
 *
 * `app === null` mounts the page of an app the api does not know, which is what the redirect of a
 * deleted app looks like.
 */
export async function createWrapper({
    appId = 'app-1',
    app = existingApp,
    savedApp = null,
    appSecret = null,
    section = 'collection',
    accounts = availableAccounts,
} = {}) {
    const service = {
        listProviderDefinitions: jest.fn(() => Promise.resolve(structuredClone(registryProviders))),
        listAppConfiguration: jest.fn(() =>
            Promise.resolve({
                apps: structuredClone(app === null ? [platformApp, otherApp] : [platformApp, app, otherApp]),
                accounts: structuredClone(accounts),
            }),
        ),
        saveApp: jest.fn(() =>
            Promise.resolve({
                app: savedApp ?? app,
                ...(appSecret === null ? {} : { appSecret }),
            }),
        ),
        deleteApp: jest.fn(() => Promise.resolve()),
    };

    const push = jest.fn();
    const replace = jest.fn();

    const wrapper = mount(await wrapTestComponent('sw-settings-payment-gateway-detail', { sync: true }), {
        props: { appId, section },
        global: {
            stubs: {
                'sw-page': {
                    template: `
                        <div>
                            <slot name="smart-bar-header"></slot>
                            <slot name="smart-bar-actions"></slot>
                            <slot name="content"></slot>
                        </div>`,
                },
                'sw-card-view': { template: '<div><slot></slot></div>' },
                'sw-container': { template: '<div><slot></slot></div>' },
                'sw-skeleton': true,
                'sw-payment-cashier-profiles': true,
                'mt-tabs': {
                    props: ['items'],
                    template:
                        '<nav><button v-for="item in items" :key="item.name" :data-tab="item.name" @click="item.onClick">{{ item.label }}</button></nav>',
                },
                'sw-payment-config-field': {
                    props: ['field', 'modelValue', 'disabled'],
                    emits: ['update:modelValue'],
                    template: `<input :data-field="field.name" :value="modelValue" :disabled="disabled" @input="$emit('update:modelValue', $event.target.value)">`,
                },
                'router-link': { template: '<a><slot></slot></a>' },
                'mt-card': { template: '<div><slot></slot><slot name="headerRight"></slot></div>' },
                'mt-button': { template: '<button><slot></slot></button>' },
                'mt-badge': true,
                'mt-icon': true,
                'mt-switch': {
                    props: ['modelValue', 'disabled', 'label'],
                    emits: ['update:modelValue'],
                    template: `<label>{{ label }}<input type="checkbox" :checked="modelValue" :disabled="disabled" @change="$emit('update:modelValue', $event.target.checked)"></label>`,
                },
                'mt-select': {
                    props: ['modelValue', 'options', 'disabled'],
                    emits: ['update:modelValue'],
                    template: `<select :value="modelValue" :disabled="disabled" @change="$emit('update:modelValue', $event.target.value)"><option value=""></option><option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option></select>`,
                },
                ...Object.fromEntries(
                    [
                        'mt-text-field',
                        'mt-password-field',
                        'mt-textarea',
                        'mt-url-field',
                        'mt-number-field',
                    ].map((name) => [
                        name,
                        {
                            props: [
                                'modelValue',
                                'disabled',
                                'label',
                                'name',
                            ],
                            emits: ['update:modelValue'],
                            template: `<label>{{ label }}<input :name="name" :value="modelValue" :disabled="disabled" @input="$emit('update:modelValue', $event.target.value)"></label>`,
                        },
                    ]),
                ),
                'mt-banner': { template: '<div><slot></slot></div>' },
                'mt-modal-root': { template: '<div><slot></slot></div>' },
                'mt-modal': { template: '<div><slot></slot><slot name="footer"></slot></div>' },
                'mt-modal-close': { template: '<button><slot></slot></button>' },
                'mt-modal-action': { template: '<button><slot></slot></button>' },
            },
            provide: {
                paymentGatewayService: service,
            },
            mocks: {
                $router: { push, replace },
                $te: () => false,
                // the page names the app it inherits from, so the translation is called with parameters
                $t: (key, params) => {
                    if (key === 'sw-settings-payment-gateway.detail.defaultAccountName') {
                        return zhSnippets['sw-settings-payment-gateway'].detail.defaultAccountName.replace(
                            '{provider}',
                            params.provider,
                        );
                    }
                    return params ? `${key}|${JSON.stringify(params)}` : key;
                },
            },
        },
    });

    await flushPromises();

    wrappers.push(wrapper);
    return { wrapper, service, push, replace };
}
