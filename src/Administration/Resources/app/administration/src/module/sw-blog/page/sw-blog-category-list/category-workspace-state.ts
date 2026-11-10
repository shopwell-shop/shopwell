/**
 * @sw-package discovery
 */

/** @private */
export function createCategoryWorkspaceState(categoryId: string | null, searchTerm: string) {
    return {
        selectedId: categoryId,
        categorySearch: searchTerm,
        category: null as EntitySchema.Entity<'blog_category'> | null,
        entries: null as EntityCollection<'blog'> | null,
        isLoading: false,
        isSaving: false,
        isDeleting: false,
        isTreeMutating: false,
        showDeleteModal: false,
        activeTab: 'general',
        categoryPath: '',
        pendingNavigation: null as (() => void) | null,
        pendingNavigationIsRoute: true,
        cancelNavigation: null as (() => void) | null,
        assignmentsChanged: false,
        navigationRevision: 0,
        entriesPage: 1,
        entriesTotal: 0,
        isEntriesLoading: false,
        isUpdatingAssignments: false,
        addedEntries: [] as EntitySchema.Entity<'blog'>[],
        removedAssignments: [] as EntitySchema.Entity<'blog_category_assignment'>[],
        entriesRequest: 0,
        categoryRequest: 0,
    };
}

/** @private */
export function hasCategoryDraftChanges(category: EntitySchema.Entity<'blog_category'>): boolean {
    return (
        !category.active ||
        [
            category.name,
            category.slug,
            category.description,
            category.metaTitle,
            category.metaDescription,
            category.keywords,
            category.contentLayoutId,
        ].some((value) => Boolean(value?.trim()))
    );
}
