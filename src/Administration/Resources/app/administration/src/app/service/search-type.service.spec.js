/**
 * @sw-package inventory
 */
import createSearchTypeService from 'src/app/service/search-type.service';

describe('src/app/service/search-type.service.js', () => {
    it('should provide the product criteria with the variant options', () => {
        const type = createSearchTypeService().getTypeByName('product');

        const criteria = type.criteria(11);

        expect(criteria.getLimit()).toBe(11);
        expect(criteria.getAssociation('options').getAssociation('group')).toBeDefined();
    });

    it.each([
        ['loyalty_tier', 'sw.settings.membership.index'],
        ['membership_plan', 'sw.settings.membership.plans'],
    ])('registers %s as a settings search type', (entityName, listingRoute) => {
        expect(createSearchTypeService().getTypeByName(entityName)).toMatchObject({
            entityName,
            listingRoute,
            hideOnGlobalSearchBar: true,
        });
    });

    it('should merge the configuration of an upserted type', () => {
        const service = createSearchTypeService();
        const itemRoute = (item) => ({ name: 'custom.detail', params: { id: item.id } });

        service.upsertType('product', { itemRoute });

        expect(service.getTypeByName('product').itemRoute).toBe(itemRoute);
        expect(service.getTypeByName('product').entityName).toBe('product');
    });
});
