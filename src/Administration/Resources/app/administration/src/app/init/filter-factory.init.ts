import type FilterFactoryData from 'src/core/data/filter-factory.data';

/**
 * @sw-package framework
 */

const FilterFactory = Shopwell.Classes._private.FilterFactory;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function initializeFilterFactory() {
    const filterFactory = new FilterFactory();

    Shopwell.Application.addServiceProvider('filterFactory', () => {
        return filterFactory as unknown as FilterFactoryData;
    });
}
