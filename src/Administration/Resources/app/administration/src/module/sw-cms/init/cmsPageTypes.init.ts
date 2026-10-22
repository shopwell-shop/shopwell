/**
 * @sw-package discovery
 */
const defaultPageTypes = [
    {
        name: 'page',
        icon: 'regular-lightbulb',
    },
    {
        name: 'landingpage',
        icon: 'regular-dashboard',
    },
    {
        name: 'product_list',
        icon: 'regular-shopping-basket',
    },
    {
        name: 'product_detail',
        icon: 'regular-tag',
    },
    // a blog layout is content, so it is created like any other layout instead of being edited
    // somewhere else - the entry only picks the finished layout afterwards
    {
        name: 'blog_detail',
        icon: 'regular-image-text',
    },
    {
        name: 'blog_listing',
        icon: 'regular-image-text',
    },
];

/**
 * @private
 */
export default () => {
    const pageTypeService = Shopwell.Service().get('cmsPageTypeService');

    defaultPageTypes.forEach((type: { name: string; icon: string }) => {
        pageTypeService.register(type);
    });
};
