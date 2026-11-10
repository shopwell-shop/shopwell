/**
 * @sw-package after-sales
 */
import useNotification from 'src/app/composables/use-notification';
import template from './sw-settings-order-return-index.html.twig';

/**
 * The return rules.
 *
 * The page owns nothing but the save button: every field it renders is declared by the config domain
 * `core.orderReturn`, so the labels, the help texts and the defaults live in the config xml next to the
 * keys the server reads - and not twice.
 */
/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,

    inject: ['acl'],

    setup() {
        return useNotification();
    },

    data(): { isLoading: boolean; isSaveSuccessful: boolean } {
        return {
            isLoading: false,
            isSaveSuccessful: false,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        canEdit(): boolean {
            return this.acl.can('system.system_config');
        },
    },

    methods: {
        onSave(): void {
            const systemConfig = this.$refs.systemConfig as { saveAll: () => Promise<unknown> } | undefined;

            if (!systemConfig || !this.canEdit) {
                return;
            }

            this.isSaveSuccessful = false;
            this.isLoading = true;

            systemConfig
                .saveAll()
                .then(() => {
                    this.isSaveSuccessful = true;

                    this.createNotificationSuccess({
                        message: this.$t('sw-settings-order-return.notification.saveSuccess'),
                    });
                })
                .catch((error: unknown) => {
                    this.createNotificationError({
                        message: (error as Error).message || this.$t('sw-settings-order-return.notification.saveError'),
                    });
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        onSaveFinish(): void {
            this.isSaveSuccessful = false;
        },

        onLoadingChanged(loading: boolean): void {
            this.isLoading = loading;
        },

        onOpenReasons(): void {
            void this.$router.push({ name: 'sw.settings.order.return.reason' });
        },
    },
});
