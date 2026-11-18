/** @sw-package framework */
import type { PropType } from 'vue';
import type { WorkflowDocument } from 'src/core/workflow/workflow.types';
import { WORKFLOW_NODE_TYPES } from 'src/core/workflow/workflow-node.constants';
import { workflowFormFields } from '../../util/workflow-form.util';
import template from './sw-workflow-form-assignee-settings.html.twig';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    props: {
        config: { type: Object as PropType<Record<string, unknown>>, required: true },
        document: { type: Object as PropType<WorkflowDocument | null>, default: null },
        allowEdit: { type: Boolean, default: true },
    },
    emits: ['update-config'],
    computed: {
        peopleFields() {
            const start = this.document?.nodes.find(
                (node) => node.type === WORKFLOW_NODE_TYPES.START && node.typeVersion === 1,
            );
            return start ? workflowFormFields(start.config).filter((field) => ['user', 'users'].includes(field.type)) : [];
        },
        options(): { value: string; label: string }[] {
            const options = this.peopleFields.map((field) => ({ value: field.key, label: field.label }));
            if (
                typeof this.config.fieldKey === 'string' &&
                !options.some((option) => option.value === this.config.fieldKey)
            ) {
                options.push({ value: this.config.fieldKey, label: this.$t('sw-workflow.approval.formFieldUnavailable') });
            }
            return options;
        },
    },
    methods: {
        select(key: string): void {
            if (this.allowEdit && this.peopleFields.some((field) => field.key === key)) {
                this.$emit('update-config', { fieldKey: key });
            }
        },
    },
});
