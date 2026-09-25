/*
 * @sw-package inventory
 */

import template from './sw-product-deliverability-form.html.twig';

const { Mixin } = Shopwell;
const { mapPropertyErrors } = Shopwell.Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    mixins: [Mixin.getByName('placeholder')],

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

        loading() {
            return Shopwell.Store.get('swProductDetail').loading;
        },

        showModeSetting() {
            return Shopwell.Store.get('swProductDetail').showModeSetting;
        },

        ...mapPropertyErrors('product', [
            'stock',
            'deliveryTimeId',
            'isCloseout',
            'maxPurchase',
            'purchaseSteps',
            'minPurchase',
            'shippingFree',
            'restockTime',
        ]),
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (typeof this.product.stock === 'undefined') {
                this.product.stock = 0;
            }
        },
    },
};
