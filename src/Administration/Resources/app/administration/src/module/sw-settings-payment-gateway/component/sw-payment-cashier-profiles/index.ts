/**
 * @sw-package framework
 */
import type { PropType } from 'vue';
import type RepositoryType from 'src/core/data/repository.data';
import useNotification from 'src/app/composables/use-notification';
import template from './sw-payment-cashier-profiles.html.twig';

const { Criteria } = Shopwell.Data;
type CashierProfile = Entity<'payment_cashier_config'> & { allowedMethods: string[] };
type MethodOption = { value: string; label: string };

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,

    inject: ['repositoryFactory', 'acl'],

    props: {
        appId: { type: String, required: true },
        methodOptions: { type: Array as PropType<MethodOption[]>, required: true },
    },

    data(): { profiles: CashierProfile[]; isLoading: boolean; busyIds: string[] } {
        return { profiles: [], isLoading: true, busyIds: [] };
    },

    computed: {
        repository(): RepositoryType<'payment_cashier_config'> {
            return this.repositoryFactory.create('payment_cashier_config');
        },
        canEdit(): boolean {
            return this.acl.can('payment_app.editor');
        },
    },

    watch: {
        appId(): void {
            void this.loadProfiles();
        },
    },

    created(): void {
        void this.loadProfiles();
    },

    methods: {
        async loadProfiles(): Promise<void> {
            this.isLoading = true;
            try {
                const criteria = new Criteria(1, 100);
                criteria.setTotalCountMode(0);
                criteria.addFilter(Criteria.equals('paymentAppId', this.appId));
                criteria.addSorting(Criteria.sort('technicalName', 'ASC'));
                const profiles = await this.repository.search(criteria, Shopwell.Context.api);
                this.profiles = profiles.map((profile) => this.normalizeProfile(profile));
            } catch {
                this.notifyError();
            } finally {
                this.isLoading = false;
            }
        },

        normalizeProfile(profile: Entity<'payment_cashier_config'>): CashierProfile {
            profile.allowedMethods = Array.isArray(profile.allowedMethods) ? profile.allowedMethods : [];
            return profile as CashierProfile;
        },

        addProfile(): void {
            const profile = this.normalizeProfile(this.repository.create(Shopwell.Context.api));
            profile.paymentAppId = this.appId as Entity<'payment_app'>['id'];
            profile.technicalName = '';
            profile.active = true;
            profile.routingPolicy = 'priority';
            this.profiles.push(profile);
        },

        async saveProfile(profile: CashierProfile): Promise<void> {
            if (!this.canEdit || !profile.technicalName.trim() || !profile.routingPolicy.trim()) {
                return;
            }
            this.busyIds.push(profile.id);
            try {
                profile.technicalName = profile.technicalName.trim();
                profile.routingPolicy = profile.routingPolicy.trim();
                await this.repository.save(profile, Shopwell.Context.api);
                const saved = await this.repository.get(profile.id, Shopwell.Context.api);
                if (saved === null) {
                    this.notifyError();
                    return;
                }
                const index = this.profiles.findIndex((item) => item.id === profile.id);
                this.profiles.splice(index, 1, this.normalizeProfile(saved));
                useNotification().createNotificationSuccess({
                    message: this.$t('sw-settings-payment-gateway.cashier.saved'),
                });
            } catch {
                this.notifyError();
            } finally {
                this.busyIds = this.busyIds.filter((id) => id !== profile.id);
            }
        },

        async deleteProfile(profile: CashierProfile): Promise<void> {
            if (!this.canEdit) {
                return;
            }
            this.busyIds.push(profile.id);
            try {
                if (!profile.isNew()) {
                    await this.repository.delete(profile.id, Shopwell.Context.api);
                }
                this.profiles = this.profiles.filter((item) => item.id !== profile.id);
            } catch {
                this.notifyError();
            } finally {
                this.busyIds = this.busyIds.filter((id) => id !== profile.id);
            }
        },

        notifyError(): void {
            useNotification().createNotificationError({ message: this.$t('sw-settings-payment-gateway.cashier.failed') });
        },
    },
});
