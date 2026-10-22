/**
 * @sw-package discovery
 */
import { defineComponent } from 'vue';
import template from './sw-blog-detail-content-post.html.twig';

/**
 * An article: a rich text body below the headline.
 *
 * The body is html because the editor produces html, so it goes through the sanitiser of the text
 * editor with the field name it is stored under. Everything else of an article - title, teaser, seo -
 * lives in the general and seo tabs, not here.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default defineComponent({
    template,

    props: {
        blog: {
            type: Object,
            required: true,
        },

        isDisabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },
});
