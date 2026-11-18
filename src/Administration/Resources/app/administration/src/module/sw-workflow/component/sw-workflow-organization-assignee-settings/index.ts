/** @sw-package framework */
import type { PropType } from 'vue';
import { WORKFLOW_ASSIGNEE_SOURCES } from 'src/core/workflow/workflow-node.constants';
import type { WorkflowNode } from 'src/core/workflow/workflow.types';
import template from './sw-workflow-organization-assignee-settings.html.twig';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    props: {
        node: { type: Object as PropType<WorkflowNode>, required: true },
        config: { type: Object as PropType<Record<string, unknown>>, default: () => ({}) },
        allowEdit: { type: Boolean, default: true },
    },
    emits: ['update-config'],
    computed: {
        isInitiator(): boolean {
            const config = this.node.config as Record<string, { source?: string }>;
            return config.assignee?.source === WORKFLOW_ASSIGNEE_SOURCES.INITIATOR;
        },
        level(): number | null {
            return 'level' in this.config ? (this.config.level as number) : 1;
        },
    },
    methods: {
        changeLevel(level: number | null): void {
            if (this.allowEdit && level !== null && Number.isInteger(level) && level >= 1 && level <= 20) {
                this.$emit('update-config', { ...this.config, level });
            }
        },
        changeContinuous(continuous: boolean): void {
            if (this.allowEdit) {
                this.$emit('update-config', { ...this.config, continuous });
            }
        },
    },
});
