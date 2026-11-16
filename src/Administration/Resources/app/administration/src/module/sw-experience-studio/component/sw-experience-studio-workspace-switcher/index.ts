import type { ExperienceStudioWorkspaceStore } from '../../store/experience-studio-workspace.store';
import template from './sw-experience-studio-workspace-switcher.html.twig';
import './sw-experience-studio-workspace-switcher.scss';

/** @private @sw-package discovery */
export default Shopwell.Component.wrapComponentConfig({
    template,
    inject: ['acl'],
    emits: [
        'select',
        'create',
        'browse',
    ],
    props: { disabled: { type: Boolean, default: false } },
    computed: {
        allowCreate(): boolean {
            return this.acl.can('experience_studio.editor');
        },
        workspaceStore(): ExperienceStudioWorkspaceStore {
            return Shopwell.Store.get('experienceStudioWorkspace' as never) as ExperienceStudioWorkspaceStore;
        },
    },
    created(): void {
        void this.workspaceStore.load();
    },
    methods: {
        onOpenChange(open: boolean): void {
            if (open) {
                void this.workspaceStore.load();
            }
        },
    },
});
