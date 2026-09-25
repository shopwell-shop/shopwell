/**
 * @sw-package framework
 */
import useSession from 'src/app/composables/use-session';
import template from './sw-settings-services-grant-permissions-modal.html.twig';
import './sw-settings-services-grant-permissions-modal.scss';
import { useShopwellServicesStore } from '../../store/shopwell-services.store';
import extractErrorMessage from '../../composables/extract-error';
import { grantPermissions } from '../../composables/permissions';

/**
 * @private
 */
export default Shopwell.Component.wrapComponentConfig({
    name: 'sw-settings-services-grant-permissions-modal',
    template,

    data() {
        const assetFilter = Shopwell.Filter.getByName('asset');

        return {
            grantPermissionsBackground: assetFilter(
                '/administration/administration/static/img/services/grant-permissions-background.svg',
            ),
            isLoading: false,
        };
    },

    computed: {
        feedbackLink() {
            return useShopwellServicesStore().currentRevision?.links['docs-url'] ?? '';
        },

        showGrantPermissionsModal: {
            get() {
                return useShopwellServicesStore().showGrantPermissionsModal;
            },
            set(value: boolean) {
                useShopwellServicesStore().showGrantPermissionsModal = value;
            },
        },
    },

    methods: {
        prepareRevisions(isOpen: boolean) {
            this.showGrantPermissionsModal = isOpen;

            if (this.showGrantPermissionsModal && !this.feedbackLink) {
                Shopwell.Service('serviceRegistryClient')
                    .getCurrentRevision(useSession().currentLocale.value as string)
                    .then((revisions) => {
                        useShopwellServicesStore().revisions = revisions;
                    })
                    .catch(() => {});
            }
        },

        async grantPermissions(done: () => void) {
            try {
                this.isLoading = true;

                await grantPermissions();
            } catch (exception) {
                Shopwell.Store.get('notification').createNotification({
                    variant: 'critical',
                    title: this.$t('global.default.error'),
                    message: extractErrorMessage(exception),
                });
            } finally {
                this.isLoading = false;
                done();
            }
        },
    },
});
