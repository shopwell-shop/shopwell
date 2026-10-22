/**
 * @sw-package discovery
 */
import { defineComponent } from 'vue';
import template from './sw-blog-detail-content-video.html.twig';

/**
 * A video page: the address of the video, the platform it is served from and its poster frame.
 *
 * The video itself is never uploaded here - the entity stores where the media is hosted, so a shop
 * keeps the bandwidth bill at its video host and a re-encoded file only changes this address. The
 * poster frame is a media entity, hence the upload field next to the two text fields.
 *
 * The row exists before the component is rendered: the detail page creates an unsaved `blog_video`
 * and hands it down, so the fields here always have something to write into.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default defineComponent({
    template,

    props: {
        blog: {
            type: Object,
            required: true,
        },

        video: {
            type: Object,
            required: false,
            default: null,
        },

        isDisabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        providerOptions(): Array<{ value: string; label: string }> {
            return [
                { value: 'youtube', label: this.$t('sw-blog.detail.contentProviderYoutube') },
                { value: 'vimeo', label: this.$t('sw-blog.detail.contentProviderVimeo') },
                { value: 'self-hosted', label: this.$t('sw-blog.detail.contentProviderSelfHosted') },
            ];
        },

        posterSource(): string | null {
            return (this.video as { posterMediaId?: string | null } | null)?.posterMediaId ?? null;
        },
    },

    methods: {
        onPosterSelected(media: Array<{ id: string }>): void {
            if (!this.video) {
                return;
            }

            (this.video as { posterMediaId: string | null }).posterMediaId = media[0]?.id ?? null;
        },

        onPosterRemoved(): void {
            if (!this.video) {
                return;
            }

            (this.video as { posterMediaId: string | null }).posterMediaId = null;
        },
    },
});
