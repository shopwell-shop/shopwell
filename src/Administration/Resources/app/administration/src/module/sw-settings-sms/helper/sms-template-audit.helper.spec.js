/**
 * @sw-package framework
 */
import {
    approvalStatusLabel,
    approvalStatusSnippet,
    auditOfProvider,
    CUSTOM_FIELD_LAST_SYNCED_AT,
    CUSTOM_FIELD_STATUS_CODE,
    CUSTOM_FIELD_STATUS_MESSAGE,
    customFieldValue,
    NO_AUDIT,
    providerOf,
} from './sms-template-audit.helper';

const providers = [
    {
        technicalName: 'described',
        labelSnippet: 'sms.gateway.described',
        supportedRegions: ['CN'],
        supportsTemplateMode: true,
        credentialDefinitions: [],
        audit: {
            requiresApproval: true,
            canSubmit: true,
            canReadStatus: false,
            submissionFields: [],
        },
    },
];

describe('src/module/sw-settings-sms/helper/sms-template-audit.helper', () => {
    it('should key the label of an audit state the way the server does', () => {
        expect(approvalStatusSnippet('approved')).toBe('sms.approval.status.approved');
        expect(approvalStatusSnippet('a-state-nobody-knows')).toBe('sms.approval.status.a-state-nobody-knows');
    });

    it('should show the translated label of a state the shop knows', () => {
        const label = approvalStatusLabel(
            'approved',
            () => true,
            (key) => `label of ${key}`,
        );

        expect(label).toBe('label of sms.approval.status.approved');
    });

    it('should show a state the shop has no label for as it came', () => {
        expect(
            approvalStatusLabel(
                'a-state-nobody-knows',
                () => false,
                (key) => key,
            ),
        ).toBe('a-state-nobody-knows');
    });

    it('should show a dash while no state was answered', () => {
        expect(
            approvalStatusLabel(
                null,
                () => true,
                (key) => key,
            ),
        ).toBe('—');
        expect(
            approvalStatusLabel(
                undefined,
                () => true,
                (key) => key,
            ),
        ).toBe('—');
    });

    it('should find the provider a row points to', () => {
        expect(providerOf(providers, 'described')).toEqual(providers[0]);
        expect(providerOf(providers, 'gone')).toBeNull();
        expect(providerOf(providers, null)).toBeNull();
        expect(providerOf(providers, undefined)).toBeNull();
    });

    it('should answer the audit capabilities the provider declares', () => {
        expect(auditOfProvider(providers, 'described')).toEqual(providers[0].audit);
    });

    it('should answer a provider the server did not describe with no capability at all', () => {
        expect(auditOfProvider(providers, 'gone')).toEqual(NO_AUDIT);
        expect(auditOfProvider([], undefined)).toBe(NO_AUDIT);
    });

    it('should read one answer of a provider out of the custom fields', () => {
        const customFields = {
            [CUSTOM_FIELD_STATUS_CODE]: '2',
            [CUSTOM_FIELD_STATUS_MESSAGE]: 'The body names a forbidden word.',
            [CUSTOM_FIELD_LAST_SYNCED_AT]: '2026-10-19 10:00:00.000',
        };

        expect(customFieldValue(customFields, CUSTOM_FIELD_STATUS_CODE)).toBe('2');
        expect(customFieldValue(customFields, CUSTOM_FIELD_STATUS_MESSAGE)).toBe('The body names a forbidden word.');
        expect(customFieldValue(customFields, CUSTOM_FIELD_LAST_SYNCED_AT)).toBe('2026-10-19 10:00:00.000');
    });

    it('should answer an empty or missing custom field with nothing', () => {
        expect(customFieldValue(undefined, CUSTOM_FIELD_STATUS_CODE)).toBeNull();
        expect(customFieldValue(null, CUSTOM_FIELD_STATUS_CODE)).toBeNull();
        expect(customFieldValue({}, CUSTOM_FIELD_STATUS_CODE)).toBeNull();
        expect(customFieldValue({ [CUSTOM_FIELD_STATUS_CODE]: '' }, CUSTOM_FIELD_STATUS_CODE)).toBeNull();
        expect(customFieldValue({ [CUSTOM_FIELD_STATUS_CODE]: 2 }, CUSTOM_FIELD_STATUS_CODE)).toBeNull();
    });
});
