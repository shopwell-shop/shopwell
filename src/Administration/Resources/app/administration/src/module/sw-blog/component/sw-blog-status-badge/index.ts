/**
 * @sw-package discovery
 */
import { defineComponent } from 'vue';
import template from './sw-blog-status-badge.html.twig';
import type { BlogStatus } from '../../service/blog.api.service';
import { blogStatusOf } from '../../service/blog.api.service';

/** @private */
export default defineComponent({
    template,

    props: {
        active: {
            type: Boolean,
            required: false,
            default: undefined,
        },
        publishedAt: {
            type: [String, Date],
            required: false,
            default: null,
        },
    },

    computed: {
        status(): BlogStatus {
            return blogStatusOf({ active: this.active, publishedAt: this.publishedAt });
        },

        labelKey(): string {
            return `sw-blog.status.${this.status}`;
        },

        variant(): string {
            return { draft: 'neutral', scheduled: 'attention', published: 'positive' }[this.status];
        },
    },
});
