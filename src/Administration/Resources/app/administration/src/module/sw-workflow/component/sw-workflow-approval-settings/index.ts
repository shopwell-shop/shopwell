/** @sw-package framework */
import type { PropType } from 'vue';
import type { WorkflowNode } from 'src/core/workflow/workflow.types';
import template from './sw-workflow-approval-settings.html.twig';
import './sw-workflow-approval-settings.scss';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    inject: ['workflowApprovalService'],
    props: { node: { type: Object as PropType<WorkflowNode>, required: true }, allowEdit: { type: Boolean, default: true } },
    emits: ['update-config'],
    computed: {
        settings() {
            return this.workflowApprovalService.read(this.node);
        },
        source() {
            return this.workflowApprovalService.getSource(this.settings.assignee.source);
        },
        mode() {
            return this.workflowApprovalService.getMode(this.settings.approval.mode);
        },
        sourceOptions() {
            return this.workflowApprovalService
                .sources()
                .map((source) => ({ value: source.name, label: this.$t(source.labelKey) }));
        },
        modeCompatible(): boolean {
            return !this.source?.supportedModes || this.source.supportedModes.includes(this.settings.approval.mode);
        },
        modeOptions() {
            const modes = this.workflowApprovalService
                .modes()
                .filter((mode) => !this.source?.supportedModes || this.source.supportedModes.includes(mode.name))
                .map((mode) => ({ value: mode.name, label: this.$t(mode.labelKey), disabled: false }));
            if (this.mode && !this.modeCompatible)
                modes.push({ value: this.mode.name, label: this.$t(this.mode.labelKey), disabled: true });
            return modes;
        },
    },
    methods: {
        updateAssignee(config: Record<string, unknown>): void {
            if (!this.allowEdit || !this.source) return;
            this.$emit('update-config', { ...this.settings, assignee: { ...this.settings.assignee, config } });
        },
        updateApproval(config: Record<string, unknown>): void {
            if (!this.allowEdit || !this.mode) return;
            this.$emit('update-config', { ...this.settings, approval: { ...this.settings.approval, config } });
        },
        changeSource(source: string): void {
            if (
                !this.allowEdit ||
                source === this.settings.assignee.source ||
                !this.workflowApprovalService.getSource(source)
            )
                return;
            const supportedModes = this.workflowApprovalService.getSource(source)?.supportedModes;
            const defaultMode = supportedModes?.find((name) => this.workflowApprovalService.getMode(name));
            const approval =
                supportedModes && !supportedModes.includes(this.settings.approval.mode) && defaultMode
                    ? { mode: defaultMode, config: {} }
                    : this.settings.approval;
            this.$emit('update-config', { assignee: { source, config: {} }, approval });
        },
        changeMode(mode: string): void {
            if (
                !this.allowEdit ||
                mode === this.settings.approval.mode ||
                !this.modeOptions.some((option) => option.value === mode && !option.disabled)
            )
                return;
            this.$emit('update-config', { ...this.settings, approval: { mode, config: {} } });
        },
    },
});
