/**
 * @sw-package discovery
 *
 * The read shapes of the blog module.
 *
 * No controller of its own is needed: the five entities are exposed by the generic admin api, so a
 * page reads and writes through the repository. What a page does *not* do is build a criteria
 * itself - "the entries, newest publication first", "the approved comments of this entry" and "the
 * cms pages a blog may use" are asked in more than one place, and a page that answers them on its
 * own drifts away from the others.
 *
 * The one rule that lives here and nowhere else is {@see blogStatusOf}. The entity has no `active`
 * flag: whether an entry is live is derived from `publishedAt`, and that derivation is the single
 * thing the list, the detail page and the badge have to agree on. Writing the `if` twice is how the
 * list and the detail page start to disagree about an entry dated exactly now.
 */
import type CriteriaType from 'src/core/data/criteria.data';
import type { Component } from 'vue';

const { Criteria } = Shopwell.Data;

/**
 * How many comments a detail page reads at once.
 *
 * A moderator reads the thread of one entry, not the whole shop; a longer thread than this needs a
 * paginated grid instead of a silent truncation.
 *
 * @private
 */
export const COMMENT_LIMIT = 250;

/**
 * The two states an entry can be in, derived from its publication date.
 *
 * @private
 */
export type BlogStatus = 'published' | 'scheduled';

/**
 * Everything {@see blogStatusOf} needs. Structural on purpose: the list rows, the detail entity and
 * a freshly created (unsaved) entry all qualify.
 *
 * @private
 */
export type BlogLike = {
    publishedAt?: string | Date | null;
};

/**
 * The filters the list page offers. Every one of them is optional - the default list is unfiltered.
 *
 * @private
 */
export type BlogListFilters = {
    type?: string | null;
    status?: BlogStatus | null;
    categoryId?: string | null;
};

/**
 * One row of `blog_comment`.
 *
 * The comment carries the ids of the customer, the sales channel and the language without declaring
 * associations for them - the domain does not grow fields on definitions it does not own. A name for
 * a customer comment therefore comes from a second request, not from `comment.customer`.
 *
 * @private
 */
export type BlogComment = EntitySchema.Entity<'blog_comment'>;

/**
 * A row of `blog_category_assignment`, as the detail page reads it.
 *
 * Filing an entry is writing this row and nothing else: the entry and the category are the two sides
 * of the association, the row is the decision that connects them.
 *
 * @private
 */
export type BlogCategoryAssignment = EntitySchema.Entity<'blog_category_assignment'>;

/**
 * What an entry is, for the two questions the pages ask about `publishedAt`.
 *
 * An entry without a date is live: an editor who writes a page and saves it once expects to find it
 * on the storefront, not to learn that it is still a draft. A date in the future is scheduled. A
 * date in the past - and a date that can not be parsed - is published, because the alternative is to
 * hide content the shop already announced.
 *
 * @private
 */
export function blogStatusOf(blog: BlogLike, now: Date = new Date()): BlogStatus {
    const publishedAt = blog.publishedAt ?? null;

    if (!publishedAt) {
        return 'published';
    }

    const publishedTime = publishedAt instanceof Date ? publishedAt.getTime() : Date.parse(publishedAt);

    if (Number.isNaN(publishedTime)) {
        return 'published';
    }

    return publishedTime > now.getTime() ? 'scheduled' : 'published';
}

/**
 * The service as the module uses it.
 *
 * @private
 */
export default class BlogApiService {
    /**
     * The list of entries: newest publication first, the creation date breaks the tie so two entries
     * dated the same day do not swap places between two requests.
     *
     * The author and the categories are read along, because the list prints their names and a second
     * request per row would be one request too many.
     *
     * @param filters
     * @param term
     */
    public listCriteria(
        page: number,
        limit: number,
        filters: BlogListFilters = {},
        term: string | null = null,
    ): CriteriaType {
        const criteria = new Criteria(page, limit);
        criteria.addAssociation('author');
        criteria.addAssociation('categories.blogCategory');
        criteria.addSorting(Criteria.sort('publishedAt', 'DESC'));
        criteria.addSorting(Criteria.sort('createdAt', 'DESC'));

        if (term) {
            criteria.setTerm(term);
        }

        if (filters.type) {
            criteria.addFilter(Criteria.equals('type', filters.type));
        }

        if (filters.categoryId) {
            criteria.addFilter(Criteria.equals('categories.blogCategoryId', filters.categoryId));
        }

        this.addStatusFilter(criteria, filters.status ?? null);

        return criteria;
    }

