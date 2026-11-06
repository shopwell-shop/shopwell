/**
 * @sw-package framework
 */

/**
 * The answer of `/_action/payment/providers`: two interfaces with the fields they declare.
 */
export const registryProviders = [
    {
        technicalName: 'alipay',
        label: '支付宝',
        enabled: true,
        configured: true,
        credentialFields: [
            {
                name: 'app_id',
                label: '支付宝应用 APPID',
                type: 'text',
                required: true,
                secret: false,
                options: {},
                default: null,
                value: '2021000000000000',
                configured: true,
            },
        ],
        settings: {},
        products: [
            {
                product: 'alipay_h5',
                method: 'alipay',
                methodTranslationKey: 'Alipay',
                label: '支付宝 手机网站支付',
                interactions: ['redirect'],
                currencies: ['CNY'],
                devices: ['wap'],
                runtimeParams: {},
                paramFields: [
                    {
                        name: 'sign_type',
                        label: '签名算法',
                        type: 'text',
                        required: false,
                        secret: false,
                        options: {},
                        default: null,
                        value: null,
                        configured: false,
                    },
                ],
                enabled: true,
                configured: true,
                settings: {},
            },
        ],
    },
    {
        technicalName: 'stripe',
        label: 'Stripe',
        enabled: false,
        configured: false,
        credentialFields: [
            {
                name: 'secret_key',
                label: 'Secret Key',
                type: 'password',
                required: true,
                secret: true,
                options: {},
                default: null,
                value: null,
                configured: false,
            },
        ],
        settings: {},
        products: [
            {
                product: 'stripe_checkout',
                method: 'stripe',
                methodTranslationKey: 'Stripe',
                label: 'Stripe Checkout',
                interactions: ['redirect'],
                currencies: [],
                devices: [],
                runtimeParams: {},
                paramFields: [],
                enabled: false,
                configured: false,
                settings: {},
            },
        ],
    },
    ...['wechat', 'paypal'].map((name) => ({
        technicalName: name,
        label: name === 'wechat' ? 'WeChat Pay' : 'PayPal',
        enabled: true,
        credentialFields: [],
        products: [
            {
                product: name === 'wechat' ? 'wechat_h5' : 'paypal_checkout',
                label: `${name} checkout`,
                method: name,
                interactions: ['redirect'],
                paramFields: [],
            },
        ],
    })),
];

export const availableAccounts = [
    {
        id: 'account-platform',
        name: 'alipay platform',
        providerTechnicalName: 'alipay',
        active: true,
        ownerAppId: null,
        isDefault: true,
    },
    {
        id: 'account-alipay',
        name: 'Merchant',
        providerTechnicalName: 'alipay',
        active: true,
        ownerAppId: 'app-1',
        isDefault: false,
    },
    {
        id: 'account-shared',
        name: 'Shared merchant',
        providerTechnicalName: 'alipay',
        active: true,
        ownerAppId: 'app-2',
        isDefault: false,
    },
    {
        id: 'account-ungranted',
        name: 'New branch',
        providerTechnicalName: 'stripe',
        active: true,
        ownerAppId: null,
        isDefault: false,
    },
];
export const platformApp = {
    id: 'app-platform',
    technicalName: 'platform',
    name: 'Platform Payment App',
    type: 'platform',
    active: true,
    notifyUrl: null,
    returnUrl: null,
    allowedIps: [],
    rateLimit: null,
    hasSecret: true,
    accounts: [{ accountId: 'account-platform', enabled: true, position: 0 }],
    channels: [{ channelTechnicalName: 'alipay_h5', enabled: true, settings: {} }],
};
export const otherApp = {
    ...platformApp,
    id: 'app-2',
    technicalName: 'pos-two',
    name: 'POS 二号',
    type: 'external',
    accounts: [{ accountId: 'account-shared', enabled: true, position: 0 }],
};
export const existingApp = {
    ...platformApp,
    id: 'app-1',
    technicalName: 'pos',
    name: 'POS 收银',
    type: 'external',
    allowedIps: ['127.0.0.1'],
    rateLimit: 60,
    accounts: [{ accountId: 'account-alipay', enabled: true, position: 0 }],
};
