/** @sw-package framework */
import type { PropType } from 'vue';
import type { ApprovalStatus, ApprovalOutcome } from '../../service/approval-flow.api.service';
import template from './sw-approval-flow-status-badge.html.twig';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    name: 'sw-approval-flow-status-badge',
    template,
    props: {
        status: { type: String as PropType<ApprovalStatus>, required: true },
        outcome: { type: String as PropType<ApprovalOutcome | null>, default: null },
    },
    computed: {
        label(): string {
            return this.status === 'completed' && this.outcome ? this.outcome : this.status;
        },
        variant(): string {
            if (this.label === 'approved') {
                return 'positive';
            }
            if (this.label === 'rejected' || this.label === 'terminated') {
                return 'critical';
            }
            if (this.label === 'running') {
                return 'info';
            }
            if (this.label === 'suspended') {
                return 'attention';
            }
            return 'neutral';
        },
    },
});
