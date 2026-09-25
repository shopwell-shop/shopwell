/*
 * @sw-package inventory
 */
import template from './sw-product-measurement-form.html.twig';
import './sw-product-measurement-form.scss';

const { Mixin, Utils } = Shopwell;
const { mapPropertyErrors } = Shopwell.Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    mixins: [Mixin.getByName('placeholder')],

    props: {
        allowEdit: {
            type: Boolean,
            required: true,
        },
    },

    computed: {
        product() {
            return Shopwell.Store.get('swProductDetail').product;
        },

        parentProduct() {
            return Shopwell.Store.get('swProductDetail').parentProduct;
        },

        lengthUnit() {
            return Shopwell.Store.get('swProductDetail').lengthUnit;
        },

        weightUnit() {
            return Shopwell.Store.get('swProductDetail').weightUnit;
        },

        ...mapPropertyErrors('product', [
            'width',
            'height',
            'length',
            'weight',
        ]),
    },

    methods: {
        onUpdateLengthUnit(unit, type) {
            if (type === 'width') {
                this.convertHeight(unit);
                this.convertLength(unit);
            }

            if (type === 'height') {
                this.convertWidth(unit);
                this.convertLength(unit);
            }

            if (type === 'length') {
                this.convertWidth(unit);
                this.convertHeight(unit);
            }

            Shopwell.Store.get('swProductDetail').setLengthUnit(unit);
        },

        convertWidth(unit) {
            if (!this.product.width) {
                return;
            }

            this.product.width = Utils.unitConversion.convert(this.product.width, this.lengthUnit, unit);
        },

        convertHeight(unit) {
            if (!this.product.height) {
                return;
            }

            this.product.height = Utils.unitConversion.convert(this.product.height, this.lengthUnit, unit);
        },

        convertLength(unit) {
            if (!this.product.length) {
                return;
            }

            this.product.length = Utils.unitConversion.convert(this.product.length, this.lengthUnit, unit);
        },

        onUpdateWeightUnit(unit) {
            Shopwell.Store.get('swProductDetail').setWeightUnit(unit);
        },
    },
};
