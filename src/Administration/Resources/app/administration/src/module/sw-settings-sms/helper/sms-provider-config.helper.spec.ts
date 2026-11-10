/**
 * @sw-package framework
 */
import { readConfigValues, writeConfigValues } from './sms-provider-config.helper';
import type { SmsCredentialDefinition } from '../service/sms-gateway-provider.service';

const fields: SmsCredentialDefinition[] = [
    { name: 'metadata', type: 'json', labelSnippet: 'metadata', required: false },
    { name: 'sandbox', type: 'bool', labelSnippet: 'sandbox', required: false, default: false },
    { name: 'timeout', type: 'int', labelSnippet: 'timeout', required: false, default: 10 },
];

describe('SMS provider configuration', () => {
    it('round trips structured values while preserving undeclared options', () => {
        const stored = { metadata: { sender: 'Shop' }, extensionOption: 'keep' };
        const values = readConfigValues(fields, stored);

        expect(values).toEqual({ metadata: '{\n  "sender": "Shop"\n}', sandbox: false, timeout: 10 });
        expect(writeConfigValues(fields, values, stored)).toEqual({
            metadata: { sender: 'Shop' },
            sandbox: false,
            timeout: 10,
            extensionOption: 'keep',
        });
    });

    it('rejects invalid JSON instead of replacing a saved value', () => {
        expect(() => writeConfigValues(fields, { metadata: '{broken' })).toThrow();
    });
});
