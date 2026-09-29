/**
 * Locale-specific test data helpers
 */

export interface AddressData {
    street: string;
    city: string;
    postalCode: string;
    country: string;
}

/**
 * Get locale-specific address test data
 * @param locale - The locale code (e.g., 'zh-CN', 'en-GB')
 * @returns Address data for the given locale, defaults to the Simplified Chinese address if locale not found
 */
export function getAddressDataFromLocale(locale: string): AddressData {
    const localeAddresses: Record<string, AddressData> = {
        'zh-CN': { street: '朝阳路 88 号', city: '北京', postalCode: '100020', country: '中国' },
        'en-DE': { street: 'Musterstraße 123', city: 'Berlin', postalCode: '10115', country: 'Germany' },
        'en-GB': { street: '10 Downing Street', city: 'London', postalCode: 'SW1A 2AA', country: 'United Kingdom' },
        'en-US': {
            street: '1600 Pennsylvania Avenue NW',
            city: 'Washington',
            postalCode: '20500',
            country: 'United States',
        },
    };
    return localeAddresses[locale] || localeAddresses['zh-CN'];
}
