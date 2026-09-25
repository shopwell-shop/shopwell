import { MAIN_HIDDEN } from '@shopwell-ag/meteor-admin-sdk/es/location';
import template from './sw-hidden-iframes.html.twig';

/**
 * @sw-package framework
 *
 * @private
 */
export default {
    template,

    computed: {
        extensions() {
            return Shopwell.Store.get('extensions').privilegedExtensions;
        },

        MAIN_HIDDEN() {
            return MAIN_HIDDEN;
        },
    },
};
