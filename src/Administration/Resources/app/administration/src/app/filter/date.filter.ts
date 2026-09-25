/**
 * @sw-package framework
 */

Shopwell.Filter.register('date', (value: string, options: Intl.DateTimeFormatOptions = {}): string => {
    if (!value) {
        return '';
    }

    return Shopwell.Utils.format.date(value, options);
});

/**
 * @private
 */
export default {};
