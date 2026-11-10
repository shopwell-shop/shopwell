/**
 * @sw-package discovery
 */
/** @private */
export function getBlogDefaultLayoutId(
    values: Record<string, unknown>,
    type: string,
): EntitySchema.EntityKey<'content_layout'> | null {
    const typed = values[`core.content_system.default_blog_${type}_content_layout`];
    if (typeof typed === 'string' && typed) {
        return typed as EntitySchema.EntityKey<'content_layout'>;
    }

    if (type === 'listing') {
        return null;
    }

    const generic = values['core.content_system.default_blog_content_layout'];
    return typeof generic === 'string' && generic ? (generic as EntitySchema.EntityKey<'content_layout'>) : null;
}
