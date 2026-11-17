/** @sw-package framework */
import { toRef } from 'vue';
import type { PropType } from 'vue';
import useWorkflowPositions from '../../composable/use-workflow-positions';
import template from './sw-workflow-position-assignee-summary.html.twig';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    props: { config: { type: Object as PropType<Record<string, unknown>>, required: true } },
    setup(props) {
        return useWorkflowPositions(toRef(props, 'config'));
    },
    computed: {
        summary(): string {
            if (this.status !== 'ready') {
                return this.$t(`sw-workflow.approval.positions.status.${this.status}`);
            }
            const names = this.positionIds.map((id, index) => {
                const position = this.positions.find((item) => item.id === id);
                const name = position?.translated?.name || position?.name;
                if (!position || !name) {
                    return this.$t('sw-workflow.approval.positions.missing', { position: index + 1 });
                }
                return position.active ? name : this.$t('sw-workflow.approval.positions.unavailable', { name });
            });
            return this.$t('sw-workflow.approval.positions.summary', {
                names: names.join(this.$t('sw-workflow.approval.multiple.separator')),
            });
        },
    },
});
