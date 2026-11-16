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
            return this.workflowApprovalService.getMode(this.settings.approval.mode, this.settings.approval.version ?? 1);
        },
        modeValue(): string | null {
            if (!this.mode) {
                return null;
            }

            const version = this.mode.version ?? 1;
            return version === 1 ? this.mode.name : `${this.mode.name}@${version}`;
        },
        sourceOptions() {
            return this.workflowApprovalService
                .sources()
                .map((source) => ({ value: source.name, label: this.$t(source.labelKey) }));
        },
        modeCompatible(): boolean {
            return this.workflowApprovalService.supportsMode(
                this.settings.assignee.source,
                this.settings.approval.mode,
                this.settings.approval.version ?? 1,
            );
        },
        modeOptions() {
            const modes = this.workflowApprovalService
                .modes()
                .filter((mode) =>
                    this.workflowApprovalService.supportsMode(this.settings.assignee.source, mode.name, mode.version ?? 1),
                )
                .map((mode) => ({
                    value: (mode.version ?? 1) === 1 ? mode.name : `${mode.name}@${mode.version}`,
                    name: mode.name,
                    version: mode.version ?? 1,
                    label: this.$t(mode.labelKey),
                    disabled: false,
                }));

            if (this.mode && !this.modeCompatible) {
                modes.push({
                    value: (this.mode.version ?? 1) === 1 ? this.mode.name : `${this.mode.name}@${this.mode.version}`,
                    name: this.mode.name,
                    version: this.mode.version ?? 1,
                    label: this.$t(this.mode.labelKey),
                    disabled: true,
                });
            }

            return modes;
        },
    },
    methods: {
        updateAssignee(config: Record<string, unknown>): void {
            if (!this.allowEdit || !this.source) {
                return;
            }

            this.$emit('update-config', { ...this.settings, assignee: { ...this.settings.assignee, config } });
        },
        updateApproval(config: Record<string, unknown>): void {
            if (!this.allowEdit || !this.mode) {
                return;
            }

            this.$emit('update-config', { ...this.settings, approval: { ...this.settings.approval, config } });
        },
        changeSource(source: string): void {
            if (
                !this.allowEdit ||
                source === this.settings.assignee.source ||
                !this.workflowApprovalService.getSource(source)
            ) {
                return;
            }

            const defaultMode = this.workflowApprovalService
                .modes()
                .find((mode) => this.workflowApprovalService.supportsMode(source, mode.name, mode.version ?? 1));
            const compatible = this.workflowApprovalService.supportsMode(
                source,
                this.settings.approval.mode,
                this.settings.approval.version ?? 1,
            );
            let approval = this.settings.approval;

            if (!compatible && defaultMode) {
                approval = {
                    mode: defaultMode.name,
                    ...((defaultMode.version ?? 1) !== 1 ? { version: defaultMode.version } : {}),
                    config: {},
                };
            }

            this.$emit('update-config', { assignee: { source, config: {} }, approval });
        },
        changeMode(value: string): void {
            const selected = this.modeOptions.find((option) => option.value === value && !option.disabled);

            if (!this.allowEdit || !selected) {
                return;
            }

            if (
                selected.name === this.settings.approval.mode &&
                selected.version === (this.settings.approval.version ?? 1)
            ) {
                return;
            }

            this.$emit('update-config', {
                ...this.settings,
                approval: {
                    mode: selected.name,
                    ...(selected.version !== 1 ? { version: selected.version } : {}),
                    config: {},
                },
            });
        },
    },
});