    /**
     * One entry by id, with everything the three-column detail page renders.
     */
    public detailCriteria(id: string): CriteriaType {
        const criteria = new Criteria(1, 1);
        criteria.setIds([id]);
        criteria.addAssociation('author');
        criteria.addAssociation('categories.blogCategory');
        criteria.addAssociation('videos');

        return criteria;
    }

    /**
     * The categories of an entry, as the assignment rows the detail page has to create and remove.
     */
    public assignmentsOfBlogCriteria(blogId: string): CriteriaType {
        const criteria = new Criteria(1, 500);
        criteria.addFilter(Criteria.equals('blogId', blogId));
        criteria.addSorting(Criteria.sort('createdAt', 'ASC'));

        return criteria;
    }

    /**
     * The list of categories. The assignments are read along to print how many entries a category
     * holds - a blog has dozens of categories, not thousands, so one association is cheaper than an
     * aggregation the server would have to compute for every list request.
     */
    public categoryListCriteria(page: number, limit: number, term: string | null = null): CriteriaType {
        const criteria = new Criteria(page, limit);
        criteria.addAssociation('assignments');
        criteria.addSorting(Criteria.sort('name', 'ASC'));

        if (term) {
            criteria.setTerm(term);
        }

        return criteria;
    }

    /**
     * One category by id.
     */
    public categoryDetailCriteria(id: string): CriteriaType {
        const criteria = new Criteria(1, 1);
        criteria.setIds([id]);

        return criteria;
    }

    /**
     * The entries filed under one category, newest publication first.
     *
     * The assignment is the path: an entry does not carry a category id, it carries a filing row, and
     * the filter goes through that row.
     */
    public entriesOfCategoryCriteria(categoryId: string): CriteriaType {
        const criteria = new Criteria(1, 100);
        criteria.addFilter(Criteria.equals('categories.blogCategoryId', categoryId));
        criteria.addSorting(Criteria.sort('publishedAt', 'DESC'));
        criteria.addSorting(Criteria.sort('createdAt', 'DESC'));

        return criteria;
    }

    /**
     * The cms pages a blog entry may be laid out with.
     *
     * The `blog_detail` type of the cms page is what keeps the selector free of landing pages and
     * product layouts - a merchant picks a layout that was built for an article.
     */
    public cmsPageOptionsCriteria(): CriteriaType {
        const criteria = new Criteria(1, 100);
        criteria.addFilter(Criteria.equals('type', 'blog_detail'));
        criteria.addSorting(Criteria.sort('name', 'ASC'));

        return criteria;
    }

    /**
     * The cms pages a blog category may be laid out with.
     */
    public categoryCmsPageOptionsCriteria(): CriteriaType {
        const criteria = new Criteria(1, 100);
        criteria.addFilter(Criteria.equals('type', 'blog_listing'));
        criteria.addSorting(Criteria.sort('name', 'ASC'));

        return criteria;
    }

    /**
     * One cms page with the tree the page form needs to render it.
     *
     * The page is read by the repository and not through `blog.cmsPage`: the entry only carries
     * `cmsPageId`, because an association into the cms domain would have to grow a reverse
     * one-to-many on `cms_page`, which belongs to another domain.
     */
    public cmsPageCriteria(id: string): CriteriaType {
        const criteria = new Criteria(1, 1);
        criteria.setIds([id]);
        criteria.addAssociation('previewMedia');
        criteria.addAssociation('sections');
        criteria.addAssociation('sections.blocks');

        const sections = criteria.getAssociation('sections');
        sections?.addSorting(Criteria.sort('position', 'ASC'));

        const blocks = criteria.getAssociation('sections.blocks');

        if (blocks) {
            blocks.addSorting(Criteria.sort('position', 'ASC'));

            const slots = blocks.getAssociation('slots');
            slots?.addAssociation('translations');
        }

        return criteria;
    }

