/**
 * @sw-package framework
 */
import { ref } from 'vue';
import type Repository from 'src/core/data/repository.data';

const { Criteria } = Shopwell.Data;
type Organization = EntitySchema.Entity<'organization'>;
type Unit = EntitySchema.Entity<'organization_unit'>;

async function loadOrganizations(organizationRepository: Repository<'organization'>) {
    const rows: Organization[] = [];
    const counts: Record<string, number> = {};
    let page = 1;
    let hasMore = true;
    while (hasMore) {
        const criteria = new Criteria(page, 100);
        criteria.addSorting(Criteria.sort('name', 'ASC'));
        criteria.addSorting(Criteria.sort('id', 'ASC'));
        criteria.addAggregation(
            Criteria.terms('unitCounts', 'id', null, null, Criteria.count('unitCount', 'organization.units.id')),
        );
        const result = await organizationRepository.search(criteria);
        rows.push(...result);
        const aggregation = result.aggregations?.unitCounts as
            | { buckets: Array<{ key: string; unitCount: { count: number } }> }
            | undefined;
        aggregation?.buckets.forEach((bucket) => {
            counts[bucket.key] = bucket.unitCount.count;
        });
        hasMore = result.length === 100 && (result.total == null || rows.length < result.total);
        page += 1;
    }
    return { organizations: rows, unitCounts: counts };
}
async function loadUnits(
    unitRepository: Repository<'organization_unit'>,
    organizationId: string | null,
    term = '',
): Promise<Unit[]> {
    const units: Unit[] = [];
    let page = 1;
    let hasMore = true;
    while (hasMore) {
        const criteria = new Criteria(page, 100);
        if (organizationId) {
            criteria.addFilter(Criteria.equals('organizationId', organizationId));
        }
        if (term) {
            criteria.setTerm(term);
        }
        criteria.addSorting(Criteria.sort('name', 'ASC'));
        criteria.addSorting(Criteria.sort('id', 'ASC'));
        const result = await unitRepository.search(criteria);
        units.push(...result);
        hasMore = result.length === 100 && (result.total == null || units.length < result.total);
        page += 1;
    }
    return units;
}
/** @private */
export default function useOrganizationTree(
    organizationRepository: Repository<'organization'>,
    unitRepository: Repository<'organization_unit'>,
) {
    const organizations = ref<Organization[]>([]);
    const units = ref<Unit[]>([]);
    const unitCounts = ref<Record<string, number>>({});
    const loadedOrganizationIds = ref<string[]>([]);
    const isLoadingNavigation = ref(false);
    const unitLoads = new Map<string, Promise<void>>();
    async function loadNavigation(): Promise<void> {
        isLoadingNavigation.value = organizations.value.length === 0;
        try {
            const result = await loadOrganizations(organizationRepository);
            organizations.value = result.organizations;
            unitCounts.value = result.unitCounts;
        } finally {
            isLoadingNavigation.value = false;
        }
    }
    async function loadOrganizationUnits(id: string): Promise<void> {
        if (loadedOrganizationIds.value.includes(id)) {
            return;
        }
        const existing = unitLoads.get(id);
        if (existing) {
            return existing;
        }
        const load = loadUnits(unitRepository, id).then((rows) => {
            units.value = [...units.value.filter((unit) => unit.organizationId !== id), ...rows];
            loadedOrganizationIds.value.push(id);
        });
        unitLoads.set(id, load);
        try {
            await load;
        } finally {
            unitLoads.delete(id);
        }
    }
    async function searchTree(term: string): Promise<void> {
        if (!term.trim()) {
            return;
        }
        const matches = await loadUnits(unitRepository, null, term.trim());
        const organizationIds = new Set<string>(matches.map((unit) => unit.organizationId));
        organizations.value
            .filter((organization) =>
                (organization.name ?? organization.translated?.name ?? '')
                    .toLocaleLowerCase()
                    .includes(term.trim().toLocaleLowerCase()),
            )
            .forEach((organization) => organizationIds.add(organization.id));
        await Promise.all([...organizationIds].map((id) => loadOrganizationUnits(id)));
    }

    return {
        organizations,
        units,
        unitCounts,
        loadedOrganizationIds,
        isLoadingNavigation,
        loadNavigation,
        loadOrganizationUnits,
        searchTree,
    };
}
