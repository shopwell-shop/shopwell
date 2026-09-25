/*
 * @sw-package inventory
 */

import template from './sw-product-settings-form.html.twig';
import './sw-product-settings-form.scss';

const { mapPropertyErrors } = Shopwell.Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    props: {
        allowEdit: {
            type: Boolean,
            required: false,
            default: true,
        },
    },

    computed: {
        product() {
            return Shopwell.Store.get('swProductDetail').product;
        },

        parentProduct() {
            return Shopwell.Store.get('swProductDetail').parentProduct;
        },

        ...mapPropertyErrors('product', [
            'releaseDate',
            'stock',
            'minPurchase',
            'maxPurchase',
            'ean',
            'manufacturerNumber',
            'shippingFree',
            'markAsTopseller',
        ]),
    },
};
