/**
 * @sw-package after-sales
 */
import { defineComponent } from 'vue';
import type { PropType } from 'vue';
import template from './sw-settings-order-return-reason-modal.html.twig';

const { Mixin } = Shopwell;

type OrderReturnReason = EntitySchema.Entity<'order_return_reason'>;

/**
 * Creates or edits one reason.
 *
 * The label is translated, so it is written in the language the administration is currently in - that
 * is the language of the row the operator sees. `active` and `position` are the same in every language,
 * which is why they sit next to the label instead of in a separate section.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default defineComponent({
    template,

    mixins: [Mixin.getByName('notification')],

    inject: ['repositoryFactory'],

    emits: ['modal-close', 'saved'],

    props: {
        reason: {
            type: Object as PropType<OrderReturnReason | null>,
            required: false,
            default: null,
        },
    },

    data(): { label: string; position: number; active: boolean; isSaving: boolean } {
        return {
            label: this.reason?.label ?? '',
            position: this.reason?.position ?? 0,
            active: this.reason?.active ?? true,
            isSaving: false,
        };
    },

    computed: {
        isEdit(): boolean {
            return Boolean(this.reason?.id);
        },

        title(): string {
            return this.isEdit
                ? this.$t('sw-settings-order-return.reason.modalTitleEdit')
                : this.$t('sw-settings-order-return.reason.modalTitleCreate');
        },

        /**
         * A reason without a label can not be picked in the storefront - it would render an empty
         * option, so the button stays disabled until there is one.
         */
        isSaveDisabled(): boolean {
            return this.isSaving || this.label.trim() === '';
        },
    },

    methods: {
        onClose(): void {
            if (this.isSaving) {
                return;
            }

            this.$emit('modal-close');
        },

        onSave(): void {
            if (this.isSaveDisabled) {
                return;
            }

            this.isSaving = true;

            const repository = this.repositoryFactory.create('order_return_reason');
            const entity = this.reason ?? repository.create();

            entity.label = this.label.trim();
            entity.position = this.position;
            entity.active = this.active;

            repository
                .save(entity)
                .then(() => {
                    this.createNotificationSuccess({
                        message: this.$t('sw-settings-order-return.reason.notificationSaveSuccess'),
                    });

                    this.$emit('saved');
                })
                .catch((error: { response?: { data?: { errors?: Array<{ detail?: string }> } } }) => {
                    const detail = error.response?.data?.errors?.[0]?.detail;

                    this.createNotificationError({
                        message: detail || this.$t('sw-settings-order-return.reason.notificationSaveError'),
                    });
                })
                .finally(() => {
                    this.isSaving = false;
                });
        },
    },
});
