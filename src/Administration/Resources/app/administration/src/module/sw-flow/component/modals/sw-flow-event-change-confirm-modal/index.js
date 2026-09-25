import template from './sw-flow-event-change-confirm-modal.html.twig';
import './sw-flow-event-change-confirm-modal.scss';

const { Component, Store } = Shopwell;
const { EntityCollection } = Shopwell.Data;
const { mapState } = Component.getComponentHelper();

/**
 * @private
 * @sw-package after-sales
 */
export default {
    template,

    emits: ['modal-confirm', 'modal-close'],

    computed: {
        ...mapState(() => Store.get('swFlow'), ['sequences']),
    },

    methods: {
        onConfirm() {
            const sequencesCollection = new EntityCollection(
                this.sequences.source,
                this.sequences.entity,
                Shopwell.Context.api,
                null,
                [],
            );

            Store.get('swFlow').setSequences(sequencesCollection);

            this.$emit('modal-confirm');
            this.onClose();
        },

        onClose() {
            this.$emit('modal-close');
        },
    },
};
