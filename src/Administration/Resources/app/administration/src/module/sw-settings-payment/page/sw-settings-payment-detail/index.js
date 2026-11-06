import template from './sw-settings-payment-detail.html.twig';
import './sw-settings-payment-detail.scss';

const { Mixin } = Shopwell;
const { Criteria } = Shopwell.Data;
const { warn } = Shopwell.Utils.debug;
const { mapPropertyErrors } = Shopwell.Component.getComponentHelper();

/**
 * @sw-package checkout
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'acl',
        'customFieldDataProviderService',
        'paymentGatewayService',
    ],

    mixins: [Mixin.getByName('notification'), Mixin.getByName('placeholder')],

    shortcuts: {
        'SYSTEMKEY+S': {
            method: 'onSave',
        },
        ESCAPE: 'onCancel',
    },

    data() {
        return {
            paymentMethod: null,
            mediaItem: null,
            uploadTag: 'sw-payment-method-upload-tag',
            isLoading: false,
            isSaveSuccessful: false,
            customFieldSets: null,
            showDeleteModal: false,
            deletionInProcess: false,
            // the gateway channel of this payment method, loadable only for a saved method
            gatewayApps: [],
            gatewayBinding: null,
            gatewayApp: null,
            gatewayMethod: null,
            gatewayRoutingPolicy: 'priority',
            gatewayTaken: [],
            gatewayCollectThroughGateway: true,
            isGatewayLoading: false,
            isGatewaySaving: false,
            showGatewayRebindModal: false,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        isNewPaymentMethod() {
            return this.paymentMethod._isNew;
        },

        identifier() {
            return this.placeholder(this.paymentMethod, 'name');
        },

        paymentMethodRepository() {
            return this.repositoryFactory.create('payment_method');
        },

        ruleRepository() {
            return this.repositoryFactory.create('rule');
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        tooltipSave() {
            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light',
            };
        },

        tooltipCancel() {
            return {
                message: 'ESC',
                appearance: 'light',
            };
        },

        ruleFilter() {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(
                Criteria.multi('OR', [
                    Criteria.contains('rule.moduleTypes.types', 'payment'),
                    Criteria.equals('rule.moduleTypes', null),
                ]),
            );

            criteria.addAssociation('conditions').addSorting(Criteria.sort('name', 'ASC', false));

            return criteria;
        },

        showCustomFields() {
            return this.paymentMethod && this.customFieldSets && this.customFieldSets.length > 0;
        },

        paymentMethodCriteria() {
            const criteria = new Criteria();
            criteria.setIds([this.paymentMethodId]);

            if (this.acl.can('payment.deleter')) {
                criteria.getAssociation('customers').setLimit(1);
                criteria.getAssociation('salesChannels').setLimit(1);
                criteria.getAssociation('salesChannelDefaultAssignments').setLimit(1);
                criteria.getAssociation('orderTransactions').setLimit(1);
            }

            return criteria;
        },

        forbidDelete() {
            return (
                this.paymentMethod.orderTransactions?.length !== 0 ||
                this.paymentMethod.salesChannels?.length !== 0 ||
                this.paymentMethod.customers?.length !== 0 ||
                this.paymentMethod.salesChannelDefaultAssignments?.length !== 0
            );
        },

        technicalNameIsProvided() {
            return !!this.paymentMethod?.pluginId || !!this.paymentMethod?.appPaymentMethod?.id;
        },

        /**
         * A payment is collected on the merchant account of an app, so an installation without a single
         * usable app has no account to collect on at all - and that is what the card tells the operator.
         */
        gatewayHasApps() {
            return this.gatewayApps.length > 0;
        },

        /**
         * The account the operator picks. Most installations have exactly one - the tenant of the
         * platform - so the list is only rendered as a choice when there really is one.
         */
        gatewayAppOptions() {
            return this.gatewayApps.map((app) => {
                return {
                    value: app.id,
                    label: this.appLabel(app),
                };
            });
        },

        gatewaySingleApp() {
            return this.gatewayApps.length === 1;
        },

        gatewaySelectedApp() {
            return this.gatewayApps.find((app) => app.id === this.gatewayApp) || null;
        },

        gatewaySelectedAppLabel() {
            return this.gatewaySelectedApp ? this.appLabel(this.gatewaySelectedApp) : '';
        },

        gatewayMethodOptions() {
            const app = this.gatewaySelectedApp;
            if (!app) {
                return [];
            }

            const methods = new Map();
            (app.methods || []).forEach((method) => {
                const taken = this.gatewayTaken.find(
                    (item) => item.paymentAppId === app.id && item.methodTechnicalName === method.technicalName,
                );
                const label = this.displayLabel(method.translationKey);
                methods.set(method.technicalName, {
                    value: method.technicalName,
                    label: taken
                        ? this.$t('sw-settings-payment.detail.gatewayChannel.optionTakenBy', {
                              name: taken.paymentMethodName,
                              label,
                          })
                        : label,
                    disabled: !!taken,
                });
            });

            return Array.from(methods.values());
        },

        gatewayHasChannels() {
            return this.gatewayMethodOptions.length > 0;
        },

        gatewayBindingAppLabel() {
            if (!this.gatewayBinding) {
                return '';
            }

            const app = this.gatewayApps.find((item) => item.id === this.gatewayBinding.paymentAppId);

            return app ? this.appLabel(app) : this.gatewayBinding.paymentAppId;
        },

        gatewayBindingMethodLabel() {
            if (!this.gatewayBinding) {
                return '';
            }

            const app = this.gatewayApps.find((item) => item.id === this.gatewayBinding.paymentAppId);
            const method = (app?.methods || []).find(
                (item) => item.technicalName === this.gatewayBinding.methodTechnicalName,
            );

            return method ? this.displayLabel(method.translationKey) : this.gatewayBinding.methodTechnicalName;
        },

        /**
         * The card has a save button of its own: it writes another table than the page's save, and
         * putting it behind the global button would either write it twice or only on the second click.
         */
        gatewaySaveDisabled() {
            if (this.isGatewayLoading || this.isGatewaySaving) {
                return true;
            }

            if (!this.gatewayApp || !this.gatewayMethod || !this.gatewayRoutingPolicy.trim()) {
                return true;
            }

            if (!this.gatewayBinding) {
                return false;
            }

            return (
                this.gatewayBinding.paymentAppId === this.gatewayApp &&
                this.gatewayBinding.methodTechnicalName === this.gatewayMethod &&
                this.gatewayBinding.routingPolicy === this.gatewayRoutingPolicy
            );
        },

        /**
         * A method that was bound before this card existed carries the binding without the handler of
         * the gateway. Saving is what changes the handler, so the operator is told that.
         */
        gatewayShowCollectHint() {
            return !this.gatewayCollectThroughGateway && !!this.gatewayMethod;
        },

        ...mapPropertyErrors('paymentMethod', ['name', 'technicalName']),
    },

    watch: {
        'paymentMethod.mediaId'() {
            if (this.paymentMethod.mediaId) {
                this.setMediaItem({ targetId: this.paymentMethod.mediaId });
            }
        },

        /**
         * The channels belong to the account, so a channel the newly selected account does not offer is
         * not a valid selection anymore once the account changes.
         */
        gatewayApp() {
            const available = this.gatewayMethodOptions.map((option) => option.value);

            if (this.gatewayMethod && !available.includes(this.gatewayMethod)) {
                this.gatewayMethod = null;
            }
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.paymentMethodId = this.$route.params.id.toLowerCase();
            this.loadEntityData();
            this.loadCustomFieldSets();
        },

        onSaveRule(ruleId) {
            this.paymentMethod.availabilityRuleId = ruleId;
        },

        onDismissRule() {
            this.paymentMethod.availabilityRuleId = null;
        },

        abortOnLanguageChange() {
            return this.paymentMethodRepository.hasChanges(this.paymentMethod);
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        onChangeLanguage() {
            this.loadEntityData();
        },

        loadEntityData() {
            this.isLoading = true;

            this.paymentMethodRepository
                .search(this.paymentMethodCriteria)
                .then((response) => response.first())
                .then((paymentMethod) => {
                    this.paymentMethod = paymentMethod;

                    if (paymentMethod?.mediaId) {
                        this.setMediaItem({ targetId: paymentMethod.mediaId });
                    }

                    // the handler of the method and therefore the state of the card follows the saved
                    // entity, so it is read together with it
                    this.loadGatewayChannel();
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        loadCustomFieldSets() {
            this.customFieldDataProviderService.getCustomFieldSets('payment_method').then((sets) => {
                this.customFieldSets = sets;
            });
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onSave() {
            this.isSaveSuccessful = false;
            this.isLoading = true;

            return this.paymentMethodRepository
                .save(this.paymentMethod)
                .then(() => {
                    this.isSaveSuccessful = true;
                    this.$refs.mediaSidebarItem.getList();
                    this.loadEntityData();
                })
                .catch((exception) => {
                    this.onError(exception);
                    warn(this._name, exception.message, exception.response);
                    throw exception;
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        onError(error) {
            let errorDetails = null;

            try {
                errorDetails = error.response.data.errors[0].detail;
            } catch (_e) {
                errorDetails = '';
            }

            this.createNotificationError({
                title: this.$t('global.default.error'),
                message: `${this.$t('sw-settings-payment.detail.messageSaveError', { name: this.paymentMethod.name }, 0)} ${errorDetails}`,
            });
        },

        onCancel() {
            this.$router.push({ name: 'sw.settings.payment.overview' });
        },

        setMediaItem({ targetId }) {
            this.mediaRepository.get(targetId).then((updatedMedia) => {
                this.mediaItem = updatedMedia;
                this.paymentMethod.mediaId = targetId;
            });
        },

        setMediaFromSidebar(mediaEntity) {
            this.mediaItem = mediaEntity;
            this.paymentMethod.mediaId = mediaEntity.id;
        },

        onUnlinkLogo() {
            this.mediaItem = null;
            this.paymentMethod.mediaId = null;
        },

        onDropMedia(mediaItem) {
            this.setMediaItem({ targetId: mediaItem.id });
        },

        openMediaSidebar() {
            this.$refs.mediaSidebarItem.openContent();
        },

        async deletePaymentMethod() {
            if (!this.acl.can('payment.deleter')) return;

            this.deletionInProcess = true;

            await this.paymentMethodRepository.delete(this.paymentMethod.id);
            await this.$router.replace({
                name: 'sw.settings.payment.overview',
            });
        },

        /**
         * The name of an interface and of a channel is a snippet key, not a text: the core ships the
         * keys of its own interfaces, an extension ships its own. A key without an entry is rendered
         * as it is, so a missing translation is visible instead of silent.
         */
        displayLabel(label) {
            if (!label) {
                return '';
            }

            return this.$te(label) ? this.$t(label) : label;
        },

        /**
         * The name an operator recognizes an app by. An app may be unnamed, and the technical name is
         * then the only thing left that identifies it.
         */
        appLabel(app) {
            if (!app) {
                return '';
            }

            return app.name || app.technicalName || app.id;
        },

        /**
         * Reads the accounts this shop can collect on, the interfaces they can collect through and the
         * channel this method uses.
         *
         * A method that was never saved has no id the endpoints could answer for, so the card shows an
         * instruction instead of a request - and no request is made, because a 404 is not a state.
         */
        async loadGatewayChannel() {
            if (!this.paymentMethod || this.isNewPaymentMethod) {
                this.gatewayApps = [];
                this.gatewayBinding = null;

                return;
            }

            this.isGatewayLoading = true;

            try {
                const channel = await this.paymentGatewayService.getPaymentMethodChannel(this.paymentMethodId);
                this.applyGatewayChannel(channel);
            } catch (error) {
                this.createNotificationError({
                    title: this.$t('global.default.error'),
                    message: error.message,
                });
            } finally {
                this.isGatewayLoading = false;
            }
        },

        /**
         * The stored state of the card. The account and the channel are prefilled with it, so a re-bind
         * starts from what the method uses today; an unbound method starts from the account the answer
         * names, which is the tenant of the platform.
         */
        applyGatewayChannel(channel) {
            const answer = channel || {};

            if (answer.apps) {
                this.gatewayApps = answer.apps.filter((app) => app.active);
            }
            this.gatewayBinding = answer.binding || null;
            this.gatewayTaken = answer.taken || [];
            this.gatewayCollectThroughGateway = answer.collectThroughGateway !== false;
            this.gatewayApp = this.gatewayBinding
                ? this.gatewayBinding.paymentAppId
                : answer.paymentApp
                  ? answer.paymentApp.id
                  : null;
            this.gatewayMethod = this.gatewayBinding ? this.gatewayBinding.methodTechnicalName : null;
            this.gatewayRoutingPolicy = this.gatewayBinding?.routingPolicy || 'priority';
        },

        /**
         * Re-binding moves the money of this payment method to another merchant account, so it is
         * confirmed. The first binding is not: there is no account to take the money away from.
         */
        onSaveGatewayChannel() {
            if (this.gatewayBinding) {
                this.showGatewayRebindModal = true;

                return;
            }

            this.saveGatewayChannel();
        },

        async saveGatewayChannel() {
            this.showGatewayRebindModal = false;
            this.isGatewaySaving = true;

            try {
                const channel = await this.paymentGatewayService.savePaymentMethodChannel(
                    this.paymentMethodId,
                    this.gatewayMethod,
                    this.gatewayApp,
                    this.gatewayRoutingPolicy,
                );

                this.applyGatewayChannel(channel);

                this.createNotificationSuccess({
                    title: this.$t('global.default.success'),
                    message: this.$t('sw-settings-payment.detail.gatewayChannel.messageSaveSuccess'),
                });

                // the same call changed the handler of the payment method, so the page reloads it
                this.loadEntityData();
            } catch (error) {
                this.createNotificationError({
                    title: this.$t('global.default.error'),
                    message: error.message,
                });
            } finally {
                this.isGatewaySaving = false;
            }
        },
    },
};
