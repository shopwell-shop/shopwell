/** @sw-package framework */
import { computed } from 'vue';
import type { PropType } from 'vue';
import useWorkflowUser from '../../composable/use-workflow-user';
import template from './sw-workflow-user-assignee-summary.html.twig';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    props: { config: { type: Object as PropType<Record<string, unknown>>, required: true } },
    setup(props) {
        return useWorkflowUser(computed(() => (typeof props.config.userId === 'string' ? props.config.userId : null)));
    },
});
