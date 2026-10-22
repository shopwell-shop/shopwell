/**
 * @sw-package discovery
 */

/**
 * One entry: a post, filed under one category, dressed in one layout and signed with one author.
 *
 * The values are deliberately unremarkable - every test asserts what the page does with them, not
 * what they are.
 *
 * @private
 */
export const blog = {
    id: 'blog-1',
    type: 'post',
    title: 'Autumn guide',
    slug: 'autumn-guide',
    teaser: 'Teaser',
    content: '<p>Body</p>',
    metaTitle: null,
    metaDescription: null,
    publishedAt: null,
    authorId: 'author-1',
    coverMediaId: null,
    cmsPageId: 'page-1',
    categories: [],
    videos: [],
};

/**
 * The filing rows of that entry: one category, one row. Filing is a row, not a column, which is why
 * the page diffs rows instead of writing a list of ids.
 *
 * @private
 */
export const assignments = [{ id: 'assignment-1', blogId: 'blog-1', blogCategoryId: 'category-1' }];
