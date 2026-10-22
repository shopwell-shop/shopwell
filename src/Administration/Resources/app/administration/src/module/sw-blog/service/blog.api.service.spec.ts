/**
 * @sw-package discovery
 */
import BlogApiService, { blogApi, blogStatusOf, COMMENT_LIMIT } from './blog.api.service';
import type { BlogCategoryAssignment, BlogComment } from './blog.api.service';

type Sortings = Array<{ field: string; order: string }>;
type Filters = Array<{ type: string; field?: string; operator?: string; value?: unknown; queries?: Filters }>;
type Association = { association: string; criteria: { associations: Association[]; sortings: Sortings } };

const service = new BlogApiService();

/**
 * The fields a thread fixture varies. Kept flat on purpose: the entity class carries methods, and
 * spreading a partial of it would drag them along.
 */
type CommentOverrides = {
    parentId?: BlogComment['parentId'];
    customerId?: BlogComment['customerId'];
    externalUser?: string;
    content?: string;
    status?: boolean;
};

/**
 * A row of `blog_comment`.
 *
 * The ids are branded in the generated schema; a fixture spells them out once here instead of at
 * every call site.
 */
function comment(id: string, overrides: CommentOverrides = {}): BlogComment {
    return {
        id: id as BlogComment['id'],
        blogId: 'blog-1' as BlogComment['blogId'],
        content: overrides.content ?? '',
        status: overrides.status ?? false,
        salesChannelId: 'sales-channel-1' as BlogComment['salesChannelId'],
        languageId: 'language-1' as BlogComment['languageId'],
        createdAt: '2026-05-01T10:00:00.000Z' as BlogComment['createdAt'],
        ...overrides,
    } as BlogComment;
}

/**
 * A row of `blog_category_assignment`.
 *
 * `blogCategoryId` is a required column in the generated schema although the php side allows null,
 * so the one row that removes that assumption is built here.
 */
function assignment(id: string, categoryId: string | null): BlogCategoryAssignment {
    return {
        id,
        blogId: 'blog-1',
        blogCategoryId: categoryId,
        createdAt: '2026-05-01T10:00:00.000Z',
    } as unknown as BlogCategoryAssignment;
}

/**
 * The names of the associations a criteria reads: `addAssociation` stores an association object, not
 * the bare name, and a nested path becomes a nested association.
 */
function associationNames(criteria: { associations: unknown }, prefix: string = ''): string[] {
    return (criteria.associations as Association[]).flatMap((association) => {
        const name = `${prefix}${association.association}`;

        return [name, ...associationNames(association.criteria, `${name}.`)];
    });
}

function associationOf(criteria: { associations: unknown }, name: string): Association | undefined {
    return (criteria.associations as Association[]).find((association) => association.association === name);
}

