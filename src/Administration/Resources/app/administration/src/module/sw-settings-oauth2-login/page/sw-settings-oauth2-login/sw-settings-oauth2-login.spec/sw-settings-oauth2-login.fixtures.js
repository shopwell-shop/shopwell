/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';

const credentialFields = [
    {
        name: 'clientId',
        type: 'text',
        target: 'clientId',
        config: { label: 'sw-settings-oauth2-login.channel.clientId', required: true },
    },
    {
        name: 'clientSecret',
        type: 'password',
        target: 'clientSecret',
        config: { label: 'sw-settings-oauth2-login.channel.clientSecret', required: true },
    },
];

export const googleChannel = {
    technicalName: 'google',
    labelSnippet: 'sso.oauth2.channel.google',
    defaultScopes: ['openid', 'email'],
    supportsAdminLogin: true,
    formFields: credentialFields,
};

export const wechatChannel = {
    technicalName: 'wechat',
    labelSnippet: 'sso.oauth2.channel.wechat',
    defaultScopes: ['snsapi_login'],
    supportsAdminLogin: true,
    formFields: credentialFields,
};

// A channel that needs a parameter besides the credentials, labelled with its own inline snippet.
export const keycloakChannel = {
    technicalName: 'keycloak',
    labelSnippet: 'sso.oauth2.channel.keycloak',
    defaultScopes: ['openid'],
    formFields: [
        ...credentialFields,
        {
            name: 'auth_server_url',
            type: 'url',
            target: 'settings',
            config: { label: { 'en-GB': 'Auth server', 'zh-CN': '认证服务器' }, required: true },
        },
    ],
};

// A channel with more than one scope to choose, the only case a scope field is shown at all.
export const officialAccountChannel = {
    technicalName: 'wechat-official-account',
    labelSnippet: 'sso.oauth2.channel.wechat',
    defaultScopes: ['snsapi_base'],
    formFields: [
        ...credentialFields,
        {
            name: 'scopes',
            type: 'multi-select',
            target: 'scopes',
            config: {
                label: 'sw-settings-oauth2-login.channel.scopes',
                placeholder: 'snsapi_base',
                options: [
                    { value: 'snsapi_base', label: { 'zh-CN': '静默授权' } },
                    { value: 'snsapi_userinfo', label: { 'zh-CN': '弹窗授权' } },
                ],
            },
        },
    ],
};

// The row a channel has once the shop configured it.
export const googleClient = {
    id: 'client-google',
    technicalName: 'google',
    enabled: true,
    clientId: 'google-id',
    clientSecret: 'google-secret',
    scopes: null,
    settings: null,
};

const formFieldRendererStub = {
    name: 'sw-form-field-renderer',
    props: {
        type: { type: String, default: null },
        config: { type: Object, default: null },
        value: { default: null },
        disabled: { default: undefined },
    },
    template: `<div class="sw-form-field-renderer">
        <input :value="value" :disabled="disabled" @input="$emit('update:value', $event.target.value)" />
        <button class="choose-scope" @click="$emit('update:value', ['snsapi_userinfo'])">Choose scope</button>
    </div>`,
};

/**
 * Every spec of the page needs the roles, the page checks them itself on the buttons it renders.
 */
export function setupPageHooks() {
    beforeEach(() => {
        global.activeAclRoles = ['oauth2_client.editor', 'oauth2_client.creator', 'oauth2_client.deleter'];
    });
}

export async function createWrapper({
    channels = [googleChannel, wechatChannel],
    clients = [],
    listError = null,
    saveError = null,
    deleteError = null,
} = {}) {
    // The page reloads the channels after every write, so the mock has to reflect them.
    const storedClients = [...clients];

    const repository = {
        search: jest.fn(() => Promise.resolve(storedClients)),
        create: jest.fn(() => ({})),
        get: jest.fn((id) => Promise.resolve(storedClients.find((client) => client.id === id))),
        save: jest.fn(() => (saveError === null ? Promise.resolve() : Promise.reject(saveError))),
        delete: jest.fn((id) => {
            if (deleteError !== null) {
                return Promise.reject(deleteError);
            }

            const index = storedClients.findIndex((client) => client.id === id);

            if (index !== -1) {
                storedClients.splice(index, 1);
            }

            return Promise.resolve();
        }),
    };

    const wrapper = mount(await wrapTestComponent('sw-settings-oauth2-login', { sync: true }), {
        global: {
            provide: {
                repositoryFactory: {
                    create: () => repository,
                },
                oauth2ChannelService: {
                    list: jest.fn(() => (listError === null ? Promise.resolve(channels) : Promise.reject(listError))),
                },
            },
            stubs: {
                'sw-page': {
                    template: `
                        <div>
                            <slot name="smart-bar-header"></slot>
                            <slot name="smart-bar-actions"></slot>
                            <slot name="content"></slot>
                        </div>`,
                },
                'sw-card-view': await wrapTestComponent('sw-card-view'),
                'sw-container': await wrapTestComponent('sw-container'),
                'sw-extension-component-section': await wrapTestComponent('sw-extension-component-section'),
                'sw-skeleton': true,
                'mt-tabs': {
                    name: 'mt-tabs',
                    props: ['items', 'defaultItem'],
                    template: `<div class="channel-tabs"><button v-for="item in items" :key="item.name"
                        :data-channel="item.name" :data-error="item.hasError"
                        @click="$emit('new-item-active', item.name)">{{ item.label }}</button></div>`,
                },
                'mt-card': {
                    name: 'mt-card',
                    props: ['title', 'subtitle', 'positionIdentifier'],
                    template: '<div class="mt-card"><h3>{{ title }}</h3><slot name="headerRight"/><slot/></div>',
                },
                'mt-switch': {
                    props: ['modelValue', 'disabled'],
                    template: `<input type="checkbox" :checked="modelValue" :disabled="disabled"
                        @change="$emit('update:modelValue', $event.target.checked)" />`,
                },
                'sw-form-field-renderer': formFieldRendererStub,
                // The modal teleports into the body and traps the focus, the page only needs its slots here.
                'mt-modal-root': {
                    template: `<div class="mt-modal-root"><button class="dismiss-modal" @click="$emit('change', false)">Dismiss</button><slot/></div>`,
                },
                'mt-modal': {
                    template: '<div class="mt-modal"><slot></slot><slot name="footer"></slot></div>',
                },
                'mt-modal-close': true,
                'mt-modal-action': true,
            },
        },
    });

    await flushPromises();

    return { wrapper, repository };
}

export async function selectChannel(wrapper, name) {
    await wrapper.get(`[data-channel="${name}"]`).trigger('click');
}

export async function setField(wrapper, name, value) {
    await wrapper.get(`.sw-settings-oauth2-login__field-${name} input`).setValue(value);
}

export async function enableChannel(wrapper) {
    await wrapper.get('.sw-settings-oauth2-login__channel-enabled').setValue(true);
}

export async function save(wrapper) {
    await wrapper.get('.sw-settings-oauth2-login__save-action').trigger('click');
    await flushPromises();
}
