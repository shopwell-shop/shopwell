/** @sw-package framework */
import { computed, inject, onScopeDispose, ref, shallowRef, watch } from 'vue';
import type { Ref } from 'vue';

type RoleStatus = 'empty' | 'loading' | 'ready' | 'error' | 'forbidden';

/** Load role labels without changing the configured IDs. @private */
export default function useWorkflowRoles(config: Ref<Record<string, unknown>>) {
    const factory = inject<ServiceContainer['repositoryFactory']>('repositoryFactory');
    const acl = inject<ServiceContainer['acl']>('acl');
    if (!factory || !acl) {
        throw new Error('Workflow roles require repositoryFactory and acl.');
    }

    const repository = factory.create('acl_role');
    const canRead = computed(() => acl.can('acl_role:read'));
    const roleIds = computed<string[]>(() => {
        const ids = config.value.roleIds;
        return Array.isArray(ids)
            ? [...new Set(ids.filter((id): id is string => typeof id === 'string').map((id) => id.toLowerCase()))]
            : [];
    });
    const roles = shallowRef<Entity<'acl_role'>[]>([]);
    const status = ref<RoleStatus>('empty');
    let request = 0;

    async function load(): Promise<void> {
        const current = ++request;
        roles.value = [];
        if (!canRead.value || roleIds.value.length === 0) {
            status.value = canRead.value ? 'empty' : 'forbidden';
            return;
        }

        status.value = 'loading';
        const ids = roleIds.value.filter((id) => /^[a-f0-9]{32}$/.test(id));
        if (ids.length === 0) {
            status.value = 'ready';
            return;
        }

        try {
            const criteria = new Shopwell.Data.Criteria(1, 50);
            criteria.setIds(ids);
            const result = await repository.search(criteria, Shopwell.Context.api);
            if (current === request) {
                roles.value = Array.from(result);
                status.value = 'ready';
            }
        } catch {
            if (current === request) {
                status.value = 'error';
            }
        }
    }

    watch(
        [roleIds, canRead],
        () => {
            void load();
        },
        { immediate: true },
    );
    onScopeDispose(() => {
        request += 1;
    });
    return { repository, roleIds, roles, canRead, status, retry: load };
}
