/** @sw-package framework */
import { computed, inject, onScopeDispose, ref, shallowRef, watch } from 'vue';
import type { Ref } from 'vue';

type UsersStatus = 'empty' | 'loading' | 'ready' | 'error' | 'forbidden';
type UsersEntry = {
    users: Ref<Entity<'user'>[]>;
    status: Ref<UsersStatus>;
    consumers: number;
    request: number;
    retry: () => Promise<void>;
};

// Keep shared results only while at least one node or settings panel displays them.
const entriesByFactory = new WeakMap<ServiceContainer['repositoryFactory'], Map<string, UsersEntry>>();

function createEntry(ids: string[], factory: ServiceContainer['repositoryFactory']): UsersEntry {
    const repository = factory.create('user');
    const entry: UsersEntry = {
        users: shallowRef<Entity<'user'>[]>([]),
        status: ref<UsersStatus>('loading'),
        consumers: 0,
        request: 0,
        async retry() {
            const request = ++entry.request;
            entry.users.value = [];
            entry.status.value = 'loading';
            const users: Entity<'user'>[] = [];
            try {
                for (let offset = 0; offset < ids.length; offset += 100) {
                    if (request !== entry.request) {
                        return;
                    }

                    const criteria = new Shopwell.Data.Criteria(1, 100);
                    criteria.setIds(ids.slice(offset, offset + 100));
                    criteria.setTotalCountMode(0);
                    const result = await repository.search(criteria, Shopwell.Context.api);

                    if (request !== entry.request) {
                        return;
                    }

                    users.push(...result);
                }
                entry.users.value = users;
                entry.status.value = 'ready';
            } catch {
                if (request === entry.request) {
                    entry.status.value = 'error';
                }
            }
        },
    };
    return entry;
}

/** Resolve the configured accounts without rewriting the stored assignment. @private */
export default function useWorkflowUsers(userIds: Ref<string[]>) {
    const factory = inject<ServiceContainer['repositoryFactory']>('repositoryFactory');
    const acl = inject<ServiceContainer['acl']>('acl');

    if (!factory || !acl) {
        throw new Error('Workflow users resolution requires repositoryFactory and acl.');
    }

    let entries = entriesByFactory.get(factory);

    if (!entries) {
        entries = new Map();
        entriesByFactory.set(factory, entries);
    }

    const accounts = entries;
    const canRead = computed(() => acl.can('user:read'));
    const requestedIds = computed(() => [...new Set(userIds.value.map((id) => id.toLowerCase()))]);
    const key = computed(() =>
        requestedIds.value
            .filter((id) => /^[a-f0-9]{32}$/i.test(id))
            .sort()
            .join(','),
    );
    const current = shallowRef<UsersEntry | null>(null);
    let currentKey: string | null = null;

    function release(): void {
        const entry = current.value;

        if (entry && --entry.consumers === 0 && currentKey) {
            entry.request += 1;
            accounts.delete(currentKey);
        }

        current.value = null;
        currentKey = null;
    }

    watch(
        [key, canRead],
        ([idsKey, allowed]) => {
            release();

            if (!idsKey || !allowed) {
                return;
            }

            let entry = accounts.get(idsKey);
            const isNew = !entry;

            if (!entry) {
                entry = createEntry(idsKey.split(','), factory);
                accounts.set(idsKey, entry);
            }

            entry.consumers += 1;
            currentKey = idsKey;
            current.value = entry;

            if (isNew) {
                void entry.retry();
            }
        },
        { immediate: true },
    );
    onScopeDispose(release);

    const status = computed<UsersStatus>(() => {
        if (!requestedIds.value.length) {
            return 'empty';
        }

        if (!canRead.value) {
            return 'forbidden';
        }

        return current.value?.status.value ?? 'ready';
    });
    const users = computed<Entity<'user'>[]>(() => {
        if (!canRead.value) {
            return [];
        }

        const found = new Map(current.value?.users.value.map((user) => [user.id.toLowerCase(), user]));
        return requestedIds.value.flatMap((id) => {
            const user = found.get(id);
            return user ? [user] : [];
        });
    });
    async function retry(): Promise<void> {
        if (canRead.value) {
            await current.value?.retry();
        }
    }
    return { users, status, canRead, retry };
}
