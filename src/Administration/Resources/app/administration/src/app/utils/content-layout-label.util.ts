/**
 * @sw-package discovery
 */
const builtInLayouts = {
    '91ddf2e3fd20050a47409eb9ebbdfff5': {
        name: 'Default blog article',
        key: 'sw-experience-studio.builtinLayouts.defaultBlogPost',
    },
    d25760ad3288f7caa419037d34abf383: {
        name: 'Default blog video',
        key: 'sw-experience-studio.builtinLayouts.defaultBlogVideo',
    },
    b2cd20e487fbc369061861e22ac317aa: {
        name: 'Default blog listing',
        key: 'sw-experience-studio.builtinLayouts.defaultBlogListing',
    },
};

/** @private */
export function getContentLayoutNameKey(layout: { id: string; name: string }): string | null {
    const builtIn = builtInLayouts[layout.id as keyof typeof builtInLayouts];
    return builtIn?.name === layout.name ? builtIn.key : null;
}
