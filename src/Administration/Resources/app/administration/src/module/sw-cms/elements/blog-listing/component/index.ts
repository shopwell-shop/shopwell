import template from './sw-cms-el-blog-listing.html.twig';
import './sw-cms-el-blog-listing.scss';

const { Component, Mixin } = Shopwell;

type BlogListingElementConfig = {
    value?: string | null;
};

type BlogListingElementData = {
    blogs?: EntityCollection<'blog'> | null;
};

/**
 * How many placeholder cards the canvas draws per layout.
 *
 * The number is part of the preview as well - a grid shows more entries than a list simply because a
 * grid is the denser arrangement, and a preview that shows three cards for a grid would misrepresent
 * it.
 */
const PLACEHOLDER_COUNT: Record<string, number> = {
    classic: 3,
    grid: 6,
    list: 3,
};

/**
 * The element on the canvas of the designer.
 *
 * The element does not read from the api. What it renders is either the entries the resolver already
 * put into the element data - that is what a saved page shows - or, while the merchant is building
 * the page, schematic cards that stand in for them. Asking the server here would mean a request per
 * element on a page that has not been saved yet, for a result nobody reads.
 *
 * @private
 * @sw-package discovery
 */
export default Component.wrapComponentConfig({
    template,

    mixins: [Mixin.getByName('cms-element')],

    computed: {
        layout(): string {
            const config = this.element.config.layout as BlogListingElementConfig | undefined;

            return config?.value ?? 'classic';
        },

        blogs(): Array<Entity<'blog'>> {
            const data = this.element.data as unknown as BlogListingElementData | undefined;

            if (!data?.blogs) {
                return [];
            }

            return [...data.blogs];
        },

        hasBlogs(): boolean {
            return this.blogs.length > 0;
        },

        placeholderCount(): number {
            return PLACEHOLDER_COUNT[this.layout] ?? PLACEHOLDER_COUNT.classic;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig();
        },
    },
});
