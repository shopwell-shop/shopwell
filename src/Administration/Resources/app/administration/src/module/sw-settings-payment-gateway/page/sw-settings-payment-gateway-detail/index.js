/**
 * @sw-package framework
 */
import template from './sw-settings-payment-gateway-detail.html.twig';
import './sw-settings-payment-gateway-detail.scss';

const { Mixin } = Shopwell;

const NEW_APP_ID = 'new';
const GENERATED_SECRET_BYTES = 32;

/**
 * The value the account source select falls back to when the app collects on its own account.
 *
 * `null` is what the server stores for "own account", but a select on `null` renders as "nothing
 * chosen", which would hide a decision the merchant has made. The empty string is therefore the
 * `own account` option of the control and is turned back into `null` when the page is saved.
 */
const OWN_ACCOUNT = '';

/**
 * Onboards one external app.
 *
 * Collecting a payment is two decisions, and both are made on this page:
 *
 *  1. the app is authorized for a payment interface - that is the merchant account, and the account
 *     source says whose it is: the one the app brings itself, or the one of another app it inherits,
 *     the tenant of the platform in particular. The credentials of the platform are configured once in
 *     the settings, an app that uses its own has to be filled in here.
 *  2. the app has individual channels enabled - being allowed to collect through alipay does not mean
 *     the app offers every alipay product to its users.
 *
 * A channel can only be enabled for an interface the app is authorized for, and its parameters follow
 * the account source: an appid of a wechat channel belongs to the merchant account that is charged, so
 * an app that inherits the account of another app cannot edit them here.
 *
 * The page is four cards because there are four independent things to decide - who the app is, how it
 * is called back, whose account is charged, and which products it offers. The account source is the
 * switch between the third and the fourth: whatever it says decides whether credentials and channel
 * parameters are editable, and it does so immediately instead of after a save.
 *
 * The tenant of the platform is part of the list but is written by the installation and never here, so
 * its whole page is read only.
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
            /**
             * The answer of the registry, kept as it came: the state of an interface is derived from it
             * again after every save, and a state cannot be turned back into a declaration.
             */
            registryProviders: [],
            /** Every app of the installation, the tenant of the platform first - the source options. */
            apps: [],
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

        /**
         * The tenant of the platform is the app the shop itself collects through. It is seeded by the
         * installation and every write to it is refused by the server, so the page shows it instead of
         * offering changes that would be rejected.
         */
        isPlatformTenant() {
            return this.app !== null && this.app.type === 'platform';
        },

        /**
         * The single answer to "may this field be changed", so a new control cannot forget one of the
         * two reasons a page is read only.
         */
        isReadOnly() {
            return !this.canEdit || this.isPlatformTenant;
        },

        /**
         * The app itself is never an account source of its own: inheriting from itself is a circle the
         * server refuses, so it must not even be offered.
         */
        accountSourceCandidates() {
            return this.apps.filter((app) => app.id !== this.app?.id);
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
                this.apps = apps;
                this.registryProviders = registryProviders;
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
                type: 'external',
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
                // whose merchant account is charged: `OWN_ACCOUNT` for the app itself, the id of the
                // app it inherits from otherwise
                sourceAppId: authorization && authorization.sourceAppId ? authorization.sourceAppId : OWN_ACCOUNT,
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

        /**
         * The account source options: an account of the app itself, or the account of another app of the
         * installation. The tenant of the platform comes first because it is the account the shop
         * collects through by default, and the app itself is left out - inheriting from itself is a
         * circle the server refuses.
         */
        accountSourceOptions() {
            return [
                {
                    value: OWN_ACCOUNT,
                    label: this.$t('sw-settings-payment-gateway.detail.accountSourceOwn'),
                },
                ...this.accountSourceCandidates.map((app) => {
                    return { value: app.id, label: app.name || app.technicalName };
                }),
            ];
        },

        /**
         * Whether the merchant account of another app is charged for this interface.
         */
        isAccountInherited(provider) {
            return provider.sourceAppId !== OWN_ACCOUNT;
        },

        /**
         * The app whose account is charged, `null` when it is the app of the page itself.
         */
        sourceApp(provider) {
            if (!this.isAccountInherited(provider)) {
                return null;
            }

            return this.apps.find((app) => app.id === provider.sourceAppId) ?? null;
        },

        sourceAppName(provider) {
            const source = this.sourceApp(provider);

            if (source === null) {
                return '';
            }

            return source.name || source.technicalName;
        },

        /**
         * One sentence that says what the inheritance means, instead of a form that silently disappears.
         */
        inheritanceText(provider) {
            return this.$t('sw-settings-payment-gateway.detail.accountSourceInheritance', {
                app: this.sourceAppName(provider),
                provider: this.displayLabel(provider.label),
            });
        },

        /**
         * An app that inherits an interface it is not authorized for itself never has credentials: the
         * chain is followed through the authorizations of every hop, so a hop without one is refused by
         * the server when the payment is opened. Warning here is the only place the merchant learns it
         * before a shopper does.
         */
        sourceAppMissesInterface(provider) {
            const source = this.sourceApp(provider);

            if (source === null) {
                // the app does not exist anymore - the foreign key dropped the inheritance already, so
                // this is data that was not saved yet and nothing to warn about twice
                return false;
            }

            const authorization = (source.providers || []).find(
                (item) => item.providerTechnicalName === provider.technicalName,
            );

            return !authorization || !authorization.enabled;
        },

        sourceAppWarning(provider) {
            return this.$t('sw-settings-payment-gateway.detail.accountSourceWarning', {
                app: this.sourceAppName(provider),
                provider: this.displayLabel(provider.label),
            });
        },

        /**
         * The parameters of a channel are part of the merchant account - the appid of a wechat channel
         * belongs to the merchant id next to it - so they are maintained by the app that is charged and
         * not by the one that inherits the account.
         */
        channelParamsInheritedText(provider) {
            return this.$t('sw-settings-payment-gateway.detail.channelParamsInherited', {
                app: this.sourceAppName(provider),
            });
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
                    const inherited = this.isAccountInherited(provider);

                    return {
                        providerTechnicalName: provider.technicalName,
                        enabled: provider.enabled,
                        sourceAppId: inherited ? provider.sourceAppId : null,
                        // an app that collects on a foreign account keeps no credentials of its own,
                        // otherwise the copy would drift from the account it was copied from
                        credentials: inherited ? {} : provider.credentialInput,
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

                // the state is derived from the declaration of the interface again and not from the
                // state it had before: a state carries the merged values of the app, not the fields the
                // interface declares
                this.providers = this.registryProviders.map((provider) => this.createProviderState(provider));

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
