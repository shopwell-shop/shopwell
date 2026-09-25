import { mapState } from 'pinia';
import useSession from 'src/app/composables/use-session';
import { useShopwellServicesStore } from '../../store/shopware-services.store';
import template from './sw-settings-services-index.html.twig';
import './sw-settings-services-index.scss';
import type { ServiceDescription } from '../../service/shopware-services.service';
import extractError from '../../composables/extract-error';
import {
    getServicesWithShopwellAccountRequirement,
    type ServiceWithShopwellAccountRequirement,
} from '../../requirements/index';

import SwSettingsServicesHero from '../../component/sw-settings-services-hero';
import SwSettingsServicesGrantPermissionsCard from '../../component/sw-settings-services-grant-permissions-card';
import SwSettingsServicesRevokePermissionsModal from '../../component/sw-settings-services-revoke-permissions-modal';
import SwSettingsServicesDeactivateModal from '../../component/sw-settings-services-deactivate-modal';
import SwSettingsServicesServiceCard from '../../component/sw-settings-services-service-card';

type SwSettingsPageData = {
    grantPermissionsCardBackground: string;
    services: ServiceDescription[];
    suspended: boolean;
    loadingError: string;
};

/**
 * @sw-package framework
 * @private
 */
export default Shopwell.Component.wrapComponentConfig({
    name: 'sw-settings-services-index',

    template,

    inject: ['acl'],

    components: {
        SwSettingsServicesHero,
        SwSettingsServicesGrantPermissionsCard,
        SwSettingsServicesRevokePermissionsModal,
        SwSettingsServicesDeactivateModal,
        SwSettingsServicesServiceCard,
    },

    data(): SwSettingsPageData {
        const assetFilter = Shopwell.Filter.getByName('asset');

        return {
            grantPermissionsCardBackground: assetFilter(
                '/administration/administration/static/img/services/grant-permissions-background.svg',
            ),
            services: [],
            suspended: true,
            loadingError: '',
        };
    },

    computed: {
        ...mapState(useShopwellServicesStore, ['config', 'currentRevision', 'consentGiven']),
        servicesWithAccountRequirement(): ServiceWithShopwellAccountRequirement[] {
            return getServicesWithShopwellAccountRequirement(this.services);
        },
    },

    created() {
        const shopwareServicesService = Shopwell.Service('shopwareServicesService');
        const serviceRegistryClient = Shopwell.Service('serviceRegistryClient');
        const shopwareServicesStore = useShopwellServicesStore();
        const sessionStore = useSession();

        Promise.all([
            this.reloadServices(),
            shopwareServicesService.getServicesContext().then((servicesConsent) => {
                shopwareServicesStore.config = servicesConsent;
            }),
            serviceRegistryClient
                .getCurrentRevision(sessionStore.currentLocale.value ?? 'en-GB')
                .then((serviceRevisions) => {
                    shopwareServicesStore.revisions = serviceRevisions;
                }),
        ])
            .then(() => {
                this.suspended = false;
            })
            .catch((exception) => {
                const errorMessage = extractError(exception);

                Shopwell.Store.get('notification').createNotification({
                    variant: 'critical',
                    title: this.$t('global.default.error'),
                    message: errorMessage,
                });
            });
    },

    methods: {
        async activateServices() {
            try {
                const shopwareServicesService = Shopwell.Service('shopwareServicesService');
                const shopwareServicesStore = useShopwellServicesStore();

                shopwareServicesStore.config = await shopwareServicesService.enableAllServices();

                Shopwell.Store.get('notification').createNotification({
                    title: this.$t('sw-settings-services.index.services-enabled'),
                    variant: 'positive',
                    message: this.$t('sw-settings-services.index.services-scheduled'),
                });
            } catch (exceptionResponse) {
                Shopwell.Store.get('notification').createNotification({
                    title: this.$t('global.default.error'),
                    variant: 'critical',
                    message: extractError(exceptionResponse),
                    autoClose: false,
                });
            }
        },

        async reloadServices() {
            try {
                const shopwareServicesService = Shopwell.Service('shopwareServicesService');

                this.services = await shopwareServicesService.getInstalledServices();
            } catch (exception) {
                this.loadingError = extractError(exception);

                Shopwell.Store.get('notification').createNotification({
                    variant: 'critical',
                    title: this.$t('global.default.error'),
                    message: this.$t('sw-settings-services.exception.service-list'),
                    autoClose: false,
                });

                this.services = [];
            }
        },
    },
});
