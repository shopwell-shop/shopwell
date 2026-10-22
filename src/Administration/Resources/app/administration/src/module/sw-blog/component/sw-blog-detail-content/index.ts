/**
 * @sw-package discovery
 */
import { defineComponent, markRaw } from 'vue';
import type { Component } from 'vue';
import template from './sw-blog-detail-content.html.twig';
import './sw-blog-detail-content.scss';
import { getBlogTypeComponent } from '../../service/blog-type.registry';

/**
 * The content tab, as the host of whatever edits `blog.type`.
 *
 * The component does not switch over the two types of the core. It asks the registry for a loader,
 * downloads it and renders the result - which is what makes a type a plugin registered editable in
 * the same tab as an article. Only when nobody registered the type does it fall back to the unknown
 * component, which keeps the entry from white-screening and from silently dropping the fields the
 * plugin owns.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default defineComponent({
    template,

    inheritAttrs: false,

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

    data(): {
        contentType: Component | null;
        isUnknownType: boolean;
        isLoading: boolean;
    } {
        return {
            contentType: null,
            isUnknownType: false,
            isLoading: false,
        };
    },

    computed: {
        type(): string {
            return (this.blog as { type?: string }).type ?? '';
        },
    },

    watch: {
        type: {
            immediate: true,
            handler(): void {
                void this.loadTypeComponent();
            },
        },
    },

    methods: {
        async loadTypeComponent(): Promise<void> {
            const type = this.type;
            const loader = getBlogTypeComponent(type);

            if (!loader) {
                this.contentType = null;
                this.isUnknownType = true;

                return;
            }

            this.isLoading = true;
            this.isUnknownType = false;

            try {
                const module = await loader();

                // the merchant may have switched the type while the download was running
                if (this.type !== type) {
                    return;
                }

                this.contentType = markRaw(module.default);
            } finally {
                this.isLoading = false;
            }
        },
    },
});
