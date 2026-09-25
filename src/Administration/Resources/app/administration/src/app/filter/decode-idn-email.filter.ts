/**
 * @sw-package framework
 */
import { toUnicode } from 'punycode/';

/**
 * @private
 */
Shopwell.Filter.register('decode-idn-email', (value: string) => {
    return toUnicode(value);
});
