/** @sw-package framework */
import type { WorkflowFormField } from 'src/core/workflow/workflow-runtime.types';
/** @private */
export type { WorkflowFormField } from 'src/core/workflow/workflow-runtime.types';

/** @private */
export type FormError = 'required' | 'text' | 'amount' | 'number' | 'date' | 'select';

function empty(value: unknown): boolean {
    return value === '' || value === null || value === undefined;
}

function validDate(value: string): boolean {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value) || value.startsWith('0000')) return false;
    const date = new Date(`${value}T00:00:00Z`);
    return Number.isFinite(date.getTime()) && date.toISOString().slice(0, 10) === value;
}

/** @private */
export function validateFormValue(field: WorkflowFormField, value: unknown): FormError | null {
    if (empty(value)) return field.required ? 'required' : null;
    if (typeof value !== 'string') return field.type;
    if (field.dataSource) return field.options?.some((option) => option.value === value) ? null : 'select';
    if (field.type === 'text') return validateText(field, value);
    if (field.type === 'amount') return /^\d{1,12}(\.\d{1,2})?$/.test(value) ? null : 'amount';
    if (field.type === 'number') return /^-?\d{1,12}(\.\d{1,6})?$/.test(value) ? null : 'number';
    if (field.type === 'date') return validDate(value) ? null : 'date';
    return validateSelection(field, value);
}

/** @private */
export function serializeForm(fields: WorkflowFormField[], values: Record<string, string>): Record<string, string> {
    return Object.fromEntries(fields.map((field) => [field.key, values[field.key] ?? '']));
}

/** @private */
export function formatFormValue(field: WorkflowFormField, value: unknown, locale: string): string | null {
    if (empty(value)) return '—';
    if (field.type === 'select') return selectLabel(field, value);
    if (field.type === 'date' && typeof value === 'string' && validDate(value)) {
        return new Intl.DateTimeFormat(locale, { timeZone: 'UTC' }).format(new Date(`${value}T00:00:00Z`));
    }
    // Keep the fraction as a string: converting a 12-digit amount to Number can round its cents.
    if (field.type === 'amount' && typeof value === 'string' && validateFormValue(field, value) === null) {
        const [integer, fraction = ''] = value.split('.');
        const separator =
            new Intl.NumberFormat(locale).formatToParts(1.1).find((part) => part.type === 'decimal')?.value ?? '.';
        return `${new Intl.NumberFormat(locale).format(Number(integer))}${separator}${fraction.padEnd(2, '0')}`;
    }
    return null;
}

function selectLabel(field: WorkflowFormField, value: unknown): string | null {
    return field.options?.find((option) => option.value === value)?.label ?? (typeof value === 'string' ? value : null);
}

function validateText(field: WorkflowFormField, value: string): FormError | null {
    if (field.required && !value.trim()) return 'required';
    return value.length > 4000 ? 'text' : null;
}

function validateSelection(field: WorkflowFormField, value: string): FormError | null {
    return field.options?.some((option) => option.value === value) ? null : 'select';
}
