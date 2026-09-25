/**
 * @sw-package framework
 */

/**
 * @private
 */
Shopwell.Filter.register('fileSize', (value: number, locale: string) => {
    if (!value) {
        return '';
    }

    return Shopwell.Utils.format.fileSize(value, locale);
});

/* @private */
export {};
