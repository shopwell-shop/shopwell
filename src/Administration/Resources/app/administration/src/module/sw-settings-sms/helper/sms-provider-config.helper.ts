/**
 * @sw-package framework
 */
import type { SmsCredentialDefinition } from '../service/sms-gateway-provider.service';

type FormValues = Record<string, string | number | boolean>;

/**
 * @private
 */
export function readConfigValues(fields: SmsCredentialDefinition[], stored: Record<string, unknown>): FormValues {
    return Object.fromEntries(
        fields.map((field) => {
            const value = stored[field.name] ?? field.default;

            if (field.type === 'json') {
                return [field.name, value === undefined || value === null ? '' : JSON.stringify(value, null, 2)];
            }

            return [
                field.name,
                typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean'
                    ? value
                    : field.type === 'bool'
                      ? false
                      : '',
            ];
        }),
    );
}

/**
 * @private
 */
export function writeConfigValues(
    fields: SmsCredentialDefinition[],
    values: FormValues,
    stored: Record<string, unknown> = {},
): Record<string, unknown> {
    const collected = { ...stored };

    fields.forEach((field) => {
        const value = values[field.name] ?? field.default ?? null;

        if (field.type === 'json' && typeof value === 'string') {
            collected[field.name] = value.trim() === '' ? null : (JSON.parse(value) as unknown);
        } else {
            collected[field.name] = value;
        }
    });

    return collected;
}
