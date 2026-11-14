/** @sw-package fundamentals@framework */
import type { PropType } from 'vue';
import type Repository from 'src/core/data/repository.data';
import template from './sw-permissions-role-info-modal.html.twig';
import './sw-permissions-role-info-modal.scss';

type RoleInformation = Pick<Entity<'acl_role'>, 'id' | 'name' | 'description' | 'privileges'> & { isNew: () => boolean };

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    inject: [
        'acl',
        'repositoryFactory',
        'privileges',
        'ssoSettingsService',
    ],
    emits: ['close', 'created', 'confirm'],
    props: {
        role: { type: Object as PropType<Entity<'acl_role'> | null>, default: null },
        copyFromId: { type: String, default: null },
    },
    data() {
        return {
            formRole: null as RoleInformation | null,
            newRole: null as Entity<'acl_role'> | null,
            isLoading: false,
            isSourceLoaded: false,
            hasError: false,
            confirmPasswordModal: false,
        };
    },
    computed: {
        roleRepository(): Repository<'acl_role'> {
            return this.repositoryFactory.create('acl_role');
        },
        canEdit(): boolean {
            return this.acl.can(this.role ? 'permissions.editor' : 'permissions.creator');
        },
        canSubmit(): boolean {
            return this.canEdit && this.isSourceLoaded && !!this.formRole?.name.trim() && !this.isLoading;
        },
    },
    created() {
        void this.initializeRole();
    },
    methods: {
        async initializeRole(): Promise<void> {
            if (this.role) {
                this.formRole = {
                    id: this.role.id,
                    name: this.role.name,
                    description: this.role.description ?? '',
                    privileges: [...this.role.privileges],
                    isNew: () => false,
                };
                this.isSourceLoaded = true;
                return;
            }
            this.newRole = this.roleRepository.create();
            this.newRole.name = '';
            this.newRole.description = '';
            this.newRole.privileges = [...this.privileges.getDefaultUserPrivileges()];
            this.newRole.menuIds = [];
            this.formRole = this.newRole;
            if (!this.copyFromId) {
                this.isSourceLoaded = true;
                return;
            }
            this.isLoading = true;
            try {
                const criteria = new Shopwell.Data.Criteria();
                criteria.addAssociation('adminMenuItems');
                const source = await this.roleRepository.get(
                    this.copyFromId as EntityKey<'acl_role'>,
                    Shopwell.Context.api,
                    criteria,
                );
                if (!source) throw new Error('Role not found');
                this.newRole.name = this.$t('sw-permissions.roles.info.copyName', { name: source.name });
                this.newRole.description = source.description ?? '';
                this.newRole.privileges = [...source.privileges];
                this.newRole.menuIds = [...(source.menuIds ?? source.adminMenuItems?.getIds() ?? [])];
                this.isSourceLoaded = true;
            } catch {
                this.hasError = true;
            } finally {
                this.isLoading = false;
            }
        },
        close(): void {
            if (!this.isLoading) this.$emit('close');
        },
        async submit(): Promise<void> {
            if (!this.canSubmit || !this.formRole || this.confirmPasswordModal) return;
            const information = { name: this.formRole.name.trim(), description: this.formRole.description ?? '' };
            if (this.role) {
                this.$emit('confirm', information);
                this.$emit('close');
                return;
            }
            if (!this.newRole) return;
            Object.assign(this.newRole, information);
            this.hasError = false;
            this.isLoading = true;
            try {
                const response = await this.ssoSettingsService.isSso();
                if (response.isSso) {
                    // saveRole owns the loading state while the verified endpoint runs.
                    this.isLoading = false;
                    await this.saveRole({ ...Shopwell.Context.api });
                } else {
                    this.confirmPasswordModal = true;
                }
            } catch {
                this.hasError = true;
            } finally {
                this.isLoading = false;
            }
        },
        async saveRole(context: apiContext): Promise<void> {
            if (!this.canEdit || !this.newRole || this.isLoading) return;
            this.isLoading = true;
            this.confirmPasswordModal = false;
            try {
                await this.roleRepository.save(this.newRole, context);
                this.$emit('created', this.newRole.id);
            } catch {
                this.hasError = true;
            } finally {
                this.isLoading = false;
            }
        },
    },
});
