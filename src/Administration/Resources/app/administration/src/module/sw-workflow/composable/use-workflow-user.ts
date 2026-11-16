/** @sw-package framework */
import { computed, inject, onScopeDispose, shallowRef, ref, watch } from 'vue';
import type { Ref } from 'vue';

type UserStatus = 'empty' | 'loading' | 'ready' | 'inactive' | 'missing' | 'error' | 'forbidden';
type UserEntry = {
    user: Ref<Entity<'user'> | null>;
    status: Ref<UserStatus>;
    consumers: number;
    request: number;
    retry: () => Promise<void>;
};

// Share reads only while the account is displayed. Closing its last consumer discards the state.
const entriesByFactory = new WeakMap<ServiceContainer['repositoryFactory'], Map<string, UserEntry>>();

function createEntry(id: string, factory: ServiceContainer['repositoryFactory']): UserEntry {
    const repository = factory.create('user');
    const entry: UserEntry = {
        user: shallowRef<Entity<'user'> | null>(null),
        status: ref<UserStatus>('loading'),
        consumers: 0,
        request: 0,
        async retry() {
            const current = ++entry.request;
            entry.user.value = null;
            entry.status.value = 'loading';
            try {
                const user = await repository.get(id as Entity<'user'>['id']);

                if (current !== entry.request) {
                    return;
                }

                entry.user.value = user;

                if (user) {
                    entry.status.value = user.active ? 'ready' : 'inactive';
                } else {
                    entry.status.value = 'missing';
                }
            } catch {
                if (current === entry.request) {
                    entry.status.value = 'error';
                }
            }
        },
    };
    return entry;
}

/** Resolves current account state without modifying the stored assignment. @private */
export default function useWorkflowUser(userId: Ref<string | null>) {
    const factory = inject<ServiceContainer['repositoryFactory']>('repositoryFactory');
    const acl = inject<ServiceContainer['acl']>('acl');

    if (!factory || !acl) {
        throw new Error('Workflow user resolution requires repositoryFactory and acl.');
    }

    let entries = entriesByFactory.get(factory);

    if (!entries) {
        entries = new Map();
        entriesByFactory.set(factory, entries);
    }

    const accounts = entries;
    const canRead = computed(() => acl.can('user:read'));
    const current = shallowRef<UserEntry | null>(null);
    const idleStatus = ref<UserStatus>('empty');
    let currentId: string | null = null;
    function release(): void {
        const entry = current.value;

        if (entry && --entry.consumers === 0 && currentId) {
            entry.request += 1;
            accounts.delete(currentId);
        }

        current.value = null;
        currentId = null;
    }
    watch(
        [userId, canRead],
        ([id, allowed]) => {
            release();

            if (!id) {
                idleStatus.value = 'empty';
                return;
            }

            if (!allowed) {
                idleStatus.value = 'forbidden';
                return;
            }

            if (!/^[a-f0-9]{32}$/i.test(id)) {
                idleStatus.value = 'missing';
                return;
            }

            let entry = accounts.get(id);
            const isNew = !entry;

            if (!entry) {
                entry = createEntry(id, factory);
                accounts.set(id, entry);
            }

            entry.consumers += 1;
            currentId = id;
            current.value = entry;

            if (isNew) {
                void entry.retry();
            }
        },
        { immediate: true },
    );
    onScopeDispose(release);
    const user = computed(() => current.value?.user.value ?? null);
    const status = computed(() => current.value?.status.value ?? idleStatus.value);
    const name = computed(() => user.value?.name || user.value?.username || '');
    async function retry(): Promise<void> {
        if (canRead.value) {
            await current.value?.retry();
        }
    }
    return { user, status, name, canRead, retry };
}
