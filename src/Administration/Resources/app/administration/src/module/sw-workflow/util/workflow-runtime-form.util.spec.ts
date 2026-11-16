/** @sw-package framework */
import type { WorkflowFormField } from 'src/core/workflow/workflow-runtime.types';
import { formatFormValue, serializeForm, validateFormValue } from './workflow-runtime-form.util';

const field: WorkflowFormField = {
    key: 'value',
    label: 'Value',
    type: 'text',
    required: false,
};

describe('published request values', () => {
    it.each([
        [
            'text',
            ' ',
            true,
            'required',
        ],
        [
            'text',
            'x'.repeat(4001),
            false,
            'text',
        ],
        [
            'amount',
            '999999999999.99',
            true,
            null,
        ],
        [
            'amount',
            '-1',
            false,
            'amount',
        ],
        [
            'amount',
            '1.234',
            false,
            'amount',
        ],
        [
            'amount',
            '1000000000000',
            false,
            'amount',
        ],
        [
            'amount',
            '1e3',
            false,
            'amount',
        ],
        [
            'number',
            '-999999999999.123456',
            true,
            null,
        ],
        [
            'number',
            '0.1234567',
            false,
            'number',
        ],
        [
            'number',
            'Infinity',
            false,
            'number',
        ],
        [
            'date',
            '2024-02-29',
            true,
            null,
        ],
        [
            'date',
            '2025-02-29',
            false,
            'date',
        ],
        [
            'date',
            '2025-04-31',
            false,
            'date',
        ],
        [
            'date',
            '0000-01-01',
            false,
            'date',
        ],
        [
            'select',
            'travel',
            true,
            null,
        ],
        [
            'select',
            'Travel',
            false,
            'select',
        ],
        [
            'amount',
            '',
            false,
            null,
        ],
        [
            'date',
            '',
            true,
            'required',
        ],
    ])('validates %s input %s', (type, value, required, error) => {
        expect(
            validateFormValue(
                {
                    ...field,
                    type,
                    required,
                    options: [{ value: 'travel', label: 'Travel' }],
                } as WorkflowFormField,
                value,
            ),
        ).toBe(error);
    });

    it('serializes exact strings in published schema order without unknown fields', () => {
        expect(
            serializeForm([{ ...field, key: 'amount', type: 'amount' }, field], {
                amount: '999999999999.99',
                other: 'draft',
            }),
        ).toEqual({ amount: '999999999999.99', value: '' });
    });

    it('displays frozen select labels, human dates and exact cents', () => {
        expect(
            formatFormValue(
                {
                    ...field,
                    type: 'select',
                    options: [{ value: 'travel', label: 'Travel costs' }],
                },
                'travel',
                'en-US',
            ),
        ).toBe('Travel costs');
        expect(formatFormValue({ ...field, type: 'date' }, '2026-11-13', 'en-US')).toBe('11/13/2026');
        expect(formatFormValue({ ...field, type: 'amount' }, '999999999999.99', 'en-US')).toBe('999,999,999,999.99');
        expect(formatFormValue({ ...field, type: 'amount' }, '42', 'en-US')).toBe('42.00');
    });
});

describe('Unicode text boundaries', () => {
    it.each(['😀'.repeat(4000), '申请a😀'.repeat(1000)])('accepts 4000 Unicode code points', (value) => {
        expect(validateFormValue(field, value)).toBeNull();
    });
    it('rejects 4001 Unicode code points', () => {
        expect(validateFormValue(field, '😀'.repeat(4001))).toBe('text');
    });
});
