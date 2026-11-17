/** @sw-package framework */
import type { PropType } from 'vue';
import type { RuntimeApprovalReminder } from 'src/core/workflow/workflow-runtime.types';
import { absoluteTime } from '../../util/workflow-runtime-time.util';
import template from './sw-workflow-approval-reminder-status.html.twig';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    props: {
        reminder: { type: Object as PropType<RuntimeApprovalReminder | null>, default: null },
    },
    methods: {
        absoluteTime,
    },
});
