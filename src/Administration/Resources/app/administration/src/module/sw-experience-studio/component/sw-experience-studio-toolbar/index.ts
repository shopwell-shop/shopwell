import type CriteriaType from 'src/core/data/criteria.data';
import { getContentLayoutNameKey } from 'src/app/utils/content-layout-label.util';

import { getStorefrontSalesChannelCriteria } from 'src/module/sw-experience-studio/util/sales-channel-criteria.util';
import template from './sw-experience-studio-toolbar.html.twig';
import './sw-experience-studio-toolbar.scss';

type Viewport = 'mobile' | 'tablet-landscape' | 'desktop';

/**
 * @private
 * @sw-package discovery
 */
export default Shopwell.Component.wrapComponentConfig({
    template,

    props: {
        layout: {
            type: Object,
            required: false,
            default: null,
        },
        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
        currentViewport: {
            type: String,
            required: false,
            default: 'desktop',
        },
        allowSave: {
            type: Boolean,
            required: false,
            default: false,
        },
        previewSalesChannelId: {
            type: String,
            required: false,
            default: null,
        },
        previewEntityType: {
            type: String,
            required: false,
            default: null,
        },
        previewEntityId: {
            type: String,
            required: false,
            default: null,
        },
        canUndo: {
            type: Boolean,
            required: false,
            default: false,
        },
        canRedo: {
            type: Boolean,
            required: false,
            default: false,
        },
        canManageAssignments: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    emits: [
        'back',
        'viewport-change',
        'save',
        'preview-sales-channel-change',
        'preview-entity-id-change',
        'undo',
        'redo',
        'open-assignments',
    ],

    computed: {
        layoutName(): string {
            const layout = this.layout as Entity<'content_layout'> | null;

            if (!layout) {
                return '';
            }
            const key = getContentLayoutNameKey(layout);
            return key ? this.$t(key) : layout.name;
        },

        previewEntityLabelProperty(): string {
            return this.previewEntityType === 'blog' ? 'title' : 'name';
        },

        salesChannelCriteria(): CriteriaType {
            return getStorefrontSalesChannelCriteria();
        },
    },

    methods: {
        onBack(): void {
            this.$emit('back');
        },

        onViewportChange(viewport: Viewport): void {
            this.$emit('viewport-change', viewport);
        },

        onPreviewSalesChannelChange(salesChannelId: string | null): void {
            this.$emit('preview-sales-channel-change', salesChannelId);
        },

        onPreviewEntityIdChange(entityId: string | null): void {
            this.$emit('preview-entity-id-change', entityId);
        },

        onSave(): void {
            this.$emit('save');
        },

        onUndo(): void {
            this.$emit('undo');
        },

        onRedo(): void {
            this.$emit('redo');
        },

        onOpenAssignments(): void {
            this.$emit('open-assignments');
        },
    },
});
