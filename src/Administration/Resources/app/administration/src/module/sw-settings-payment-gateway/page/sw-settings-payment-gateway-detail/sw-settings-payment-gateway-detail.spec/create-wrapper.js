/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import { existingApp, otherApp, platformApp, registryProviders } from './fixtures';

/**
 * Mounts the page with the payment api replaced by the fixtures above.
 *
 * `app === null` mounts the page of an app the api does not know, which is what the redirect of a
 * deleted app looks like.
 */
export async function createWrapper({ appId = 'app-1', app = existingApp, savedApp = null, appSecret = null } = {}) {
    const service = {
        listProviders: jest.fn(() => Promise.resolve(registryProviders)),
        listApps: jest.fn(() => Promise.resolve(app === null ? [platformApp, otherApp] : [platformApp, app, otherApp])),
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
        props: { appId },
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
                'sw-payment-config-field': {
                    props: ['field', 'modelValue', 'disabled'],
                    // the `disabled` flag is rendered as an attribute so a test can tell an input the
                    // merchant may fill from one that only displays a value
                    template: `<div
                        class="sw-payment-config-field-stub"
                        :disabled="disabled ? 'disabled' : undefined"
                    >{{ field.name }}</div>`,
                },
                'router-link': { template: '<a><slot></slot></a>' },
                'mt-card': { template: '<div><slot></slot><slot name="headerRight"></slot></div>' },
                'mt-button': { template: '<button><slot></slot></button>' },
                'mt-badge': true,
                'mt-icon': true,
                'mt-switch': true,
                'mt-select': true,
                'mt-text-field': true,
                'mt-password-field': true,
                'mt-textarea': true,
                'mt-url-field': true,
                'mt-number-field': true,
                'mt-banner': { template: '<div><slot></slot></div>' },
                'mt-modal-root': { template: '<div><slot></slot></div>' },
                'mt-modal': { template: '<div><slot></slot><slot name="footer"></slot></div>' },
                'mt-modal-close': true,
                'mt-modal-action': true,
            },
            provide: {
                paymentGatewayService: service,
            },
            mocks: {
                $router: { push, replace },
                $te: () => false,
                // the page names the app it inherits from, so the translation is called with parameters
                $t: (key, params) => (params ? `${key}|${JSON.stringify(params)}` : key),
            },
        },
    });

    await flushPromises();

    return { wrapper, service, push, replace };
}
