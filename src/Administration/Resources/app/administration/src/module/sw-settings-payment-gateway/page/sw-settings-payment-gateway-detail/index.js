/**
 * @sw-package framework
 */
import template from './sw-settings-payment-gateway-detail.html.twig';
import './sw-settings-payment-gateway-detail.scss';

const { Mixin } = Shopwell;

const NEW_APP_ID = 'new';
const GENERATED_SECRET_BYTES = 32;

/**
 * Onboards one external app.
 *
 * Collecting a payment is two decisions, and both are made on this page:
 *
 *  1. the app is authorized for a payment interface - that is the merchant account, and `mode` says
 *     whose it is: the one of the platform, or the one the app brings itself. The credentials of the
 *     platform are configured once in the settings, an app that uses its own has to be filled in here.
 *  2. the app has individual channels enabled - being allowed to collect through alipay does not mean
 *     the app offers every alipay product to its users.
 *
 * A channel can only be enabled for an interface the app is authorized for: the interface carries the
 * credentials, and a channel without them cannot be charged. That is why the channels are grouped
 * under their interface instead of being one flat list.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['paymentGatewayService', 'acl'],

    mixins: [Mixin.getByName('notification')],

    props: {
        appId: {
            type: String,
            required: false,
            default: null,
        },
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    data() {
        return {
            app: null,
            providers: [],
            allowedIpsInput: '',
            appSecretInput: '',
            generatedSecret: '',
            isSecretModalOpen: false,
            isLoading: true,
            isSaving: false,
            isDeleting: false,
            isDeleteModalOpen: false,
        };
    },

    computed: {
        isNew() {
            return !this.appId || this.appId === NEW_APP_ID;
        },

        canEdit() {
            return this.acl.can('payment_app.editor');
        },

        canCreate() {
            return this.acl.can('payment_app.creator');
        },

        canDelete() {
            return this.acl.can('payment_app.deleter');
        },

        pageTitle() {
            if (!this.app) {
                return '';
            }

            return this.app.name || this.app.technicalName;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        /**
         * The name of an interface and of a channel is a snippet key, not a text. The core ships the
         * keys of its own interfaces, a plugin ships its own; a key without an entry is shown as it is,
         * so a missing translation is visible instead of silent.
         */
        displayLabel(label) {
            if (!label) {
                return '';
            }

            return this.$te(label) ? this.$t(label) : label;
        },

        async createdComponent() {
            this.isLoading = true;

            try {
                const [registryProviders, apps] = await Promise.all([
                    this.paymentGatewayService.listProviders(),
                    this.paymentGatewayService.listApps(),
                ]);

                const found = this.isNew ? null : (apps.find((app) => app.id === this.appId) ?? null);

                if (!this.isNew && !found) {
                    this.createNotificationError({
                        title: this.$t('global.default.error'),
                        message: this.$t('sw-settings-payment-gateway.detail.notFound'),
                    });

                    void this.$router.push({ name: 'sw.settings.payment.gateway.list' });

                    return;
                }

                this.app = found ?? this.createEmptyApp();
                this.allowedIpsInput = (this.app.allowedIps || []).join('\n');
                this.providers = registryProviders.map((provider) => this.createProviderState(provider));
            } catch (error) {
                this.createNotificationError({
                    title: this.$t('global.default.error'),
                    message: error.message,
                });
            } finally {
                this.isLoading = false;
            }
        },

        createEmptyApp() {
            return {
                id: null,
                technicalName: '',
                name: '',
                active: false,
                notifyUrl: null,
                returnUrl: null,
                allowedIps: [],
                rateLimit: null,
                hasSecret: false,
                providers: [],
                channels: [],
            };
        },

        /**
         * The interfaces of the app are merged into the ones the server offers, so an interface the
         * app does not use yet is shown just like the ones it does.
         */
        createProviderState(provider) {
            const authorization = (this.app.providers || []).find(
                (item) => item.providerTechnicalName === provider.technicalName,
            );

            const credentialInput = {};
            (provider.credentialFields || []).forEach((field) => {
                // the credentials of an app are never echoed back, the form always starts empty
                credentialInput[field.name] = '';
            });

            return {
                technicalName: provider.technicalName,
                label: provider.label,
                enabled: authorization ? authorization.enabled : false,
                mode: authorization ? authorization.mode : 'platform',
                hasCredentials: authorization ? authorization.hasCredentials : false,
                settings: authorization ? authorization.settings : {},
                credentialFields: provider.credentialFields || [],
                credentialInput,
                channels: (provider.channels || []).map((channel) => this.createChannelState(channel)),
            };
        },

        createChannelState(channel) {
            const assignment = (this.app.channels || []).find((item) => item.channelTechnicalName === channel.channel);

            const paramInput = {};
            (channel.paramFields || []).forEach((field) => {
                paramInput[field.name] = '';
            });

            return {
                technicalName: channel.channel,
                label: channel.label,
                flow: channel.flow,
                currencies: channel.currencies || [],
                devices: channel.devices || [],
                runtimeParams: channel.runtimeParams || {},
                paramFields: channel.paramFields || [],
                enabled: assignment ? assignment.enabled : false,
                hasParams: assignment ? assignment.hasParams : false,
                settings: assignment ? assignment.settings : {},
                paramInput,
            };
        },

        modeOptions() {
            return [
                { value: 'platform', label: this.$t('sw-settings-payment-gateway.detail.modePlatform') },
                { value: 'own', label: this.$t('sw-settings-payment-gateway.detail.modeOwn') },
            ];
        },

        flowLabel(flow) {
            const snippet = `sw-settings-payment-provider.flow.${flow}`;

            return this.$te(snippet) ? this.$t(snippet) : flow;
        },

        deviceList(channel) {
            if (channel.devices.length === 0) {
                return this.$t('sw-settings-payment-provider.channel.allDevices');
            }

            return channel.devices
                .map((device) => {
                    const snippet = `sw-settings-payment-provider.device.${device}`;

                    return this.$te(snippet) ? this.$t(snippet) : device;
                })
                .join(' / ');
        },

        currencyList(channel) {
            if (channel.currencies.length === 0) {
                return this.$t('sw-settings-payment-provider.channel.allCurrencies');
            }

            return channel.currencies.join(' / ');
        },

        runtimeParamList(channel) {
            return Object.keys(channel.runtimeParams || {}).filter((name) => channel.runtimeParams[name]);
        },

        onGenerateSecret() {
            const bytes = new Uint8Array(GENERATED_SECRET_BYTES);
            window.crypto.getRandomValues(bytes);
            this.appSecretInput = Array.from(bytes)
                .map((byte) => byte.toString(16).padStart(2, '0'))
                .join('');
        },

        parseAllowedIps() {
            return this.allowedIpsInput
                .split('\n')
                .map((ip) => ip.trim())
                .filter((ip) => ip !== '');
        },

        buildPayload() {
            const channels = [];

            this.providers.forEach((provider) => {
                provider.channels.forEach((channel) => {
                    channels.push({
                        channelTechnicalName: channel.technicalName,
                        enabled: channel.enabled,
                        params: channel.paramInput,
                        settings: channel.settings,
                    });
                });
            });

            return {
                id: this.app.id,
                technicalName: this.app.technicalName,
                name: this.app.name,
                active: this.app.active,
                notifyUrl: this.app.notifyUrl,
                returnUrl: this.app.returnUrl,
                allowedIps: this.parseAllowedIps(),
                rateLimit: this.app.rateLimit,
                appSecret: this.appSecretInput,
                providers: this.providers.map((provider) => {
                    return {
                        providerTechnicalName: provider.technicalName,
                        enabled: provider.enabled,
                        mode: provider.mode,
                        credentials: provider.credentialInput,
                        settings: provider.settings,
                    };
                }),
                channels,
            };
        },

        async onSave() {
            this.isSaving = true;

            try {
                const response = await this.paymentGatewayService.saveApp(this.buildPayload());

                this.app = response.app;
                this.allowedIpsInput = (this.app.allowedIps || []).join('\n');
                this.appSecretInput = '';
                this.providers = this.providers.map((provider) => this.createProviderState(provider));

                if (response.appSecret) {
                    // the plain secret is handed out once, so it is shown until it is acknowledged
                    this.generatedSecret = response.appSecret;
                    this.isSecretModalOpen = true;
                }

                this.createNotificationSuccess({
                    title: this.$t('global.default.success'),
                    message: this.$t('sw-settings-payment-gateway.notification.saveSuccess'),
                });

                if (this.isNew) {
                    void this.$router.replace({ name: 'sw.settings.payment.gateway.detail', params: { id: this.app.id } });
                }
            } catch (error) {
                this.createNotificationError({
                    title: this.$t('global.default.error'),
                    message: error.message,
                });
            } finally {
                this.isSaving = false;
            }
        },

        onCloseSecretModal() {
            this.isSecretModalOpen = false;
            this.generatedSecret = '';
        },

        onSecretModalChange(isOpen) {
            if (!isOpen) {
                this.onCloseSecretModal();
            }
        },

        onOpenDeleteModal() {
            this.isDeleteModalOpen = true;
        },

        onDeleteModalChange(isOpen) {
            if (!isOpen) {
                this.isDeleteModalOpen = false;
            }
        },

        async onConfirmDelete() {
            this.isDeleting = true;

            try {
                await this.paymentGatewayService.deleteApp(this.app.id);
                this.isDeleteModalOpen = false;

                this.createNotificationSuccess({
                    title: this.$t('global.default.success'),
                    message: this.$t('sw-settings-payment-gateway.notification.deleteSuccess'),
                });

                void this.$router.push({ name: 'sw.settings.payment.gateway.list' });
            } catch (error) {
                this.createNotificationError({
                    title: this.$t('global.default.error'),
                    message: error.message,
                });
            } finally {
                this.isDeleting = false;
            }
        },
    },
};
