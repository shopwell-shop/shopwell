/** @sw-package framework */
import type { WorkflowFormField } from 'src/core/workflow/workflow-runtime.types';
/** @private */
export type FormError = 'required' | 'text' | 'amount' | 'number' | 'date' | 'select';

function isMissingFormValue(value: unknown): boolean {
    return value === '' || value === null || value === undefined;
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
export function serializeForm(fields: WorkflowFormField[], values: Record<string, string>): Record<string, string> {
    return Object.fromEntries(fields.map((field) => [field.key, values[field.key] ?? '']));
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
