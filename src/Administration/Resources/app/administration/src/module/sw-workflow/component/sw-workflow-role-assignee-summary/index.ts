/** @sw-package framework */
import { toRef } from 'vue';
import type { PropType } from 'vue';
import useWorkflowRoles from '../../composable/use-workflow-roles';
import template from './sw-workflow-role-assignee-summary.html.twig';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    props: { config: { type: Object as PropType<Record<string, unknown>>, required: true } },
    setup(props) {
        return useWorkflowRoles(toRef(props, 'config'));
    },
    computed: {
        summary(): string {
            if (this.status !== 'ready') {
                return this.$t(`sw-workflow.approval.roles.status.${this.status}`);
            }
            const names = this.roleIds.map((id, index) => {
                const role = this.roles.find((item) => item.id === id);
                const name = role?.name;
                if (!role || !name) {
                    return this.$t('sw-workflow.approval.roles.missing', { role: index + 1 });
                }
                return name;
            });
            return this.$t('sw-workflow.approval.roles.summary', {
                names: names.join(this.$t('sw-workflow.approval.multiple.separator')),
            });
        },
    },
});
