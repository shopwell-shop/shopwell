/**
 * @sw-package discovery
 */
import { defineComponent } from 'vue';
import template from './sw-blog-detail-content-unknown.html.twig';
import './sw-blog-detail-content-unknown.scss';

/**
 * The fallback for a type nobody registered an editor for.
 *
 * A plugin can bring a type without shipping an administration component for it - or the plugin that
 * brought the component is not installed anymore. Either way the entry must not white-screen and its
 * fields must not be dropped: the component says who is responsible and prints the fields the
 * administration does own, so a merchant can at least see what the entry is.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default defineComponent({
    template,

    props: {
        blog: {
            type: Object,
            required: true,
        },
    },

    computed: {
        type(): string {
            return (this.blog as { type?: string }).type ?? '';
        },

        /**
         * The sentence with the type filled in.
         *
         * The interpolation lives here and not in the template: this administration compiles its
         * templates through twig, and a `$t` call with an object literal in a template tag breaks
         * the tag - the page would render blank instead of the banner.
         */
        description(): string {
            return this.$t('sw-blog.detail.unknownDescription', { type: this.type });
        },
    },
});
