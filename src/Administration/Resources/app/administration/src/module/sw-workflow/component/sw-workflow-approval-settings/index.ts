/** @sw-package framework */
import type { PropType } from 'vue';
import { WORKFLOW_NODE_TYPES } from 'src/core/workflow/workflow-node.constants';
import type { WorkflowFormPermission } from 'src/core/workflow/workflow-runtime.types';
import type { WorkflowDocument, WorkflowNode } from 'src/core/workflow/workflow.types';
import { DEFAULT_APPROVAL_OPERATIONS } from '../../service/workflow-approval.service';
import template from './sw-workflow-approval-settings.html.twig';
import './sw-workflow-approval-settings.scss';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    inject: ['workflowApprovalService', 'workflowRequestFormService'],
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
        settings() {
            return this.workflowApprovalService.read(this.node);
        },
        deadlineEnabled(): boolean {
            return 'deadline' in this.settings;
        },
        deadlineMinutes(): number | null {
            return this.settings.deadline?.minutes ?? null;
        },
        operations() {
            return this.workflowApprovalService.operations();
        },
        enabledOperations(): string[] {
            if (!('operations' in this.settings)) {
                return DEFAULT_APPROVAL_OPERATIONS;
            }

            return Array.isArray(this.settings.operations) ? this.settings.operations : [];
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
        updateFormPermissions(formPermissions: Record<string, WorkflowFormPermission>): void {
            if (this.allowEdit) {
                const configuration = Array.isArray(this.node.config) ? {} : this.node.config;
                this.$emit('update-config', { ...configuration, formPermissions });
            }
        },
        toggleDeadline(enabled: boolean): void {
            if (!this.allowEdit) {
                return;
            }
            const settings = { ...this.settings };
            if (enabled) {
                settings.deadline = { minutes: 1440, remind: true };
            } else {
                delete settings.deadline;
            }
            this.$emit('update-config', settings);
        },
        changeDeadlineMinutes(minutes: number | null): void {
            if (!this.allowEdit || !this.deadlineEnabled) {
                return;
            }
            this.$emit('update-config', {
                ...this.settings,
                deadline: { ...this.settings.deadline, minutes },
            });
        },
        changeDeadlineReminder(remind: boolean): void {
            if (!this.allowEdit || !this.deadlineEnabled) {
                return;
            }
            this.$emit('update-config', {
                ...this.settings,
                deadline: { ...this.settings.deadline, remind },
            });
        },
        changeOperation(name: string, enabled: boolean): void {
            if (!this.allowEdit || !this.operations.some((operation) => operation.name === name)) {
                return;
            }

            const operations = this.enabledOperations.filter((operation) => operation !== name);
            if (enabled) {
                operations.push(name);
            }

            this.$emit('update-config', { ...this.settings, operations });
        },
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

            this.$emit('update-config', { ...this.settings, assignee: { source, config: {} }, approval });
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
