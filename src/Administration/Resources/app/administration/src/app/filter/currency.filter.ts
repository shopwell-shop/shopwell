/**
 * @sw-package framework
 */
import type { CurrencyOptions } from 'src/core/service/utils/format.utils';

const { currency } = Shopwell.Utils.format;

/**
 * @private
 */
Shopwell.Filter.register(
    'currency',
    (value: string | boolean, format: string, decimalPlaces: number, additionalOptions: CurrencyOptions) => {
        if (
            (!value || value === true) &&
            (!Shopwell.Utils.types.isNumber(value) || Shopwell.Utils.types.isEqual(value, NaN))
        ) {
            return '-';
        }

        if (Shopwell.Utils.types.isEqual(parseInt(value, 10), NaN)) {
            return value;
        }

        return currency(parseFloat(value), format, decimalPlaces, additionalOptions);
    },
);