    /**
     * The list of authors. The entries are read along to print how many bylines an author has - a
     * blog has a handful of authors, so one association is cheaper than an aggregation.
     */
    public authorListCriteria(page: number, limit: number, term: string | null = null): CriteriaType {
        const criteria = new Criteria(page, limit);
        criteria.addAssociation('entries');
        criteria.addSorting(Criteria.sort('name', 'ASC'));

        if (term) {
            criteria.setTerm(term);
        }

        return criteria;
    }

    /**
     * One author by id.
     */
    public authorDetailCriteria(id: string): CriteriaType {
        const criteria = new Criteria(1, 1);
        criteria.setIds([id]);

        return criteria;
    }

    /**
     * The entries signed with one author, newest publication first.
     *
     * Read through the entry repository and not through `author.entries`: the author page shows
     * three columns of an entry, and those are the entry's own fields.
     */
    public entriesOfAuthorCriteria(authorId: string): CriteriaType {
        const criteria = new Criteria(1, 100);
        criteria.addFilter(Criteria.equals('authorId', authorId));
        criteria.addSorting(Criteria.sort('publishedAt', 'DESC'));
        criteria.addSorting(Criteria.sort('createdAt', 'DESC'));

        return criteria;
    }

    /**
     * The comments of one entry, oldest first so a thread reads top to bottom.
     */
    public commentsCriteria(blogId: string): CriteriaType {
        const criteria = new Criteria(1, COMMENT_LIMIT);
        criteria.addFilter(Criteria.equals('blogId', blogId));
        criteria.addSorting(Criteria.sort('createdAt', 'ASC'));

        return criteria;
    }

    /**
     * How many comments of one entry still wait, read as a count and not as rows: the rail of the
     * entry page shows the number, the moderation happens on the queue.
     */
    public pendingCommentsOfBlogCriteria(blogId: string): CriteriaType {
        const criteria = new Criteria(1, 1);
        criteria.addFilter(Criteria.equals('blogId', blogId));
        criteria.addFilter(Criteria.equals('status', false));

        return criteria;
    }

    /**
     * Every comment of the shop, newest first, narrowed to one moderation state.
     *
     * The entry and the customer ride along as associations: the list shows which article a comment
     * belongs to and who wrote it, and both are names the moderator needs before reading the text.
     * `null` reads both states, the way the review list treats its status filter.
     */
    public commentListCriteria(page: number, limit: number, status: boolean | null, term: string | null = null): CriteriaType {
        const criteria = new Criteria(page, limit);
        criteria.addAssociation('blog');
        criteria.addAssociation('customer');
        criteria.addSorting(Criteria.sort('createdAt', 'DESC'));

        if (status !== null) {
            criteria.addFilter(Criteria.equals('status', status));
        }

        if (term) {
            criteria.setTerm(term);
        }

        return criteria;
    }

    /**
     * The customers that wrote the comments, so a customer comment gets a name without an
     * association on the comment itself.
     *
     * @param customerIds
     */
    public customerCriteria(customerIds: Array<EntitySchema.EntityKey<'customer'>>): CriteriaType {
        const criteria = new Criteria(1, COMMENT_LIMIT);
        criteria.setIds(customerIds);

        return criteria;
    }

    /**
     * The snippet key of a type. A type a plugin owns has no snippet of its own - the page falls
     * back to the raw value.
     */
    public typeLabelKey(type: string): string {
        return `sw-blog.type.${type}`;
    }

    /**
     * A slug the server will accept on the first save.
     *
     * The name is not transliterated: a chinese name has no reasonable ascii slug, and a generated
     * one is easier to correct on the detail page than a rejected save is to debug. The suffix keeps
     * two categories of the same name apart.
     */
    public slugFor(name: string, fallback: string = 'category'): string {
        const base = name
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
        const suffix = Date.now().toString(36);

        return base === '' ? `${fallback}-${suffix}` : `${base}-${suffix}`;
    }

    /**
     * The snippet key of a derived state.
     */
    public statusLabelKey(status: BlogStatus): string {
        return `sw-blog.status.${status}`;
    }

    /**
     * How many answers a comment has. Replies are one level deep, so counting the rows that point at
     * this comment is the whole count.
     *
     * @param comments
     * @param parentId
     */
    public replyCountOf(comments: BlogComment[], parentId: EntitySchema.EntityKey<'blog_comment'>): number {
        return comments.filter((comment) => comment.parentId === parentId).length;
    }

