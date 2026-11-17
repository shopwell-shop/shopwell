/** @sw-package framework */
import { computed, inject, onScopeDispose, ref, shallowRef, watch } from 'vue';
import type { Ref } from 'vue';

type PositionStatus = 'empty' | 'loading' | 'ready' | 'error' | 'forbidden';

/** Load position labels without changing the configured IDs. @private */
export default function useWorkflowPositions(config: Ref<Record<string, unknown>>) {
    const factory = inject<ServiceContainer['repositoryFactory']>('repositoryFactory');
    const acl = inject<ServiceContainer['acl']>('acl');
    if (!factory || !acl) {
        throw new Error('Workflow positions require repositoryFactory and acl.');
    }

    const repository = factory.create('position');
    const canRead = computed(() => acl.can('position:read'));
    const positionIds = computed<string[]>(() => {
        const ids = config.value.positionIds;
        return Array.isArray(ids)
            ? [...new Set(ids.filter((id): id is string => typeof id === 'string').map((id) => id.toLowerCase()))]
            : [];
    });
    const positions = shallowRef<Entity<'position'>[]>([]);
    const status = ref<PositionStatus>('empty');
    let request = 0;

    async function load(): Promise<void> {
        const current = ++request;
        positions.value = [];
        if (!canRead.value || positionIds.value.length === 0) {
            status.value = canRead.value ? 'empty' : 'forbidden';
            return;
        }

        status.value = 'loading';
        const ids = positionIds.value.filter((id) => /^[a-f0-9]{32}$/.test(id));
        if (ids.length === 0) {
            status.value = 'ready';
            return;
        }

        try {
            const criteria = new Shopwell.Data.Criteria(1, 50);
            criteria.setIds(ids);
            const result = await repository.search(criteria, Shopwell.Context.api);
            if (current === request) {
                positions.value = Array.from(result);
                status.value = 'ready';
            }
        } catch {
            if (current === request) {
                status.value = 'error';
            }
        }
    }

    watch(
        [positionIds, canRead],
        () => {
            void load();
        },
        { immediate: true },
    );
    onScopeDispose(() => {
        request += 1;
    });
    return { repository, positionIds, positions, canRead, status, retry: load };
}
