/** @sw-package framework */
import type { PropType } from 'vue';
import type { WorkflowApprovalAutomation } from '../../service/workflow-approval.service';
import template from './sw-workflow-approval-automation-settings.html.twig';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    props: {
        config: { type: Object as PropType<WorkflowApprovalAutomation>, default: () => ({}) },
        allowEdit: { type: Boolean, default: true },
    },
    emits: ['update-config'],
    computed: {
        decisions() {
            return ['manual', 'approve', 'reject'].map((value) => ({
                value,
                label: this.$t(`sw-workflow.automation.decisions.${value}`),
            }));
        },
        deduplicationOptions() {
            return ['none', 'adjacent', 'all'].map((value) => ({
                value,
                label: this.$t(`sw-workflow.automation.deduplication.${value}`),
            }));
        },
    },
    methods: {
        changeDecision(decision: WorkflowApprovalAutomation['decision']): void {
            if (this.allowEdit && this.decisions.some((option) => option.value === decision)) {
                this.$emit('update-config', { ...this.config, decision });
            }
        },
        changeInitiator(approveInitiator: boolean): void {
            if (this.allowEdit) {
                this.$emit('update-config', { ...this.config, approveInitiator });
            }
        },
        changeDeduplication(deduplication: WorkflowApprovalAutomation['deduplication']): void {
            if (this.allowEdit && this.deduplicationOptions.some((option) => option.value === deduplication)) {
                this.$emit('update-config', { ...this.config, deduplication });
            }
        },
    },
});
