/**
 * @sw-package discovery
 */
import { markRaw } from 'vue';
import type { Component } from 'vue';
import template from './sw-blog-detail-content.html.twig';
import './sw-blog-detail-content.scss';
import { getBlogTypeComponent } from '../../service/blog-type.registry';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
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
        contentType: Component | string | null;
        isUnknownType: boolean;
        loadRequest: number;
    } {
        return {
            contentType: null,
            isUnknownType: false,
            loadRequest: 0,
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
            const request = ++this.loadRequest;
            const type = this.type;
            this.contentType = null;
            const loader = getBlogTypeComponent(type);

            if (!loader) {
                this.contentType = null;
                this.isUnknownType = true;

                return;
            }

            this.isUnknownType = false;

            try {
                const module = await loader();

                // the merchant may have switched the type while the download was running
                if (this.loadRequest !== request) {
                    return;
                }

                this.contentType = typeof module === 'string' ? module : markRaw(module.default);
            } catch {
                if (this.loadRequest === request) {
                    this.contentType = null;
                    this.isUnknownType = true;
                }
            }
        },
    },
});
