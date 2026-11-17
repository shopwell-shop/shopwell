/** @sw-package framework */
import type { PropType } from 'vue';
import type { WorkflowFormField, WorkflowFormPermission } from 'src/core/workflow/workflow-runtime.types';
import { WORKFLOW_FORM_ACCESS } from 'src/core/workflow/workflow-runtime.constants';
import template from './sw-workflow-form-permissions.html.twig';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    props: {
        fields: { type: Array as PropType<WorkflowFormField[]>, required: true },
        permissions: { type: Object as PropType<Record<string, WorkflowFormPermission>>, default: () => ({}) },
        allowEdit: { type: Boolean, default: true },
        copy: { type: Boolean, default: false },
    },
    emits: ['update-permissions'],
    computed: {
        options() {
            const access = [WORKFLOW_FORM_ACCESS.HIDDEN, WORKFLOW_FORM_ACCESS.READONLY];
            const values: WorkflowFormPermission['access'][] = this.copy
                ? access
                : [...access, WORKFLOW_FORM_ACCESS.EDITABLE];
            return values.map((value) => ({ value, label: this.$t(`sw-workflow.formPermissions.${value}`) }));
        },
    },
    methods: {
        permission(key: string): WorkflowFormPermission {
            return this.permissions[key] ?? { access: WORKFLOW_FORM_ACCESS.READONLY, required: false };
        },
        changeAccess(key: string, access: WorkflowFormPermission['access']): void {
            if (!this.allowEdit || (this.copy && access === WORKFLOW_FORM_ACCESS.EDITABLE)) {
                return;
            }
            this.$emit('update-permissions', {
                ...this.permissions,
                [key]: { access, required: access === WORKFLOW_FORM_ACCESS.EDITABLE && this.permission(key).required },
            });
        },
        changeRequired(key: string, required: boolean): void {
            if (!this.allowEdit || this.copy || this.permission(key).access !== WORKFLOW_FORM_ACCESS.EDITABLE) {
                return;
            }
            this.$emit('update-permissions', { ...this.permissions, [key]: { ...this.permission(key), required } });
        },
    },
});