describe('src/module/sw-blog/service/blog.api.service', () => {
    describe('blogStatusOf', () => {
        const now = new Date('2026-05-01T10:00:00.000Z');

        it('treats an entry without a publication date as published', () => {
            expect(blogStatusOf({ publishedAt: null }, now)).toBe('published');
            expect(blogStatusOf({}, now)).toBe('published');
        });

        it('treats a date in the past as published', () => {
            expect(blogStatusOf({ publishedAt: '2026-04-30T10:00:00.000Z' }, now)).toBe('published');
            expect(blogStatusOf({ publishedAt: new Date('2026-04-30T10:00:00.000Z') }, now)).toBe('published');
        });

        it('treats a date in the future as scheduled', () => {
            expect(blogStatusOf({ publishedAt: '2026-05-02T10:00:00.000Z' }, now)).toBe('scheduled');
        });

        it('treats the exact moment as published, so a timed entry flips on time', () => {
            expect(blogStatusOf({ publishedAt: '2026-05-01T10:00:00.000Z' }, now)).toBe('published');
        });

        it('falls back to published when the date can not be parsed', () => {
            expect(blogStatusOf({ publishedAt: 'not a date' }, now)).toBe('published');
        });
    });

    describe('listCriteria', () => {
        it('reads newest publication first with the creation date as tie breaker', () => {
            const criteria = service.listCriteria(2, 25);

            expect(criteria.page).toBe(2);
            expect(criteria.limit).toBe(25);
            expect(criteria.sortings as Sortings).toEqual([
                expect.objectContaining({ field: 'publishedAt', order: 'DESC' }),
                expect.objectContaining({ field: 'createdAt', order: 'DESC' }),
            ]);
        });

        it('reads the author and the categories along, because the list prints their names', () => {
            expect(associationNames(service.listCriteria(1, 25))).toEqual([
                'author',
                'categories',
                'categories.blogCategory',
            ]);
        });

        it('passes the search term along only when there is one', () => {
            expect(service.listCriteria(1, 25, {}, 'autumn').term).toBe('autumn');
            expect(service.listCriteria(1, 25, {}, null).term).toBeNull();
        });

        it('filters by type and by category without pinning a language', () => {
            const criteria = service.listCriteria(1, 25, { type: 'video', categoryId: 'category-1' });

            expect(criteria.filters as Filters).toEqual([
                expect.objectContaining({ type: 'equals', field: 'type', value: 'video' }),
                expect.objectContaining({ type: 'equals', field: 'categories.blogCategoryId', value: 'category-1' }),
            ]);
            expect((criteria.filters as Filters).some((filter) => filter.field === 'languageId')).toBe(false);
        });

        it('filters a scheduled entry by a date in the future', () => {
            const filters = service.listCriteria(1, 25, { status: 'scheduled' }).filters as Filters;

            expect(filters).toHaveLength(1);
            expect(filters[0].type).toBe('range');
            expect(filters[0].field).toBe('publishedAt');
            expect(filters[0].value).toBeUndefined();
            expect(JSON.stringify(filters[0])).toContain('gt');
        });

        it('filters a published entry as the union of the undated and the dated rows', () => {
            const filters = service.listCriteria(1, 25, { status: 'published' }).filters as Filters;

            expect(filters).toHaveLength(1);
            expect(filters[0].type).toBe('multi');
            expect(filters[0].operator).toBe('OR');
            expect(filters[0].queries).toHaveLength(2);
            expect(filters[0].queries?.[0]).toEqual(
                expect.objectContaining({ type: 'equals', field: 'publishedAt', value: null }),
            );
            expect(filters[0].queries?.[1]).toEqual(expect.objectContaining({ type: 'range', field: 'publishedAt' }));
        });

        it('reads everything when no filter is set', () => {
            expect(service.listCriteria(1, 25).filters).toEqual([]);
        });
    });

    describe('detailCriteria', () => {
        it('asks for one entry with the associations the page renders', () => {
            const criteria = service.detailCriteria('blog-1');

            expect(criteria.ids).toEqual(['blog-1']);
            expect(criteria.limit).toBe(1);
            expect(associationNames(criteria)).toEqual([
                'author',
                'categories',
                'categories.blogCategory',
                'videos',
            ]);
        });
    });

    describe('assignment criteria', () => {
        it('reads the categories of an entry as the rows the page has to write', () => {
            const criteria = service.assignmentsOfBlogCriteria('blog-1');

            expect(criteria.filters as Filters).toEqual([
                expect.objectContaining({ type: 'equals', field: 'blogId', value: 'blog-1' }),
            ]);
        });
    });

    describe('category criteria', () => {
        it('reads the categories by name and counts the assignments along', () => {
            const criteria = service.categoryListCriteria(1, 25, 'guides');

            expect(criteria.term).toBe('guides');
            expect(associationNames(criteria)).toEqual(['assignments']);
            expect(criteria.sortings as Sortings).toEqual([
                expect.objectContaining({ field: 'name', order: 'ASC' }),
            ]);
        });

        it('asks for one category by id', () => {
            expect(service.categoryDetailCriteria('category-1').ids).toEqual(['category-1']);
        });
    });

    describe('cms page criteria', () => {
        it('offers only layouts built for an article', () => {
            expect(service.cmsPageOptionsCriteria().filters as Filters).toEqual([
                expect.objectContaining({ type: 'equals', field: 'type', value: 'blog_detail' }),
            ]);
        });

        it('offers only layouts built for a category listing', () => {
            expect(service.categoryCmsPageOptionsCriteria().filters as Filters).toEqual([
                expect.objectContaining({ type: 'equals', field: 'type', value: 'blog_listing' }),
            ]);
        });

        it('reads the layout with the tree the page form needs', () => {
            const criteria = service.cmsPageCriteria('page-1');
            const sections = associationOf(criteria, 'sections');
            const names = associationNames(criteria);

            expect(criteria.ids).toEqual(['page-1']);
            expect(names).toContain('previewMedia');
            expect(names).toContain('sections.blocks');
            expect(names).toContain('sections.blocks.slots.translations');
            expect(sections?.criteria.sortings).toEqual([expect.objectContaining({ field: 'position', order: 'ASC' })]);
        });
    });

    describe('comment criteria', () => {
        it('reads the comments of one entry, oldest first', () => {
            const criteria = service.commentsCriteria('blog-1');

            expect(criteria.limit).toBe(COMMENT_LIMIT);
            expect(criteria.filters as Filters).toEqual([
                expect.objectContaining({ type: 'equals', field: 'blogId', value: 'blog-1' }),
            ]);
            expect(criteria.sortings as Sortings).toEqual([expect.objectContaining({ field: 'createdAt', order: 'ASC' })]);
        });

        it('reads the customers of a thread in one request', () => {
            expect(
                service.customerCriteria([
                    'customer-1' as EntitySchema.EntityKey<'customer'>,
                    'customer-2' as EntitySchema.EntityKey<'customer'>,
                ]).ids,
            ).toEqual(['customer-1', 'customer-2']);
        });
    });

    describe('labels', () => {
        it('builds the snippet key of a type and of a state', () => {
            expect(service.typeLabelKey('video')).toBe('sw-blog.type.video');
            expect(service.statusLabelKey('scheduled')).toBe('sw-blog.status.scheduled');
        });
    });

    describe('comments', () => {
        const comments = [
            comment('comment-1', { content: 'First!', status: true }),
            comment('comment-2', { parentId: 'comment-1' as BlogComment['parentId'], content: 'A reply' }),
            comment('comment-3', {
                parentId: 'comment-2' as BlogComment['parentId'],
                content: 'A reply to a reply the write route never allows',
            }),
        ];

        it('counts the answers of a comment', () => {
            expect(service.replyCountOf(comments, 'comment-1' as BlogComment['id'])).toBe(1);
            expect(service.replyCountOf(comments, 'comment-3' as BlogComment['id'])).toBe(0);
        });

        it('reads the depth of a comment from its parent chain', () => {
            expect(service.depthOf(comments[0], comments)).toBe(0);
            expect(service.depthOf(comments[1], comments)).toBe(1);
            expect(service.depthOf(comments[2], comments)).toBe(2);
        });

        it('survives a broken parent chain instead of looping forever', () => {
            const orphan = comment('comment-9', {
                parentId: 'missing' as BlogComment['parentId'],
                content: 'x',
            });

            expect(service.depthOf(orphan, [orphan])).toBe(0);
        });

        it('names a guest by the name he wrote and a customer by the account', () => {
            expect(service.authorNameOf(comment('comment-1', { externalUser: 'Ada' }), {})).toBe('Ada');
            expect(
                service.authorNameOf(comment('comment-1', { customerId: 'customer-1' as BlogComment['customerId'] }), {
                    'customer-1': 'Grace Hopper',
                }),
            ).toBe('Grace Hopper');
            expect(service.authorNameOf(comment('comment-1'), {})).toBe('');
        });

        it('cuts a long comment for a grid row', () => {
            expect(service.excerptOf(comment('comment-1', { content: 'a'.repeat(200) }))).toHaveLength(121);
            expect(service.excerptOf(comment('comment-1', { content: '  short\n text  ' }))).toBe('short text');
        });
    });

    describe('set arithmetic of the assignments', () => {
        const assignments = [assignment('assignment-1', 'category-1'), assignment('assignment-2', null)];

        it('names the rows whose category was removed from the selection', () => {
            expect(
                service.assignmentIdsToRemove(assignments, ['category-2' as EntitySchema.EntityKey<'blog_category'>]),
            ).toEqual([
                'assignment-1',
            ]);
        });

        it('names the categories of the selection that have no row yet', () => {
            expect(
                service.categoryIdsToAssign(assignments, [
                    'category-1' as EntitySchema.EntityKey<'blog_category'>,
                    'category-2' as EntitySchema.EntityKey<'blog_category'>,
                ]),
            ).toEqual(['category-2']);
        });
    });

    describe('blogApi', () => {
        it('reads the service the page was given', () => {
            expect(blogApi({ blogApiService: service })).toBe(service);
        });
    });
});
