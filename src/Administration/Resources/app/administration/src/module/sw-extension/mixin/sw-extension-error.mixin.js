import { defineComponent } from 'vue';

/**
 * @sw-package checkout
 * @private
 */
export default Shopwell.Mixin.register(
    'sw-extension-error',
    defineComponent({
        mixins: [Shopwell.Mixin.getByName('notification')],

        methods: {
            showExtensionErrors(errorResponse) {
                Shopwell.Service('extensionErrorService')
                    .handleErrorResponse(errorResponse, this)
                    .forEach((notification) => {
                        this.createNotificationError(notification);
                    });
            },
        },
    }),
);