    /**
     * How deep a comment sits. A root comment is 0, a reply is 1.
     *
     * The walk up is bounded and remembers what it has seen: a broken parent chain must not turn
     * into an endless loop in the browser.
     *
     * @param comments
     */
    public depthOf(comment: BlogComment, comments: BlogComment[]): number {
        let depth = 0;
        let current: BlogComment | undefined = comment;
        const seen = new Set<string>();

        while (current?.parentId && !seen.has(current.parentId) && depth < 10) {
            seen.add(current.parentId);

            const parent = comments.find((candidate) => candidate.id === current?.parentId);

            if (!parent) {
                break;
            }

            depth += 1;
            current = parent;
        }

        return depth;
    }

    /**
     * The display name of a comment: the guest name, or the customer behind the id.
     *
     * @param customerNames
     */
    public authorNameOf(comment: BlogComment, customerNames: Record<string, string>): string {
        if (comment.externalUser) {
            return comment.externalUser;
        }

        if (comment.customerId && customerNames[comment.customerId]) {
            return customerNames[comment.customerId];
        }

        return '';
    }

    /**
     * The body of a comment, cut to a length a grid row can show.
     *
     * @param comment
     * @param length
     */
    public excerptOf(comment: BlogComment, length: number = 120): string {
        const text = (comment.content ?? '').replace(/\s+/g, ' ').trim();

        return text.length > length ? `${text.slice(0, length)}…` : text;
    }

    /**
     * The rows that are no longer wanted, because their category was removed from the selection.
     *
     * @param assignments
     * @param keptCategoryIds
     */
    public assignmentIdsToRemove(
        assignments: BlogCategoryAssignment[],
        keptCategoryIds: Array<EntitySchema.EntityKey<'blog_category'>>,
    ): Array<EntitySchema.EntityKey<'blog_category_assignment'>> {
        return assignments
            .filter((assignment) => {
                return typeof assignment.blogCategoryId === 'string' && !keptCategoryIds.includes(assignment.blogCategoryId);
            })
            .map((assignment) => assignment.id);
    }

    /**
     * The categories of the selection that have no row yet.
     *
     * @param assignments
     * @param selectedCategoryIds
     */
    public categoryIdsToAssign(
        assignments: BlogCategoryAssignment[],
        selectedCategoryIds: Array<EntitySchema.EntityKey<'blog_category'>>,
    ): Array<EntitySchema.EntityKey<'blog_category'>> {
        const assigned = assignments
            .map((assignment) => assignment.blogCategoryId)
            .filter((categoryId): categoryId is EntitySchema.EntityKey<'blog_category'> => {
                return typeof categoryId === 'string';
            });

        return selectedCategoryIds.filter((categoryId) => !assigned.includes(categoryId));
    }

    /**
     * Narrows the list to one derived state.
     *
     * "Published" is a union of two rows: the entries without a date and the entries whose moment
     * has come. Filtering only on `publishedAt <= now` would silently drop every undated entry.
     */
    private addStatusFilter(criteria: CriteriaType, status: BlogStatus | null): void {
        if (!status) {
            return;
        }

        const now = new Date().toISOString();

        if (status === 'scheduled') {
            criteria.addFilter(Criteria.range('publishedAt', { gt: now }));

            return;
        }

        // an entry without a date is live, so "published" is the union of the undated rows and the
        // rows whose moment has come
        criteria.addFilter(
            Criteria.multi('OR', [
                Criteria.equals('publishedAt', null),
                Criteria.range('publishedAt', { lte: now }),
            ]),
        );
    }
}

/**
 * The service as the component sees it.
 *
 * `inject` is not typed per key, so the pages read the service through this bridge instead of
 * widening the global component type for one module.
 *
 * The fallback is not a swallowed error: the service holds no state, every answer it gives is a
 * function of its arguments. A component that was mounted without the registration - a unit test, a
 * page that a plugin embedded - therefore gets the same criteria and the same derivation as one that
 * was wired through `index.ts`, and a container that hands out a different instance still wins.
 *
 * @private
 */
export function blogApi(vm: unknown): BlogApiService {
    const injected = (vm as { blogApiService?: BlogApiService }).blogApiService;

    return injected ?? new BlogApiService();
}

/**
 * The component a blog type renders with, as the registry hands it out.
 *
 * @private
 */
export type BlogTypeModule = { default: Component };
