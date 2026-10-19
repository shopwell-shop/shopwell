/**
 * @sw-package framework
 */
import template from './sw-settings-payment-provider-detail.html.twig';
import './sw-settings-payment-provider-detail.scss';

const { Mixin } = Shopwell;

/**
 * Configures one payment interface of the platform.
 *
 * An interface is code, not data: the administration does not know alipay or wechat, it only renders
 * the form the interface declares. That is why the page is built around one loop over the fields the
 * server hands over - an interface of an extension is configurable without the administration being
 * touched at all.
 *
 * The page configures the platform side of the two dimensional matrix:
 *
 *  - the credentials belong to the interface and are shared by all of its channels, so they are
 *    edited once, in the second card,
 *  - the parameters (`appid` of a wechat channel, for instance) belong to a single channel and are
 *    edited on the channel itself. A channel is a row of a table and its form is folded out on
 *    demand: an interface may declare a dozen channels and every channel may declare a dozen
 *    fields, so rendering them all at once would turn the page into a wall of inputs.
 *
 * Which app may use which of them is a different page: the credentials here are the merchant account
 * of the platform, an app either pays through it or brings its own.
 *
 * A secret is never echoed back - a stored one shows as "configured" and keeping the input empty
 * leaves it untouched, which is the only way to edit an interface without retyping its keys.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['paymentGatewayService', 'acl'],

    mixins: [Mixin.getByName('notification')],

    props: {
        providerId: {
            type: String,
            required: true,
        },
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.provider === null ? '' : this.displayLabel(this.provider.label)),
        };
    },

    data() {
        return {
            provider: null,
            isLoading: true,
            isSaving: false,
            resetTarget: null,
            expandedChannels: {},
            isDisplayingLeavePageWarning: false,
            nextRoute: null,
        };
    },

    computed: {
        canEditProvider() {
            return this.acl.can('payment_provider_config.editor');
        },

        canEditChannel() {
            return this.acl.can('payment_channel_config.editor');
        },

        canDeleteProvider() {
            return this.acl.can('payment_provider_config.deleter');
        },

        canDeleteChannel() {
            return this.acl.can('payment_channel_config.deleter');
        },

        channelCount() {
            return this.provider === null ? 0 : this.provider.channels.length;
        },

        /**
         * Saving is only worth a click when there is something to save. The two existing helpers
         * decide per part whether it differs from what the server handed over, the page only
         * combines them - the interface itself first, then any of its channels.
         */
        isDirty() {
            if (this.provider === null) {
                return false;
            }

            if (this.isProviderDirty()) {
                return true;
            }

            return this.provider.channels.some((channel) => this.isChannelDirty(channel));
        },
    },

    created() {
        this.createdComponent();
    },

    /**
     * The navigation is suspended, not cancelled: `next` is kept and called once the operator has
     * decided. Calling `next(false)` here would end the navigation, and pushing the same route again
     * from the modal would run into this guard a second time - with `isDirty` still true - so the
     * page could never be left at all.
     */
    beforeRouteLeave(to, from, next) {
        if (!this.isDirty) {
            next();

            return;
        }

        this.nextRoute = next;
        this.isDisplayingLeavePageWarning = true;
    },

    methods: {
        /**
         * The name of an interface and of a channel is a snippet key, not a text: the core ships the
         * keys of the interfaces it declares in this module's snippets, a plugin ships its own. An
         * installation that has no entry for a key shows the key itself - ugly, but it tells the
         * operator that a translation is missing instead of hiding it.
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
                await this.loadProvider();
            } catch (error) {
                this.createNotificationError({
                    title: this.$t('global.default.error'),
                    message: error.message,
                });
            } finally {
                this.isLoading = false;
            }
        },

        /**
         * The interface and its channels are code, only the configuration is data, so the form comes
         * from the server. Everything the page renders - including the labels - is part of that
         * response.
         */
        async loadProvider() {
            const providers = await this.paymentGatewayService.listProviders();
            const provider = providers.find((item) => item.technicalName === this.providerId) ?? null;

            if (provider === null) {
                this.$router.push({ name: 'sw.settings.payment.provider.index' });

                return;
            }

            this.provider = this.createProviderState(provider);
        },

        createProviderState(provider) {
            const credentialInput = {};
            (provider.credentialFields ?? []).forEach((field) => {
                credentialInput[field.name] = field.value ?? '';
            });

            return {
                technicalName: provider.technicalName,
                label: provider.label,
                enabled: provider.enabled,
                storedEnabled: provider.enabled,
                configured: provider.configured,
                credentialFields: provider.credentialFields ?? [],
                settings: provider.settings ?? {},
                credentialInput,
                storedInput: { ...credentialInput },
                channels: (provider.channels ?? []).map((channel) => this.createChannelState(channel)),
            };
        },

        createChannelState(channel) {
            const paramInput = {};
            (channel.paramFields ?? []).forEach((field) => {
                paramInput[field.name] = field.value ?? '';
            });

            return {
                technicalName: channel.channel,
                label: channel.label,
                flow: channel.flow,
                currencies: channel.currencies ?? [],
                devices: channel.devices ?? [],
                runtimeParams: channel.runtimeParams ?? {},
                paramFields: channel.paramFields ?? [],
                settings: channel.settings ?? {},
                enabled: channel.enabled,
                storedEnabled: channel.enabled,
                configured: channel.configured,
                paramInput,
                storedInput: { ...paramInput },
            };
        },

        /**
         * An interface whose switch is on has to be complete: the first payment of an app would
         * otherwise fail with a message only the log sees. A non-secret field that is stored comes
         * prefilled, so an emptied input is missing as well - only the empty input of a stored
         * secret is fine, because that one keeps its value.
         */
        isProviderInvalid() {
            if (!this.provider?.enabled) {
                return false;
            }

            return this.provider.credentialFields.some((field) => {
                if (!field.required || this.provider.credentialInput[field.name]) {
                    return false;
                }

                return !(field.secret && field.configured);
            });
        },

        isChannelInvalid(channel) {
            if (!channel.enabled) {
                return false;
            }

            return channel.paramFields.some((field) => {
                if (!field.required || channel.paramInput[field.name]) {
                    return false;
                }

                return !(field.secret && field.configured);
            });
        },

        /**
         * The values that are not secret are prefilled with what is stored, so only a value that
         * differs from it - or a switched switch - is a change worth saving.
         */
        isProviderDirty() {
            if (this.provider.enabled !== this.provider.storedEnabled) {
                return true;
            }

            return Object.keys(this.provider.credentialInput).some((name) => {
                return this.provider.credentialInput[name] !== (this.provider.storedInput[name] ?? '');
            });
        },

        isChannelDirty(channel) {
            if (channel.enabled !== channel.storedEnabled) {
                return true;
            }

            return Object.keys(channel.paramInput).some((name) => {
                return channel.paramInput[name] !== (channel.storedInput[name] ?? '');
            });
        },

        /**
         * A channel starts folded: the operator sees which channels exist and which of them are
         * configured, and only opens the one whose parameters have to be edited.
         */
        isChannelExpanded(channel) {
            return this.expandedChannels[channel.technicalName] === true;
        },

        toggleChannel(channel) {
            // the map is replaced instead of extended, a newly added key would not be reactive
            this.expandedChannels = {
                ...this.expandedChannels,
                [channel.technicalName]: !this.isChannelExpanded(channel),
            };
        },

        flowLabel(flow) {
            const snippet = `sw-settings-payment-provider.flow.${flow}`;

            return this.$te(snippet) ? this.$t(snippet) : flow;
        },

        deviceLabel(device) {
            const snippet = `sw-settings-payment-provider.device.${device}`;

            return this.$te(snippet) ? this.$t(snippet) : device;
        },

        /**
         * A channel that declares no currency processes every currency.
         */
        currencyList(channel) {
            if (channel.currencies.length === 0) {
                return this.$t('sw-settings-payment-provider.channel.allCurrencies');
            }

            return channel.currencies.join(' / ');
        },

        deviceList(channel) {
            if (channel.devices.length === 0) {
                return this.$t('sw-settings-payment-provider.channel.allDevices');
            }

            return channel.devices.map((device) => this.deviceLabel(device)).join(' / ');
        },

        /**
         * The parameters a payment call itself has to bring, so the operator knows what an app has to
         * hand in when it uses this channel.
         */
        runtimeParamList(channel) {
            return Object.keys(channel.runtimeParams ?? {}).filter((name) => channel.runtimeParams[name]);
        },

        /**
         * Whether clearing this channel would remove anything.
         *
         * The stored input is what the server handed over, so it answers "has this channel ever been
         * configured" instead of "is it configured now": a channel whose fields are all empty has
         * nothing to clear, and the reset action only exists when there is something to clear.
         */
        hasStoredParams(channel) {
            return Object.keys(channel.storedInput ?? {}).some((name) => {
                const value = channel.storedInput[name];

                return value !== null && value !== undefined && value !== '';
            });
        },

        onCancel() {
            this.$router.push({ name: 'sw.settings.payment.provider.index' });
        },

        /**
         * Keeping the page aborts the suspended navigation, so the operator stays where they are and
         * the next click on cancel or save starts a fresh one.
         */
        onLeaveModalClose() {
            const next = this.nextRoute;

            this.nextRoute = null;
            this.isDisplayingLeavePageWarning = false;

            if (typeof next === 'function') {
                next(false);
            }
        },

        /**
         * Resuming the suspended navigation needs the modal to be gone first, otherwise the page it
         * leaves is still covered by the warning.
         */
        onLeaveModalConfirm() {
            const next = this.nextRoute;

            this.nextRoute = null;
            this.isDisplayingLeavePageWarning = false;

            if (typeof next !== 'function') {
                return;
            }

            this.$nextTick(() => {
                next();
            });
        },

        async onSave() {
            if (this.provider === null) {
                return;
            }

            if (this.isProviderInvalid()) {
                this.createNotificationError({
                    title: this.$t('global.default.error'),
                    message: this.$t('sw-settings-payment-provider.notification.missingFields', {
                        name: this.displayLabel(this.provider.label),
                    }),
                });

                return;
            }

            const dirtyChannels = this.provider.channels.filter((channel) => this.isChannelDirty(channel));

            const channelWithInvalidParams = dirtyChannels.find((channel) => this.isChannelInvalid(channel));

            if (channelWithInvalidParams) {
                this.createNotificationError({
                    title: this.$t('global.default.error'),
                    message: this.$t('sw-settings-payment-provider.notification.missingFields', {
                        name: this.displayLabel(channelWithInvalidParams.label),
                    }),
                });

                return;
            }

            const dirtyProvider = this.isProviderDirty();

            if (!dirtyProvider && dirtyChannels.length === 0) {
                this.createNotificationInfo({
                    title: this.$t('global.default.info'),
                    message: this.$t('sw-settings-payment-provider.notification.nothingToSave'),
                });

                return;
            }

            this.isSaving = true;

            try {
                if (dirtyProvider) {
                    await this.paymentGatewayService.saveProvider(this.provider.technicalName, {
                        enabled: this.provider.enabled,
                        credentials: this.provider.credentialInput,
                        settings: this.provider.settings,
                    });
                }

                for (const channel of dirtyChannels) {
                    await this.paymentGatewayService.saveProviderChannel(
                        this.provider.technicalName,
                        channel.technicalName,
                        {
                            enabled: channel.enabled,
                            params: channel.paramInput,
                            settings: channel.settings,
                        },
                    );
                }

                this.createNotificationSuccess({
                    title: this.$t('global.default.success'),
                    message: this.$t('sw-settings-payment-provider.notification.saveSuccess'),
                });

                await this.loadProvider();
            } catch (error) {
                this.createNotificationError({
                    title: this.$t('global.default.error'),
                    message: error.message,
                });
            } finally {
                this.isSaving = false;
            }
        },

        onOpenResetProvider() {
            this.resetTarget = {
                type: 'provider',
                provider: this.provider.technicalName,
                label: this.provider.label,
            };
        },

        onOpenResetChannel(channel) {
            this.resetTarget = {
                type: 'channel',
                provider: this.provider.technicalName,
                channel: channel.technicalName,
                label: channel.label,
            };
        },

        onCloseResetModal() {
            this.resetTarget = null;
        },

        onResetModalChange(isOpen) {
            if (!isOpen) {
                this.onCloseResetModal();
            }
        },

        async onConfirmReset() {
            if (!this.resetTarget) {
                this.onCloseResetModal();

                return;
            }

            const target = this.resetTarget;
            this.isSaving = true;

            try {
                if (target.type === 'provider') {
                    await this.paymentGatewayService.resetProvider(target.provider);
                } else {
                    await this.paymentGatewayService.resetProviderChannel(target.provider, target.channel);
                }

                this.onCloseResetModal();

                this.createNotificationSuccess({
                    title: this.$t('global.default.success'),
                    message: this.$t('sw-settings-payment-provider.notification.resetSuccess'),
                });

                await this.loadProvider();
            } catch (error) {
                this.createNotificationError({
                    title: this.$t('global.default.error'),
                    message: error.message,
                });
            } finally {
                this.isSaving = false;
            }
        },
    },
};
