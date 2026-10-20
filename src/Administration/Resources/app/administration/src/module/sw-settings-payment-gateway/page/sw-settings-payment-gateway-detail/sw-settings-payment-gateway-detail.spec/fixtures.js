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
        channels: [
            {
                channel: 'alipay_h5',
                label: '支付宝 手机网站支付',
                flow: 'redirect',
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
        channels: [
            {
                channel: 'stripe_checkout',
                label: 'Stripe Checkout',
                flow: 'redirect',
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
];

/**
 * The tenant the platform collects through. It is listed by the api as the first app and it is the
 * only account the platform level configuration writes to, so its credentials and its channel
 * parameters are shown but never edited here.
 */
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
    providers: [
        {
            providerTechnicalName: 'alipay',
            enabled: true,
            sourceAppId: null,
            sourceAppName: '',
            hasCredentials: true,
            // the merchant account of the platform lives on the platform level, the api still hands the
            // values over so the page can show what is configured
            credentials: { app_id: '2021000000000000' },
            settings: {},
        },
    ],
    channels: [
        {
            channelTechnicalName: 'alipay_h5',
            enabled: true,
            hasParams: true,
            // the parameters of the channel of the platform live on the platform level as well
            params: { sign_type: 'RSA2' },
            settings: {},
        },
    ],
};

/**
 * The app an external app can inherit the merchant account of.
 */
export const otherApp = {
    id: 'app-2',
    technicalName: 'pos-two',
    name: 'POS 二号',
    type: 'external',
    active: true,
    notifyUrl: null,
    returnUrl: null,
    allowedIps: [],
    rateLimit: null,
    hasSecret: true,
    providers: [
        {
            providerTechnicalName: 'alipay',
            enabled: true,
            sourceAppId: null,
            sourceAppName: '',
            hasCredentials: true,
            settings: {},
        },
    ],
    channels: [
        {
            channelTechnicalName: 'alipay_h5',
            enabled: true,
            hasParams: true,
            settings: {},
        },
    ],
};

/**
 * The app the page is opened for in most of the tests: it collects on its own accounts.
 */
export const existingApp = {
    id: 'app-1',
    technicalName: 'pos',
    name: 'POS 收银',
    type: 'external',
    active: true,
    notifyUrl: null,
    returnUrl: null,
    allowedIps: ['127.0.0.1'],
    rateLimit: 60,
    hasSecret: true,
    providers: [
        {
            providerTechnicalName: 'alipay',
            enabled: true,
            sourceAppId: null,
            sourceAppName: '',
            hasCredentials: true,
            credentials: { app_id: '2021000000000000' },
            settings: {},
        },
    ],
    channels: [
        {
            channelTechnicalName: 'alipay_h5',
            enabled: true,
            hasParams: true,
            params: { sign_type: 'RSA2' },
            settings: {},
        },
    ],
};
