/**
 * @sw-package discovery
 */
import { defineComponent } from 'vue';
import template from './sw-blog-status-badge.html.twig';
import type { BlogStatus } from '../../service/blog.api.service';
import { blogStatusOf } from '../../service/blog.api.service';

/**
 * The publication state of an entry as a badge.
 *
 * The badge holds no logic of its own: it asks `blogStatusOf` for the state and only decides how the
 * two states look. It therefore can not disagree with the list, and the list can not disagree with
 * the sidebar of the detail page - the derivation exists once.
 *
 * The variants are the five of `mt-badge`, the same set every other status badge of the
 * administration maps onto: an entry that is live is `positive`, an entry waiting for its moment is
 * `attention` - the colour a pending transaction wears as well.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default defineComponent({
    template,

    props: {
        publishedAt: {
            type: [String, Date],
            required: false,
            default: null,
        },
    },

    computed: {
        status(): BlogStatus {
            return blogStatusOf({ publishedAt: this.publishedAt });
        },

        labelKey(): string {
            return `sw-blog.status.${this.status}`;
        },

        variant(): string {
            return this.status === 'scheduled' ? 'attention' : 'positive';
        },
    },
});
