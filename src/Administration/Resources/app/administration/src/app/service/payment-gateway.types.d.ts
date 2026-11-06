/**
 * @sw-package framework
 */
/**
 * A payment interface as the settings page renders it: the declaration of its credentials and of its
 * channels, and whether the shop has configured it.
 *
 * @private
 */
export interface PaymentProviderView extends Record<string, unknown> {
    technicalName: string;
    label?: string;
    products?: PaymentProductDefinition[];
}

/** @private */
export interface PaymentProductDefinition {
    product: string;
    label: string;
    method?: string;
    methodTranslationKey?: string;
    interactions?: string[];
}

/** @private */
export interface PaymentAppView {
    id: string | null;
    technicalName: string;
    name: string;
    type: string;
    active: boolean;
    notifyUrl: string | null;
    returnUrl: string | null;
    allowedIps: string[];
    rateLimit: number | null;
    hasSecret: boolean;
    accounts: Array<{ accountId: string; enabled: boolean; position: number }>;
    channels: Array<{ channelTechnicalName: string; enabled: boolean; settings: Record<string, unknown> }>;
}

/** @private */
export interface PaymentAccountView {
    id: string;
    name: string;
    providerTechnicalName: string;
    active: boolean;
    ownerAppId: string | null;
    isDefault: boolean;
    enabledProducts: string[];
    credentials: Record<string, string | number | boolean | null>;
    productParams: Record<string, Record<string, string | number | boolean | null>>;
    settings: Record<string, unknown>;
}

/** @private */
export type PaymentAccountChoice = Pick<
    PaymentAccountView,
    'id' | 'name' | 'providerTechnicalName' | 'active' | 'ownerAppId' | 'isDefault'
>;
