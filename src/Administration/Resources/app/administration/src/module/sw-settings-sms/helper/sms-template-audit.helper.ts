/**
 * @sw-package framework
 */
import type { SmsGatewayAudit, SmsGatewayProvider } from '../service/sms-gateway-provider.service';

/**
 * The namespace the server keys the labels of the audit states to, so the screens ask for the label
 * of a state instead of keeping a list of states of their own.
 *
 * @private
 */
const APPROVAL_STATUS_SNIPPET_PREFIX = 'sms.approval.status.';

/**
 * The custom field a row keeps the time of the last sync in.
 *
 * @private
 */
export const CUSTOM_FIELD_LAST_SYNCED_AT = 'sms_last_synced_at';

/**
 * The custom field a row keeps the raw code of the provider in.
 *
 * @private
 */
export const CUSTOM_FIELD_STATUS_CODE = 'sms_provider_status_code';

/**
 * The custom field a row keeps the message of the provider in, which is the reason an operator has
 * to read when a template was refused.
 *
 * @private
 */
export const CUSTOM_FIELD_STATUS_MESSAGE = 'sms_provider_status_message';

/**
 * What a provider that declares no audit block can do, so a payload without one leaves a screen
 * usable instead of offering a step nothing can take.
 *
 * @private
 */
export const NO_AUDIT: SmsGatewayAudit = {
    requiresApproval: false,
    canSubmit: false,
    canReadStatus: false,
    submissionFields: [],
};

/**
 * The snippet key of the label of an audit state.
 *
 * @private
 */
export function approvalStatusSnippet(status: string): string {
    return `${APPROVAL_STATUS_SNIPPET_PREFIX}${status}`;
}

/**
 * The label of an audit state: the text the shop has for it, else the state as the server named it,
 * and a dash where no state was answered at all.
 *
 * The two i18n functions of the caller are handed in instead of being reached for, so the decision
 * lives in one place - and in a place that can be tested without a translation registry.
 *
 * @private
 */
export function approvalStatusLabel(
    status: string | null | undefined,
    hasSnippet: (key: string) => boolean,
    translate: (key: string) => string,
): string {
    if (!status) {
        return '—';
    }

    const snippet = approvalStatusSnippet(status);

    return hasSnippet(snippet) ? translate(snippet) : status;
}

/**
 * The provider a row points to, or null for a provider the server did not describe.
 *
 * @private
 */
export function providerOf(
    providers: SmsGatewayProvider[],
    technicalName: string | null | undefined,
): SmsGatewayProvider | null {
    if (!technicalName) {
        return null;
    }

    return providers.find((provider) => provider.technicalName === technicalName) ?? null;
}

/**
 * What the provider of a row declares it can do about the audit of a template.
 *
 * The administration never derives this from the name of a provider: a provider an extension adds
 * brings its capabilities with it, and one the server did not describe offers nothing.
 *
 * @private
 */
export function auditOfProvider(
    providers: SmsGatewayProvider[],
    technicalName: string | null | undefined,
): SmsGatewayAudit {
    return providerOf(providers, technicalName)?.audit ?? NO_AUDIT;
}

/**
 * One answer of a provider out of the custom fields of a row.
 *
 * The answers are read only and kept as they came, so an empty one is no answer at all.
 *
 * @private
 */
export function customFieldValue(customFields: unknown, name: string): string | null {
    if (!customFields || typeof customFields !== 'object') {
        return null;
    }

    const value = (customFields as Record<string, unknown>)[name];

    return typeof value === 'string' && value !== '' ? value : null;
}
