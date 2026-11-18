/** @sw-package framework */
import type { WorkflowFormField, WorkflowFormValue, WorkflowFormFieldType } from 'src/core/workflow/workflow-runtime.types';
/** @private */
export type FormError = 'required' | WorkflowFormFieldType;

function isMissingFormValue(value: unknown): boolean {
    return value === '' || value === null || value === undefined || (Array.isArray(value) && value.length === 0);
}

function isValidCalendarDate(value: string): boolean {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value) || value.startsWith('0000')) {
        return false;
    }

    const date = new Date(`${value}T00:00:00Z`);
    return Number.isFinite(date.getTime()) && date.toISOString().slice(0, 10) === value;
}

/** @private */
export function validateFormValue(field: WorkflowFormField, value: unknown): FormError | null {
    if (isMissingFormValue(value)) {
        return field.required ? 'required' : null;
    }

    if (['user', 'users', 'department'].includes(field.type)) {
        return validateEntitySelection(field, value);
    }

    if (typeof value !== 'string') {
        return field.type;
    }

    if (field.dataSource) {
        return validateSelection(field, value);
    }

    switch (field.type) {
        case 'text':
            return validateText(field, value);
        case 'amount':
            return /^\d{1,12}(\.\d{1,2})?$/.test(value) ? null : 'amount';
        case 'number':
            return /^-?\d{1,12}(\.\d{1,6})?$/.test(value) ? null : 'number';
        case 'date':
            return isValidCalendarDate(value) ? null : 'date';
        case 'select':
            return validateSelection(field, value);
        default:
            return field.type;
    }
}

/** @private */
export function serializeForm(
    fields: WorkflowFormField[],
    values: Record<string, WorkflowFormValue>,
): Record<string, WorkflowFormValue> {
    return Object.fromEntries(fields.map((field) => [field.key, values[field.key] ?? (field.type === 'users' ? [] : '')]));
}

/** @private */
export function formatFormValue(field: WorkflowFormField, value: unknown, locale: string): string | null {
    if (isMissingFormValue(value)) {
        return '—';
    }

    switch (field.type) {
        case 'select':
            return selectLabel(field, value);
        case 'date':
            if (typeof value !== 'string' || !isValidCalendarDate(value)) {
                return null;
            }

            return new Intl.DateTimeFormat(locale, { timeZone: 'UTC' }).format(new Date(`${value}T00:00:00Z`));
        case 'amount':
            if (typeof value !== 'string' || validateFormValue(field, value) !== null) {
                return null;
            }

            return formatExactAmount(value, locale);
        default:
            return null;
    }
}

function formatExactAmount(value: string, locale: string): string {
    // Keep the fraction as a string: converting a 12-digit amount to Number can round its cents.
    const [integer, fraction = ''] = value.split('.');
    const separator = new Intl.NumberFormat(locale).formatToParts(1.1).find((part) => part.type === 'decimal')?.value ?? '.';
    return `${new Intl.NumberFormat(locale).format(Number(integer))}${separator}${fraction.padEnd(2, '0')}`;
}

function selectLabel(field: WorkflowFormField, value: unknown): string | null {
    return field.options?.find((option) => option.value === value)?.label ?? (typeof value === 'string' ? value : null);
}

function validateText(field: WorkflowFormField, value: string): FormError | null {
    if (field.required && !value.trim()) {
        return 'required';
    }

    return Array.from(value).length > 4000 ? 'text' : null;
}

function validateSelection(field: WorkflowFormField, value: string): FormError | null {
    return field.options?.some((option) => option.value === value) ? null : 'select';
}

/** Restore frozen typed form values without coercing an identifier list to text. @private */
export function restoreFormValue(field: WorkflowFormField, value: unknown): WorkflowFormValue {
    if (value === null || value === undefined) {
        return field.type === 'users' ? [] : '';
    }
    if (field.type === 'users' && Array.isArray(value) && value.every((id): id is string => typeof id === 'string')) {
        return [...value];
    }
    if (typeof value === 'string' || typeof value === 'number') {
        return String(value);
    }
    throw new Error('Unsupported workflow form value.');
}

function validateEntitySelection(field: WorkflowFormField, value: unknown): FormError | null {
    const ids = field.type === 'users' ? value : [value];
    if (!Array.isArray(ids) || ids.length > 50 || new Set(ids).size !== ids.length) {
        return field.type;
    }
    const valid = ids.every(
        (id: unknown) =>
            typeof id === 'string' &&
            /^[a-f0-9]{32}$/i.test(id) &&
            (!field.candidateIds || field.candidateIds.includes(id.toLowerCase())),
    );
    return valid ? null : field.type;
}
