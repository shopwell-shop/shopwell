/**
 * @sw-package framework
 */
import useOrganizationTree from './use-organization-tree';

describe('organization navigation loading', () => {
    function createTree() {
        const factory = Shopwell.Service('repositoryFactory');
        const organizations = factory.create('organization');
        const units = factory.create('organization_unit');
        const organizationSearch = jest.spyOn(organizations, 'search');
        const unitSearch = jest.spyOn(units, 'search');
        return { tree: useOrganizationTree(organizations, units), organizationSearch, unitSearch };
    }

    it('loads all pages and keeps institution unit counts', async () => {
        const { tree, organizationSearch } = createTree();
        organizationSearch
            .mockResolvedValueOnce(
                Object.assign(
                    Array.from({ length: 100 }, (_, i) => ({ id: `org-${i}` })),
                    {
                        total: 101,
                        aggregations: { unitCounts: { buckets: [{ key: 'org-0', unitCount: { count: 4 } }] } },
                    },
                ) as never,
            )
            .mockResolvedValueOnce(Object.assign([{ id: 'org-last' }], { total: 101 }) as never);
        await tree.loadNavigation();
        expect(tree.organizations.value).toHaveLength(101);
        expect(tree.unitCounts.value['org-0']).toBe(4);
        expect(organizationSearch.mock.calls.map(([criteria]) => criteria.page)).toEqual([1, 2]);
        expect(tree.isLoadingNavigation.value).toBe(false);
    });

    it('deduplicates concurrent expansion requests and retries failures', async () => {
        const { tree, unitSearch } = createTree();
        unitSearch.mockRejectedValueOnce(new Error('Offline'));
        await expect(tree.loadOrganizationUnits('org-a')).rejects.toThrow('Offline');
        expect(tree.loadedOrganizationIds.value).toEqual([]);
        unitSearch.mockResolvedValueOnce([{ id: 'unit', organizationId: 'org-a' }] as never);
        await Promise.all([tree.loadOrganizationUnits('org-a'), tree.loadOrganizationUnits('org-a')]);
        await tree.loadOrganizationUnits('org-a');
        expect(unitSearch).toHaveBeenCalledTimes(2);
        expect(tree.units.value).toEqual([{ id: 'unit', organizationId: 'org-a' }]);
    });

    it('searches unloaded institutions and fetches the matching unit hierarchy', async () => {
        const { tree, unitSearch } = createTree();
        unitSearch.mockResolvedValueOnce([{ id: 'leaf', organizationId: 'org-b' }] as never).mockResolvedValueOnce([
            { id: 'root', organizationId: 'org-b' },
            { id: 'leaf', organizationId: 'org-b', parentId: 'root' },
        ] as never);
        await tree.searchTree(' Engineering ');
        expect(unitSearch.mock.calls[0][0].term).toBe('Engineering');
        expect(unitSearch.mock.calls[1][0].filters).toContainEqual({
            type: 'equals',
            field: 'organizationId',
            value: 'org-b',
        });
        expect(tree.units.value.map((unit) => unit.id)).toEqual(['root', 'leaf']);
    });
});
