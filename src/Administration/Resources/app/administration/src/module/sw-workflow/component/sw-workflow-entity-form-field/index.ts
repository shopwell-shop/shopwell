/** @sw-package framework */
import type { PropType } from 'vue';
import type { WorkflowFormField, WorkflowFormValue } from 'src/core/workflow/workflow-runtime.types';
import template from './sw-workflow-entity-form-field.html.twig';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    props: {
        field: { type: Object as PropType<WorkflowFormField>, required: true },
        modelValue: { type: [String, Array] as PropType<WorkflowFormValue>, default: '' },
        readOnly: { type: Boolean, default: false },
        disabled: { type: Boolean, default: false },
    },
    emits: ['update:model-value'],
    methods: {
        mediaUploaded(media: { id?: string; mediaId?: string }): void {
            const id = media.mediaId ?? media.id;
            if (!id) {
                return;
            }
            const value = this.field.type === 'media' ? id : [...(Array.isArray(this.modelValue) ? this.modelValue : []), id];
            this.$emit('update:model-value', value);
        },
    },
});
