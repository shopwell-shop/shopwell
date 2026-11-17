/** @sw-package framework */
import type { PropType } from 'vue';
import type { RuntimeInstance, WorkflowFormField } from 'src/core/workflow/workflow-runtime.types';
import { validateFormValue } from '../../util/workflow-runtime-form.util';
import template from './sw-workflow-resubmit.html.twig';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    inject: ['workflowRuntimeApiService', 'workflowService'],
    emits: ['resubmitted', 'busy'],
    props: {
        instance: { type: Object as PropType<RuntimeInstance>, required: true },
        disabled: { type: Boolean, default: false },
    },
    data() {
        return {
            showModal: false,
            isLoading: false,
            isSubmitting: false,
            isReady: false,
            showValidation: false,
            revision: 0,
            comment: '',
            error: '',
            fields: [] as WorkflowFormField[],
            values: {} as Record<string, string>,
            initialValues: {} as Record<string, string>,
        };
    },
    computed: {
        fieldErrors(): Record<string, string> {
            const errors: Record<string, string> = {};
            for (const field of this.fields) {
                if (this.isReadOnly(field.key)) {
                    continue;
                }
                const error = validateFormValue(field, this.values[field.key]);
                if (error) {
                    errors[field.key] = this.$t(`sw-workflow.request.validation.${error}`);
                }
            }
            return errors;
        },
    },
    beforeUnmount() {
        this.revision += 1;
    },
    methods: {
        isReadOnly(key: string): boolean {
            return this.instance.readOnlyInputKeys?.includes(key) === true;
        },
        async open(): Promise<void> {
            if (this.disabled || !this.instance.canResubmit) {
                return;
            }
            const revision = ++this.revision;
            this.showModal = true;
            this.isLoading = true;
            this.isReady = false;
            this.error = '';
            this.comment = '';
            this.showValidation = false;
            try {
                const fields = await Promise.all((this.instance.formSchema ?? []).map((field) => this.prepareField(field)));
                if (revision !== this.revision) {
                    return;
                }
                this.fields = fields;
                this.values = Object.fromEntries(
                    fields.map((field) => {
                        const value = this.instance.formData?.[field.key];
                        if (
                            value !== null &&
                            value !== undefined &&
                            typeof value !== 'string' &&
                            typeof value !== 'number'
                        ) {
                            throw new Error(this.$t('sw-workflow.request.failed'));
                        }
                        return [field.key, value === null || value === undefined ? '' : String(value)];
                    }),
                );
                this.initialValues = { ...this.values };
                this.isReady = true;
            } catch (error) {
                if (revision === this.revision) {
                    this.error = this.errorMessage(error);
                }
            } finally {
                if (revision === this.revision) {
                    this.isLoading = false;
                }
            }
        },
        async prepareField(field: WorkflowFormField): Promise<WorkflowFormField> {
            const readOnly = this.isReadOnly(field.key);
            if (!readOnly && field.dataSource) {
                return { ...field, options: await this.workflowService.getFormOptions(field.dataSource) };
            }
            if (!readOnly || (field.type !== 'select' && !field.dataSource)) {
                return field;
            }

            const value = this.instance.formData?.[field.key];
            const options = field.options ?? [];
            if (typeof value !== 'string' || options.some((option) => option.value === value)) {
                return field;
            }

            // Frozen evidence must remain visible without depending on today's option catalog.
            return { ...field, options: [...options, { value, label: value }] };
        },
        close(): void {
            if (!this.isSubmitting) {
                this.revision += 1;
                this.showModal = false;
            }
        },
        async submit(): Promise<void> {
            if (!this.isReady || this.isSubmitting || this.disabled || !this.instance.canResubmit) {
                return;
            }
            this.showValidation = true;
            if (Object.keys(this.fieldErrors).length) {
                return;
            }
            const revision = this.revision;
            this.isSubmitting = true;
            this.$emit('busy', true);
            this.error = '';
            try {
                await this.workflowRuntimeApiService.resubmitInstance(this.instance.id, {
                    round: this.instance.round,
                    formVersion: this.instance.formVersion ?? 0,
                    formData: Object.fromEntries(
                        this.fields
                            .filter(
                                (field) =>
                                    !this.isReadOnly(field.key) && this.values[field.key] !== this.initialValues[field.key],
                            )
                            .map((field) => [field.key, this.values[field.key]]),
                    ),
                    comment: this.comment.trim(),
                });
                if (revision === this.revision) {
                    this.showModal = false;
                    this.$emit('resubmitted');
                }
            } catch (error) {
                if (revision === this.revision) {
                    this.error = this.errorMessage(error);
                }
            } finally {
                if (revision === this.revision) {
                    this.isSubmitting = false;
                    this.$emit('busy', false);
                }
            }
        },
        errorMessage(error: unknown): string {
            const failure = error as { response?: { data?: { errors?: { detail?: string }[] } }; message?: string };
            return failure.response?.data?.errors?.[0]?.detail ?? failure.message ?? this.$t('sw-workflow.request.failed');
        },
    },
});
