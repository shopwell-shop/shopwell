/**
 * @sw-package framework
 */

import { fileSize, date, dateWithUserTimezone, toISODate, localeName } from 'src/core/service/utils/format.utils';

describe('src/core/service/utils/format.utils.js', () => {
    describe('filesize', () => {
        it('should convert bytes to a readable format', async () => {
            expect(fileSize(0)).toBe('0.00B');
            expect(fileSize(1018)).toBe('0.99KB');
            expect(fileSize(1023)).toBe('1.00KB');
            expect(fileSize(1024)).toBe('1.00KB');
            expect(fileSize(102400000)).toBe('97.66MB');
        });
    });

    describe('date', () => {
        const setLocale = (locale) => {
            jest.spyOn(Shopwell.Application.getContainer('factory').locale, 'getLastKnownLocale').mockImplementation(
                () => locale,
            );
        };
        const setTimeZone = (timeZone) => Shopwell.Store.get('session').setCurrentUser({ timeZone });

        beforeEach(async () => {
            setLocale('en-GB');
            setTimeZone('UTC');
        });

        it('should return empty string for null value', async () => {
            expect(date(null)).toBe('');
        });

        it('should convert the date correctly with timezone UTC in en-GB', async () => {
            setLocale('en-GB');
            setTimeZone('UTC');

            expect(date('2000-06-18T08:30:00.000+00:00')).toBe('18 June 2000 at 08:30');
        });

        it('should convert the date correctly with timezone UTC in en-US', async () => {
            setLocale('en-US');
            setTimeZone('UTC');

            expect(date('2000-06-18T08:30:00.000+00:00')).toBe('June 18, 2000 at 8:30 AM');
        });

        it('should convert the date correctly with timezone UTC in zh-CN', async () => {
            setLocale('zh-CN');
            setTimeZone('UTC');

            expect(date('2000-06-18T08:30:00.000+00:00')).toBe('2000年6月18日 08:30');
        });

        it('should convert the date correctly with timezone America/New_York in en-GB', async () => {
            setLocale('en-GB');
            setTimeZone('America/New_York');

            expect(date('2000-06-18T08:30:00.000+00:00')).toBe('18 June 2000 at 04:30');
        });

        it('should convert the date correctly with timezone America/New_York in en-US', async () => {
            setLocale('en-US');
            setTimeZone('America/New_York');

            expect(date('2000-06-18T08:30:00.000+00:00')).toBe('June 18, 2000 at 4:30 AM');
        });

        it('should convert the date correctly with timezone America/New_York in zh-CN', async () => {
            setLocale('zh-CN');
            setTimeZone('America/New_York');

            expect(date('2000-06-18T08:30:00.000+00:00')).toBe('2000年6月18日 04:30');
        });

        it('should not convert the date correctly with timezone America/New_York in zh-CN', async () => {
            setLocale('zh-CN');
            setTimeZone('America/New_York');

            expect(
                date('2000-06-18T08:30:00.000+00:00', {
                    skipTimezoneConversion: true,
                }),
            ).toBe('2000年6月18日 08:30');
        });
    });

    describe('dateWithUserTimezone', () => {
        const setLocale = (locale) => {
            jest.spyOn(Shopwell.Application.getContainer('factory').locale, 'getLastKnownLocale').mockImplementation(
                () => locale,
            );
        };
        const setTimeZone = (timeZone) => Shopwell.Store.get('session').setCurrentUser({ timeZone });

        beforeEach(async () => {
            setLocale('en-GB');
            setTimeZone('UTC');
        });

        it('should convert the date correctly with timezone Pacific/Pago_Pago', async () => {
            setTimeZone('Pacific/Samoa');
            const date = new Date(2000, 1, 1, 11, 13, 37);

            expect(dateWithUserTimezone(date).toString()).toBe(
                'Tue Feb 01 2000 00:13:37 GMT+0000 (Coordinated Universal Time)',
            );
        });

        it('should convert the date correctly with timezone UTC as fallback', async () => {
            setTimeZone(null);
            const date = new Date(2000, 1, 1, 0, 13, 37);

            expect(dateWithUserTimezone(date).toString()).toBe(
                'Tue Feb 01 2000 00:13:37 GMT+0000 (Coordinated Universal Time)',
            );
        });
    });

    describe('currency', () => {
        const currencyFilter = Shopwell.Utils.format.currency;

        const precision = 0;

        it('should handle integers', async () => {
            expect(currencyFilter(42, 'EUR', precision)).toBe('€42');
        });

        it('should handle big int', async () => {
            expect(currencyFilter(42n, 'EUR', precision)).toBe('€42');
        });

        it('should handle floats', async () => {
            expect(currencyFilter(42.2, 'EUR', 2)).toBe('€42.20');
        });

        it('should use the provided language', async () => {
            expect(currencyFilter(42, 'EUR', 0, { language: 'en-US' })).toBe('€42');
        });

        it('should use a different fallback language', async () => {
            Shopwell.Store.get('session').setAdminLocaleState({
                locales: ['zh-CN'],
                locale: 'zh-CN',
                languageId: '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
            });

            expect(currencyFilter(42, 'EUR', 0)).toBe('€42');

            Shopwell.Store.get('session').setAdminLocaleState({
                locales: ['en-GB'],
                locale: 'en-GB',
                languageId: '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
            });
        });

        it('should fallback to the system currency', async () => {
            Shopwell.Context.app.systemCurrencyISOCode = 'EUR';

            expect(currencyFilter(42, undefined, 0)).toBe('€42');
        });

        it('should fallback to a different system currency', async () => {
            Shopwell.Context.app.systemCurrencyISOCode = 'USD';

            expect(currencyFilter(42, undefined, 0)).toBe('US$42');

            Shopwell.Context.app.systemCurrencyISOCode = 'EUR';
        });

        it('should fallback to decimal when the currency ISO code is invalid', async () => {
            Shopwell.Context.app.systemCurrencyISOCode = 'INVALID_EXAMPLE_CURRENCY_CODE';

            jest.spyOn(console, 'error').mockImplementationOnce(() => {});

            expect(currencyFilter(42.31415, undefined, 2)).toBe('42.31');

            expect(console.error).toHaveBeenCalledWith(
                new RangeError('Invalid currency code : INVALID_EXAMPLE_CURRENCY_CODE'),
            );

            Shopwell.Context.app.systemCurrencyISOCode = 'EUR';
        });
    });

    describe('toISODate', () => {
        it('formats the date with time', async () => {
            const dateWithTime = new Date(Date.UTC(2021, 0, 1, 13, 37, 0));

            expect(toISODate(dateWithTime)).toBe('2021-01-01T13:37:00.000Z');
        });

        it('formats the date without time', async () => {
            const dateWithoutTime = new Date(Date.UTC(2021, 0, 1, 13, 37, 0));

            expect(toISODate(dateWithoutTime, false)).toBe('2021-01-01');
        });
    });

    describe('localeName', () => {
        const setUiLocale = (locale) =>
            Shopwell.Store.get('session').setAdminLocaleState({
                locales: [locale],
                locale,
                languageId: '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
            });

        afterEach(() => {
            setUiLocale('en-GB');
        });

        it('renders the native name with language and region in the UI language', async () => {
            setUiLocale('zh-CN');

            expect(localeName('fr-FR')).toBe('Français (法语, 法国)');
            expect(localeName('en-GB')).toBe('English (英语, 英国)');
        });

        it('follows the UI language of the session', async () => {
            setUiLocale('en-GB');

            expect(localeName('zh-CN')).toBe('中文 (Chinese, China)');
        });

        it('prefers an explicitly given UI locale', async () => {
            setUiLocale('zh-CN');

            expect(localeName('zh-CN', 'fr-FR')).toBe('中文 (chinois, Chine)');
        });

        it('omits the region part for codes without a region', async () => {
            expect(localeName('zh', 'en-GB')).toBe('中文 (Chinese)');
        });

        it('falls back to the raw code when it is not a valid locale', async () => {
            expect(localeName('not a locale', 'en-GB')).toBe('not a locale');
        });
    });
});
