/** @sw-package framework */
import type { PropType } from 'vue';
import { WORKFLOW_NODE_TYPES } from 'src/core/workflow/workflow-node.constants';
import type { WorkflowFormPermission } from 'src/core/workflow/workflow-runtime.types';
import type { WorkflowDocument, WorkflowNode } from 'src/core/workflow/workflow.types';
import template from './sw-workflow-copy-settings.html.twig';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    inject: ['workflowRequestFormService'],

    props: {
        document: { type: Object as PropType<WorkflowDocument | null>, default: null },
        node: { type: Object as PropType<WorkflowNode>, required: true },
        allowEdit: { type: Boolean, default: true },
    },

    emits: ['update-config'],

    data() {
        return { activeTab: 'settings' };
    },
    computed: {
        tabs() {
            return ['settings', 'form'].map((name) => ({
                name,
                label: this.$t(
                    name === 'settings' ? 'sw-workflow.formPermissions.settings' : 'sw-workflow.formPermissions.title',
                ),
            }));
        },
        formFields() {
            const start = this.document?.nodes.find((node) => node.type === WORKFLOW_NODE_TYPES.START);
            return start
                ? this.workflowRequestFormService.getFields(start.config, this.$t('sw-workflow.inputs.reason'))
                : [];
        },
        formPermissions(): Record<string, WorkflowFormPermission> {
            return ('formPermissions' in this.node.config ? this.node.config.formPermissions : {}) as Record<
                string,
                WorkflowFormPermission
            >;
        },
        configuration(): Record<string, unknown> {
            return Array.isArray(this.node.config) ? {} : this.node.config;
        },
    },

    methods: {
        updateFormPermissions(formPermissions: Record<string, WorkflowFormPermission>): void {
            if (this.allowEdit) {
                const configuration = Array.isArray(this.node.config) ? {} : this.node.config;
                this.$emit('update-config', { ...configuration, formPermissions });
            }
        },
        updateRecipients(config: Record<string, unknown>): void {
            if (!this.allowEdit) {
                return;
            }

            this.$emit('update-config', { ...this.configuration, userIds: config.userIds });
        },
    },
});
