/**
 * @sw-package framework
 */
import type { SmsSubmissionField, SmsSubmissionFieldOption } from '../../service/sms-gateway-provider.service';
import type { SmsTemplateAuditAnswer } from '../../service/sms-template-audit.service';
import template from './sw-settings-sms-template-submission-modal.html.twig';

const { Mixin } = Shopwell;

/**
 * What an input of the submission form holds. A select answers with the value of the option, a
 * number field with a number, everything else with a string - a field the operator left empty is
 * `null`, so the server reads it as the missing value it is.
 */
type SubmissionFieldValue = string | number | null;

/**
 * The form a provider asks for before it takes a template.
 *
 * The form is not written here: the fields, their labels, their help texts and their options are
 * declared by the provider and handed in, so a provider an extension adds is submitted without a
 * change to this component. What the core already knows about the template - its name, the body of
 * the default language, its scene - arrives as `prefill` and is filled in, so an operator confirms
 * it instead of typing the same text again.
 *
 * The modal owns the call and its error, and only reports a success: the caller closes it and
 * reloads the row, which is what keeps the reload in one place.
 *
 * @sw-package framework
 * @private
 */
export default Shopwell.Component.wrapComponentConfig({
    name: 'sw-settings-sms-template-submission-modal',

    template,

    inject: ['smsTemplateAuditService'],

    mixins: [Mixin.getByName('notification')],

    emits: ['modal-close', 'submitted'],

    props: {
        mapping: {
            type: Object,
            required: true,
        },
        template: {
            type: Object,
            required: true,
        },
        submissionFields: {
            type: Array,
            required: true,
        },
    },

    data(): {
        fieldValues: Record<string, SubmissionFieldValue>;
        isSubmitting: boolean;
    } {
        return {
            fieldValues: {},
            isSubmitting: false,
        };
    },

    computed: {
        /**
         * The prop is an object, so the entities it carries are typed here once instead of on every
         * use.
         */
        mappingEntity(): EntitySchema.Entity<'sms_template_mapping'> {
            return this.mapping as EntitySchema.Entity<'sms_template_mapping'>;
        },

        templateEntity(): EntitySchema.Entity<'sms_template'> {
            return this.template as EntitySchema.Entity<'sms_template'>;
        },

        fields(): SmsSubmissionField[] {
            return this.submissionFields as SmsSubmissionField[];
        },

        /**
         * The body of the default language, which is the one body a mapping row carries.
         *
         * The page shows the body of the language the administration is switched to, so the body of
         * the default language is read from the translations the page already loaded - a language
         * without a body of its own inherits the one of the default language, which is exactly the
         * body the renderer would fall back to.
         */
        defaultLanguageContent(): string {
            const systemLanguageId = Shopwell.Context.api.systemLanguageId;
            const translations = this.templateEntity.translations;

            if (translations && systemLanguageId) {
                const translation = Array.from(translations).find((entry) => entry.languageId === systemLanguageId);

                if (translation && typeof translation.content === 'string' && translation.content !== '') {
                    return translation.content;
                }
            }

            return this.templateEntity.content ?? '';
        },
    },

    created() {
        this.fieldValues = this.initialFieldValues();
    },

    methods: {
        initialFieldValues(): Record<string, SubmissionFieldValue> {
            const values: Record<string, SubmissionFieldValue> = {};

            this.fields.forEach((field) => {
                values[field.name] = this.initialValueOf(field);
            });

            return values;
        },

        /**
         * What a field starts with: what the core knows about the template, else the default the
         * provider declared, else nothing.
         */
        initialValueOf(field: SmsSubmissionField): SubmissionFieldValue {
            if (field.prefill === 'templateName') {
                return this.templateEntity.technicalName ?? '';
            }

            if (field.prefill === 'content') {
                return this.defaultLanguageContent;
            }

            if (field.prefill === 'scene') {
                return this.templateEntity.scene ?? '';
            }

            if (field.default !== undefined && field.default !== null) {
                return field.default;
            }

            return field.type === 'string' || field.type === 'text' ? '' : null;
        },

        /**
         * The label of a field.
         *
         * A provider may group the labels of its options under the very key of the field - the use of
         * a template is one such field, its options are the single uses. The group then holds no text
         * of its own, so its own label is the `label` entry of the group; a field without a group is
         * keyed directly.
         */
        fieldLabel(field: SmsSubmissionField): string {
            const groupedLabel = `${field.labelSnippet}.label`;

            if (this.$te(groupedLabel)) {
                return this.$t(groupedLabel);
            }

            const label = this.$te(field.labelSnippet) ? this.$t(field.labelSnippet) : field.name;

            // a snippet that exists but holds no text answers with its own key
            return label === field.labelSnippet ? field.name : label;
        },

        helpTextOf(field: SmsSubmissionField): string | null {
            if (!field.helpSnippet) {
                return null;
            }

            return this.$te(field.helpSnippet) ? this.$t(field.helpSnippet) : null;
        },

        optionsOf(field: SmsSubmissionField): Array<{ value: string | number; label: string }> {
            return (field.options ?? []).map((option: SmsSubmissionFieldOption) => {
                return {
                    value: option.value,
                    label: this.$te(option.labelSnippet) ? this.$t(option.labelSnippet) : String(option.value),
                };
            });
        },

        isEmpty(value: SubmissionFieldValue | undefined): boolean {
            return value === null || value === undefined || (typeof value === 'string' && value.trim() === '');
        },

        /**
         * A required field is checked before the call, so a report the provider would refuse for a
         * missing value is not sent at all - the server checks the same definitions again.
         */
        missingFields(): SmsSubmissionField[] {
            return this.fields.filter((field) => field.required && this.isEmpty(this.fieldValues[field.name]));
        },

        async onSubmit(): Promise<void> {
            if (this.missingFields().length > 0) {
                this.createNotificationError({
                    message: this.$t('sw-settings-sms.notification.missingFields'),
                });

                return;
            }

            this.isSubmitting = true;

            try {
                const answer: SmsTemplateAuditAnswer = await this.smsTemplateAuditService.submit(
                    this.mappingEntity.id,
                    this.fieldValues,
                );

                if (answer.errorCode !== null || answer.errorMessage !== null) {
                    // the shop rendered the reason already, the code is only a fallback when it has
                    // no text for it
                    this.createNotificationError({
                        message: answer.errorMessage ?? answer.errorCode ?? '',
                    });

                    return;
                }

                this.$emit('submitted', answer);
            } catch (error) {
                this.createNotificationError({
                    message: (error as Error).message,
                });
            } finally {
                this.isSubmitting = false;
            }
        },

        onClose(): void {
            if (this.isSubmitting) {
                return;
            }

            this.$emit('modal-close');
        },

        onModalChange(isOpen: boolean): void {
            if (!isOpen) {
                this.onClose();
            }
        },
    },
});
