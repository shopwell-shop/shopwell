/**
 * @sw-package fundamentals@framework
 */
import type { NavigationGuardNext } from 'vue-router';
import type { MenuTreeNode } from 'src/app/store/admin-menu-config.store';
import useNotification from 'src/app/composables/use-notification';
import type { MenuDraft, MenuPageState } from './menu-draft';
import template from './sw-permissions-menu-list.html.twig';
import './sw-permissions-menu-list.scss';
import { createMenuPresentation } from './menu-presentation';
import {
    buildPayload,
    changeDraftType,
    createDraft,
    createPageState,
    draftFromItem,
    fingerprintDraft,
    needsMove,
    validateDraft,
} from './menu-draft';
import { collectAncestorIds, collectBranchIds, collectDescendantIds, filterTreeByTerm, findNode } from './tree-filter';

const { Data } = Shopwell;
const { Criteria } = Data;

type RenderNode = MenuTreeNode & { label: string; groupLabel: string | null; children: RenderNode[] };
type Move = { id: string; parentId: string | null; afterMenuItemId: string | null };

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,

    inject: [
        'acl',
        'repositoryFactory',
    ],

    data(): MenuPageState {
        return createPageState(Shopwell.Context.api.languageId ?? Shopwell.Defaults.systemLanguageId);
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        beforeUnloadHandler() {
            return (event: BeforeUnloadEvent) => this.onBeforeUnload(event);
        },

        store() {
            return Shopwell.Store.get('adminMenuConfig');
        },

        items() {
            return this.store.items;
        },

        presentation() {
            return createMenuPresentation({
                items: this.items,
                tree: this.store.tree,
                routeAllowlist: this.routeAllowlist,
                draft: this.draft,
                languageId: this.languageId,
                translate: (key) => this.tWithFallback(key),
            });
        },

        tree(): RenderNode[] {
            return this.presentation.tree;
        },

        routeAllowlist() {
            return this.store.routeAllowlist;
        },

        isLoading() {
            return this.store.isLoading && !this.store.isLoaded;
        },

        isSearching() {
            return this.searchTerm.trim().length > 0;
        },

        filteredTree(): RenderNode[] {
            const term = this.searchTerm.trim();

            if (!term) {
                return this.tree;
            }

            return filterTreeByTerm(this.tree, term);
        },

        effectiveExpandedIds() {
            if (!this.isSearching) {
                return this.expandedIds;
            }

            return collectBranchIds(this.filteredTree);
        },

        hasTree() {
            return this.filteredTree.length > 0;
        },

        selectedItem(): RenderNode | null {
            return findNode(this.tree, this.selectedId);
        },

        languageLabel() {
            const language = this.languages.find((candidate) => candidate.id === this.languageId);

            return language?.name ?? Shopwell.Store.get('session').currentLocale;
        },

        canEdit() {
            return this.acl.can('admin_menu.editor');
        },

        canCreate() {
            return this.acl.can('admin_menu.creator');
        },

        canDelete() {
            return this.acl.can('admin_menu.deleter');
        },

        canSaveDraft() {
            return this.draft?.id ? this.canEdit : this.canCreate;
        },

        isAllExpanded() {
            return this.expandableIds.length > 0 && this.expandableIds.every((id) => this.expandedIds.includes(id));
        },

        expandableIds() {
            return collectBranchIds(this.tree);
        },

        typeOptions() {
            return this.presentation.typeOptions;
        },

        routeOptions() {
            return this.presentation.routeOptions;
        },

        parentOptions() {
            return this.presentation.parentOptions;
        },

        parentId() {
            return this.draft?.parentId ?? null;
        },

        isBusy() {
            return this.isSaving || this.isDeleting || this.isMoving;
        },

        canEditDraft() {
            return this.canSaveDraft && !this.isBusy;
        },

        targetPrivilege() {
            return this.routeAllowlist.find((entry) => entry.internalKey === this.draft?.routeKey)?.privilege ?? '';
        },

        provenance() {
            return (
                this.selectedItem?.pluginName ??
                (this.isLocked ? this.$t('sw-permissions.menu.sourceSystem') : this.$t('sw-permissions.menu.sourceCustom'))
            );
        },

        isLocked() {
            if (!this.draft?.id) {
                return false;
            }

            return this.store.itemById(this.draft.id)?.sysLocked === true;
        },

        deleteConfirmMessage() {
            const count = this.deleteTarget ? collectDescendantIds(this.tree, this.deleteTarget.id).length : 0;

            if (count === 0) {
                return this.$t('sw-permissions.menu.deleteConfirmMessage');
            }

            return this.$t('sw-permissions.menu.deleteConfirmMessageWithChildren', { count });
        },
    },

    created() {
        this.createdComponent();
    },

    mounted() {
        window.addEventListener('beforeunload', this.beforeUnloadHandler);
    },

    beforeUnmount() {
        window.removeEventListener('beforeunload', this.beforeUnloadHandler);
    },

    beforeRouteLeave(_to, _from, next: NavigationGuardNext) {
        if (this.isBusy) {
            next(false);
            return;
        }
        this.requestChange(
            () => next(),
            () => next(false),
        );
    },

    methods: {
        ...useNotification(),

        tWithFallback(key: string): string {
            if (this.$te(key)) return this.$t(key);
            const fallback = Shopwell.Context.app.fallbackLocale;
            return fallback && this.$te(key, fallback) ? this.$t(key, {}, { locale: fallback }) : key;
        },
        onBeforeUnload(event: BeforeUnloadEvent) {
            if (this.hasUnsavedChanges() || this.isBusy) {
                event.preventDefault();
                event.returnValue = '';
            }
        },

        requestChange(change: () => void, cancel: (() => void) | null = null) {
            if (this.isBusy) {
                return;
            }
            if (this.hasUnsavedChanges()) {
                this.pendingChange = change;
                this.pendingCancel = cancel;
                this.showUnsavedModal = true;
                return;
            }
            change();
        },

        onKeepEditing() {
            const cancel = this.pendingCancel;
            this.clearPendingChange();
            cancel?.();
        },

        clearPendingChange() {
            this.pendingChange = null;
            this.pendingCancel = null;
            this.showUnsavedModal = false;
        },

        onDiscardChanges() {
            const change = this.pendingChange;
            this.clearPendingChange();
            change?.();
        },

        async onSaveChanges() {
            if (await this.onSave()) {
                this.onDiscardChanges();
            }
        },
        createdComponent() {
            this.loadLanguages();
            this.loadTree();
        },

        loadTree() {
            this.store.load().catch(() => {
                this.createNotificationError({ message: this.$t('sw-permissions.menu.notification.loadError') });
            });
        },

        loadLanguages() {
            const repository = this.repositoryFactory.create('language');

            repository
                .search(new Criteria(1, 100), Shopwell.Context.api)
                .then((languages) => {
                    this.languages = languages;

                    if (!this.languages.some((language) => language.id === this.languageId)) {
                        this.languageId = Shopwell.Context.api.languageId ?? Shopwell.Defaults.systemLanguageId;
                    }
                })
                .catch(() => {
                    this.createNotificationError({ message: this.$t('sw-permissions.menu.notification.loadError') });
                });
        },

        abortOnLanguageChange() {
            return this.hasUnsavedChanges();
        },

        async saveOnLanguageChange() {
            if (this.hasUnsavedChanges() && !(await this.onSave())) {
                throw new Error('Menu changes were not saved');
            }
        },

        onLanguageChange(languageId: string) {
            if (this.isBusy) return;
            this.languageId = languageId;

            if (this.selectedId) {
                this.initDraft(findNode(this.tree, this.selectedId));
            }
        },

        onToggle(item: RenderNode) {
            this.expandedIds = this.expandedIds.includes(item.id)
                ? this.expandedIds.filter((id) => id !== item.id)
                : [...this.expandedIds, item.id];
        },

        onToggleAll() {
            this.expandedIds = this.isAllExpanded ? [] : [...this.expandableIds];
        },

        selectItem(item: RenderNode) {
            this.selectedId = item.id;

            const ancestors = collectAncestorIds(this.tree, item.id);

            this.expandedIds = [...new Set([...this.expandedIds, ...ancestors])];

            this.initDraft(findNode(this.tree, item.id));
        },

        onItemSelect(item: RenderNode) {
            if (item.id !== this.selectedId) {
                this.requestChange(() => this.selectItem(item));
            }
        },

        initDraft(item: RenderNode | null) {
            if (!item) {
                return;
            }

            this.draft = draftFromItem(item, this.languageId);
            this.draftBaseline = fingerprintDraft(this.draft);
            this.fieldErrors = {};
        },

        setDraft(draft: MenuDraft) {
            this.draft = draft;
            this.draftBaseline = fingerprintDraft(draft);
        },

        hasUnsavedChanges() {
            return this.draft !== null && fingerprintDraft(this.draft) !== this.draftBaseline;
        },

        startDraft(draft: MenuDraft) {
            this.requestChange(() => {
                this.selectedId = null;
                this.setDraft(draft);
                this.fieldErrors = {};
            });
        },

        onCreateNew() {
            if (!this.canCreate) {
                return;
            }

            this.startDraft(createDraft());
        },

        onCreateAbove(item: RenderNode) {
            if (!this.canCreate) {
                return;
            }

            this.startDraft(createDraft({ parentId: item.parentId, afterMenuItemId: item.afterMenuItemId }));
        },

        onCreateBelow(item: RenderNode) {
            if (!this.canCreate) {
                return;
            }

            this.startDraft(createDraft({ parentId: item.parentId, afterMenuItemId: item.id }));
        },

        onAddChild(item: RenderNode) {
            if (!this.canCreate) {
                return;
            }

            this.startDraft(createDraft({ parentId: item.id }));
        },

        onCancelEdit() {
            if (this.isBusy) return;
            if (this.selectedId) {
                this.initDraft(findNode(this.tree, this.selectedId));

                return;
            }

            this.draft = null;
            this.draftBaseline = null;
        },

        updateDraft(fields: Partial<MenuDraft>) {
            if (this.draft && this.canEditDraft) {
                Object.assign(this.draft, fields);
            }
        },

        onParentChange(value: string) {
            if (!this.draft) {
                return;
            }

            this.draft.parentId = value === '' ? null : value;
            this.draft.afterMenuItemId = null;
        },

        onTypeChange(value: string) {
            if (this.draft) this.draft = changeDraftType(this.draft, value);
        },

        validate() {
            this.fieldErrors = {};
            const key = validateDraft(this.draft);
            if (!key) return true;
            const message = this.$t(key);
            if (key.endsWith('titleRequired')) this.fieldErrors.title = { code: 'required', detail: message };
            this.createNotificationError({ message });
            return false;
        },

        async onSave(): Promise<boolean> {
            if (!this.draft || this.isBusy || !this.canSaveDraft) {
                return false;
            }

            if (!this.validate()) {
                return false;
            }

            const draft = createDraft({ ...this.draft });
            const languageId = this.languageId;
            const parentId = draft.parentId;
            const hasToMove = needsMove(draft, draft.id ? this.store.itemById(draft.id) : null, parentId);

            this.isSaving = true;

            try {
                const id = await this.store.saveItem(buildPayload(draft, languageId));
                this.draft.id = id;
                this.selectedId = id;

                if (hasToMove && id) {
                    await this.store.moveItem({
                        id,
                        parentId,
                        afterMenuItemId: draft.afterMenuItemId,
                    });

                    // the entry was placed somewhere the operator cannot see yet
                    if (parentId && !this.expandedIds.includes(parentId)) {
                        this.expandedIds = [...this.expandedIds, parentId];
                    }
                }

                if (!hasToMove) {
                    await this.store.refreshAfterWrite();
                }
                if (id) {
                    this.selectedId = id;
                    this.initDraft(findNode(this.tree, id));
                }

                this.createNotificationSuccess({ message: this.$t('sw-permissions.menu.notification.saveSuccess') });
                return true;
            } catch (error) {
                this.createNotificationError({
                    message: this.errorMessage(error, 'sw-permissions.menu.notification.saveError'),
                });
                return false;
            } finally {
                this.isSaving = false;
            }
        },

        errorMessage(error: unknown, fallbackKey: string) {
            const response = error as { response?: { data?: { errors?: { detail?: string }[] } } };
            return response?.response?.data?.errors?.[0]?.detail ?? this.$t(fallbackKey);
        },

        onMove(move: Move) {
            if (!this.canEdit || this.isBusy || this.isSearching) {
                return;
            }
            this.requestChange(() => {
                void this.moveItem(move);
            });
        },

        async moveItem(move: Move) {
            this.isMoving = true;
            try {
                await this.store.moveItem(move);

                if (move.parentId && !this.expandedIds.includes(move.parentId)) {
                    this.expandedIds = [...this.expandedIds, move.parentId];
                }

                if (this.selectedId) {
                    this.initDraft(findNode(this.tree, this.selectedId));
                }
            } catch (error) {
                this.createNotificationError({
                    message: this.errorMessage(error, 'sw-permissions.menu.notification.moveError'),
                });
            } finally {
                this.isMoving = false;
            }
        },

        onDelete(item: RenderNode) {
            if (
                !this.canDelete ||
                this.isBusy ||
                item.sysLocked ||
                collectDescendantIds(this.tree, item.id).some((id) => this.store.itemById(id)?.sysLocked)
            ) {
                return;
            }

            this.requestChange(() => {
                this.deleteTarget = item;
                this.showDeleteModal = true;
            });
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
            this.deleteTarget = null;
        },

        async onConfirmDelete() {
            if (!this.deleteTarget || this.isBusy || !this.canDelete) {
                return;
            }

            this.isDeleting = true;

            try {
                const deletedIds = [this.deleteTarget.id, ...collectDescendantIds(this.tree, this.deleteTarget.id)];
                await this.store.deleteItem(this.deleteTarget.id);

                this.expandedIds = this.expandedIds.filter((id) => !deletedIds.includes(id));

                if (this.selectedId && deletedIds.includes(this.selectedId)) {
                    this.selectedId = null;
                    this.draft = null;
                    this.draftBaseline = null;
                }

                this.showDeleteModal = false;
                this.deleteTarget = null;

                this.createNotificationSuccess({ message: this.$t('sw-permissions.menu.notification.deleteSuccess') });
            } catch (error) {
                this.createNotificationError({
                    message: this.errorMessage(error, 'sw-permissions.menu.notification.deleteError'),
                });
            } finally {
                this.isDeleting = false;
            }
        },
    },
});
