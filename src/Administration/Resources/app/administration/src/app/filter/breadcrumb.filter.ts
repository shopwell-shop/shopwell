/**
 * @sw-package framework
 */

Shopwell.Filter.register(
    'breadcrumb',
    (value: string[] | Record<string, string> | null | undefined, separator: string = ' / '): string => {
        if (!value) {
            return '';
        }

        return Object.values(value).join(separator);
    },
);

/* @private */
export {};
