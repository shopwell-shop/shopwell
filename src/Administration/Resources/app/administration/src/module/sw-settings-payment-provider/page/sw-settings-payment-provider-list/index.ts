/**
 * @sw-package framework
 */
import type { PaymentAccountView } from 'src/module/sw-payment/service/payment-gateway.api.service';
import useNotification from 'src/app/composables/use-notification';
import template from './sw-settings-payment-provider-list.html.twig';
import './sw-settings-payment-provider-list.scss';

type ProviderLabel = { technicalName: string; label: string };
type SortField = 'name' | 'providerTechnicalName' | 'active';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    inject: ['paymentGatewayService', 'acl'],
    data(): {
        accounts: PaymentAccountView[];
        providers: ProviderLabel[];
        term: string;
        page: number;
        limit: number;
        sortBy: SortField;
        sortDirection: 'ASC' | 'DESC';
        isLoading: boolean;
        isDeleting: boolean;
        deleteTarget: PaymentAccountView | null;
        error: string | null;
    } {
        return {
            accounts: [],
            providers: [],
            term: '',
            page: 1,
            limit: 25,
            sortBy: 'name',
            sortDirection: 'ASC',
            isLoading: true,
            isDeleting: false,
            deleteTarget: null,
            error: null,
        };
    },
    computed: {
        columns() {
            return [
                { property: 'name', label: 'sw-settings-payment-provider.accounts.name', primary: true, allowResize: true },
                {
                    property: 'providerTechnicalName',
                    label: 'sw-settings-payment-provider.accounts.provider',
                    allowResize: true,
                },
                {
                    property: 'active',
                    label: 'sw-settings-payment-provider.accounts.active',
                    align: 'center',
                    width: '140px',
                    allowResize: true,
                },
                {
                    property: 'enabledProducts',
                    label: 'sw-settings-payment-provider.accounts.products',
                    align: 'center',
                    width: '140px',
                    sortable: false,
                    allowResize: true,
                },
            ];
        },
        filteredAccounts(): PaymentAccountView[] {
            const term = this.term.trim().toLowerCase();
            const accounts = this.accounts.filter((account) =>
                [
                    this.accountLabel(account),
                    account.providerTechnicalName,
                    this.providerLabel(account.providerTechnicalName),
                ].some((value) => value.toLowerCase().includes(term)),
            );
            return accounts.sort(
                (left, right) =>
                    this.sortValue(left).localeCompare(this.sortValue(right), undefined, {
                        numeric: true,
                        sensitivity: 'base',
                    }) * (this.sortDirection === 'ASC' ? 1 : -1),
            );
        },
        visibleAccounts(): PaymentAccountView[] {
            return this.filteredAccounts.slice((this.page - 1) * this.limit, this.page * this.limit);
        },
    },
    watch: {
        term(): void {
            this.page = 1;
        },
    },
    created(): void {
        void this.load();
    },
    methods: {
        async load(): Promise<void> {
            this.isLoading = true;
            this.error = null;
            try {
                const [accounts, providers] = await Promise.all([
                    this.paymentGatewayService.listAccounts(),
                    this.paymentGatewayService.listAccountDefinitions(),
                ]);
                this.accounts = accounts;
                this.providers = providers as ProviderLabel[];
            } catch {
                this.error = this.$t('sw-settings-payment-provider.accounts.loadError');
            } finally {
                this.isLoading = false;
            }
        },
        providerLabel(technicalName: string): string {
            const label =
                this.providers.find((provider) => provider.technicalName === technicalName)?.label || technicalName;
            return this.$te(label) ? this.$t(label) : label;
        },
        accountLabel(account: PaymentAccountView): string {
            return account.isDefault && account.name === `${account.providerTechnicalName} platform`
                ? account.providerTechnicalName
                : account.name;
        },
        sortValue(account: PaymentAccountView): string {
            if (this.sortBy === 'name') return this.accountLabel(account);
            if (this.sortBy === 'providerTechnicalName') return this.providerLabel(account.providerTechnicalName);
            return account.active ? '1' : '0';
        },
        onSort(column: { dataIndex: string }): void {
            if (!['name', 'providerTechnicalName', 'active'].includes(column.dataIndex)) return;
            this.sortDirection = this.sortBy === column.dataIndex && this.sortDirection === 'ASC' ? 'DESC' : 'ASC';
            this.sortBy = column.dataIndex as SortField;
            this.page = 1;
        },
        onPageChange({ page, limit }: { page: number; limit: number }): void {
            this.page = page;
            this.limit = limit;
        },
        openDelete(account: PaymentAccountView): void {
            if (!this.acl.can('payment_account.deleter') || account.isDefault) return;
            this.deleteTarget = account;
        },
        closeDelete(): void {
            this.deleteTarget = null;
        },
        onDeleteModalChange(isOpen: boolean): void {
            if (!isOpen) this.closeDelete();
        },
        async confirmDelete(): Promise<void> {
            const account = this.deleteTarget;
            if (!account || !this.acl.can('payment_account.deleter') || account.isDefault) return;
            this.isDeleting = true;
            this.error = null;
            try {
                await this.paymentGatewayService.deleteAccount(account.id);
                this.accounts = this.accounts.filter((item) => item.id !== account.id);
                this.page = Math.min(this.page, Math.max(1, Math.ceil(this.filteredAccounts.length / this.limit)));
                this.closeDelete();
            } catch {
                this.error = this.$t('sw-settings-payment-provider.accounts.deleteError');
                useNotification().createNotificationError({ message: this.error });
            } finally {
                this.isDeleting = false;
            }
        },
    },
});
