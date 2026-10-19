/**
 * @sw-package framework
 */
import { defineComponent } from 'vue';
import type { OAuth2Channel, OAuth2ChannelField, OAuth2ChannelFieldConfig } from '../../service/oauth2-channel.api.service';
import template from './sw-settings-oauth2-login.html.twig';

const { Criteria } = Shopwell.Data;
const { Mixin } = Shopwell;

/**
 * The row of `oauth2_client` as this page reads it. The repository hands back the entity, this is
 * the shape of the properties the page looks at.
 */
type OAuth2Client = EntitySchema.Entities['oauth2_client'];

type OAuth2ClientId = EntitySchema.EntityKey<'oauth2_client'>;

/**
 * A channel of the server, together with the values the row holds for it - the shape the page
 * actually works on. `formFields` is what the channel declares, the page adds no field of its own.
 */
interface ChannelState {
    technicalName: string;
    label: string;
    formFields: OAuth2ChannelField[];
    id: OAuth2ClientId | null;
    enabled: boolean;
    values: Record<string, unknown>;
}

/**
 * The settings of a channel, one tab per channel.
 *
 * @sw-package framework
 * @private
 */
export default defineComponent({
    template,

    inject: ['repositoryFactory', 'oauth2ChannelService', 'acl'],

    mixins: [Mixin.getByName('notification')],

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    data() {
        return {
            channels: [] as ChannelState[],
            activeChannel: null as string | null,
            isLoading: true,
            isSaving: false,
            removeTarget: null as ChannelState | null,
        };
    },

    computed: {
        clientRepository() {
            return this.repositoryFactory.create('oauth2_client');
        },

        channelTabs() {
            return this.channels.map((channel) => {
                return {
                    label: channel.label,
                    name: channel.technicalName,
                    hasError: this.isChannelInvalid(channel),
                };
            });
        },

        activeChannelState(): ChannelState | undefined {
            return this.channels.find((channel) => channel.technicalName === this.activeChannel) ?? this.channels[0];
        },
    },

    created() {
        void this.createdComponent();
    },

    methods: {
        async createdComponent(): Promise<void> {
            this.isLoading = true;

            try {
                await this.loadChannels();
            } catch (error) {
                this.createNotificationError({
                    title: this.$t('global.default.error'),
                    message: error instanceof Error ? error.message : '',
                });
            } finally {
                this.isLoading = false;
            }
        },

        /**
         * The channels are code and live in the server, only their credentials are data. Both halves
         * are fetched here and merged into the state the page works on: the server sends the fields of
         * a channel, the row carries the values they had the last time.
         */
        async loadChannels(): Promise<void> {
            const [channels, clients] = await Promise.all([
                this.oauth2ChannelService.list(),
                this.clientRepository.search(this.channelCriteria()),
            ]);

            const configured: Record<string, OAuth2Client | undefined> = {};
            clients?.forEach((client) => {
                configured[client.technicalName] = client;
            });

            this.channels = channels.map((channel) => {
                return this.createChannelState(channel, configured[channel.technicalName] ?? null);
            });

            // Keep the tab the user is on, it only falls back when that channel is gone.
            if (!this.channels.some((channel) => channel.technicalName === this.activeChannel)) {
                this.activeChannel = this.channels[0]?.technicalName ?? null;
            }
        },

        /**
         * Every channel is read in one page, a shop never has more of them than that. The page does
         * not show a total, so the request for one is left out.
         */
        channelCriteria() {
            const criteria = new Criteria(1, 100);
            criteria.setTotalCountMode(0);

            return criteria;
        },

        createChannelState(channel: OAuth2Channel, client: OAuth2Client | null): ChannelState {
            const formFields: OAuth2ChannelField[] = (channel.formFields ?? []).map((field) => {
                return {
                    ...field,
                    config: this.translatedConfig(field.config),
                };
            });

            const values: Record<string, unknown> = {};
            formFields.forEach((field) => {
                const stored = this.storedValue(field, client);
                values[field.name] = stored === undefined ? this.defaultValue(field) : stored;
            });

            return {
                technicalName: channel.technicalName,
                label: this.$te(channel.labelSnippet) ? this.$t(channel.labelSnippet) : channel.technicalName,
                formFields,
                id: client ? client.id : null,
                enabled: client ? client.enabled : false,
                values,
            };
        },

        /**
         * The fields the platform adds are labelled with a snippet key the page translates, a channel
         * labels its own fields with an inline snippet right where it declares them - which is what
         * lets an extension ship a channel without a snippet file of ours.
         */
        translatedConfig(config?: OAuth2ChannelFieldConfig): OAuth2ChannelFieldConfig {
            return {
                ...config,
                label: this.resolveLabel(config?.label),
                helpText: this.resolveLabel(config?.helpText),
            };
        },

        resolveLabel(label?: OAuth2ChannelFieldConfig['label']): OAuth2ChannelFieldConfig['label'] {
            if (typeof label !== 'string' || label === '') {
                return label;
            }

            return this.$te(label) ? this.$t(label) : label;
        },

        setFieldValue(channel: ChannelState, field: OAuth2ChannelField, value: unknown): void {
            channel.values[field.name] = value;
        },

        /**
         * The value the shop stored for a field, or undefined when it never set one.
         */
        storedValue(field: OAuth2ChannelField, client: OAuth2Client | null): unknown {
            if (client === null) {
                return undefined;
            }

            const stored =
                field.target === 'settings'
                    ? (client.settings as Record<string, unknown> | null | undefined)?.[field.name]
                    : // The column a field lands in is its target, the field name is the name of that column.
                      (client as unknown as Record<string, unknown>)[field.name];

            return stored === null ? undefined : stored;
        },

        /**
         * What a field holds before the admin touches it: the default the channel declares, or the
         * empty value of its type.
         */
        defaultValue(field: OAuth2ChannelField): unknown {
            if (field.config?.default !== undefined) {
                return field.config.default;
            }

            switch (field.type) {
                case 'multi-select':
                    return [];
                case 'bool':
                case 'switch':
                case 'checkbox':
                    return false;
                default:
                    return '';
            }
        },

        /**
         * Every field is present in `values` from the moment the channel is loaded: the page writes
         * into it as the admin types, and a key that does not exist yet is not tracked.
         */
        isSameValue(left: unknown, right: unknown): boolean {
            if (Array.isArray(left) && Array.isArray(right)) {
                return left.length === right.length && left.every((value, index) => value === right[index]);
            }

            return left === right;
        },

        isEmptyValue(value: unknown): boolean {
            if (value === null || value === undefined || value === '') {
                return true;
            }

            if (Array.isArray(value)) {
                return value.length === 0;
            }

            return false;
        },

        /**
         * Whether a required field of the channel is still empty. What a channel needs is declared on
         * the server, so this only points the admin at the field - the check that counts runs again
         * when the login is built.
         */
        isChannelInvalid(channel: ChannelState): boolean {
            if (!channel.enabled) {
                return false;
            }

            return channel.formFields.some((field) => {
                return field.config?.required === true && this.isEmptyValue(channel.values[field.name]);
            });
        },

        /**
         * A channel without a row and with every field on its default was never touched, it is not
         * worth storing an empty configuration for.
         */
        isChannelConfigured(channel: ChannelState): boolean {
            if (channel.id !== null || channel.enabled) {
                return true;
            }

            return channel.formFields.some((field) => {
                return !this.isSameValue(channel.values[field.name], this.defaultValue(field));
            });
        },

        async onSave(): Promise<void> {
            const invalid = this.channels.find((channel) => this.isChannelInvalid(channel));

            if (invalid) {
                this.activeChannel = invalid.technicalName;

                this.createNotificationError({
                    title: this.$t('global.default.error'),
                    message: this.$t('sw-settings-oauth2-login.notification.missingFields', { channel: invalid.label }),
                });

                return;
            }

            this.isSaving = true;

            try {
                const configured = this.channels.filter((channel) => this.isChannelConfigured(channel));

                await Promise.all(configured.map((channel) => this.saveChannel(channel)));

                this.createNotificationSuccess({
                    title: this.$t('global.default.success'),
                    message: this.$t('sw-settings-oauth2-login.notification.saveSuccess'),
                });

                await this.loadChannels();
            } catch (error) {
                this.createNotificationError({
                    title: this.$t('global.default.error'),
                    message: error instanceof Error ? error.message : '',
                });
            } finally {
                this.isSaving = false;
            }
        },

        async saveChannel(channel: ChannelState): Promise<void> {
            const client = channel.id ? await this.clientRepository.get(channel.id) : this.clientRepository.create();

            if (client === null) {
                return;
            }

            client.technicalName = channel.technicalName;
            client.enabled = channel.enabled;
            client.settings = this.collectSettings(channel);

            // The columns are filled by the target of a field, not by its name: which fields exist is
            // decided by the channel, the page only knows the entity.
            Object.assign(client, this.collectColumns(channel));

            await this.clientRepository.save(client);
        },

        /**
         * The columns of the row, in the shape the entity expects. A column no field of the channel
         * maps to is written as null, so a channel that dropped a field in a newer version does not
         * keep the value that is still stored.
         */
        collectColumns(channel: ChannelState): Record<string, unknown> {
            const columns: Record<string, unknown> = {
                clientId: null,
                clientSecret: null,
                scopes: null,
            };

            channel.formFields
                .filter((field) => field.target !== 'settings')
                .forEach((field) => {
                    columns[field.target] = this.nullIfEmpty(channel.values[field.name]);
                });

            return columns;
        },

        /**
         * Only the fields the channel declares are stored. The settings column is a free json blob, a
         * channel that dropped a parameter in a newer version would otherwise keep the value around
         * forever and the server would keep it out of the login anyway.
         */
        collectSettings(channel: ChannelState): Record<string, unknown> | null {
            const settings: Record<string, unknown> = {};

            channel.formFields
                .filter((field) => field.target === 'settings')
                .forEach((field) => {
                    settings[field.name] = channel.values[field.name];
                });

            return Object.keys(settings).length === 0 ? null : settings;
        },

        nullIfEmpty(value: unknown): unknown {
            return this.isEmptyValue(value) ? null : value;
        },

        onOpenRemoveModal(channel: ChannelState): void {
            this.removeTarget = channel;
        },

        /**
         * The modal closes itself, it only tells the page that there is no target left to remove.
         */
        onRemoveModalChange(isOpen: boolean): void {
            if (!isOpen) {
                this.onCloseRemoveModal();
            }
        },

        onCloseRemoveModal(): void {
            this.removeTarget = null;
        },

        async onConfirmRemove(): Promise<void> {
            const target = this.removeTarget;

            if (!target || !target.id) {
                this.onCloseRemoveModal();

                return;
            }

            this.isSaving = true;

            try {
                await this.clientRepository.delete(target.id);
                this.onCloseRemoveModal();

                this.createNotificationSuccess({
                    title: this.$t('global.default.success'),
                    message: this.$t('sw-settings-oauth2-login.notification.removeSuccess'),
                });

                await this.loadChannels();
            } catch (error) {
                this.createNotificationError({
                    title: this.$t('global.default.error'),
                    message: error instanceof Error ? error.message : '',
                });
            } finally {
                this.isSaving = false;
            }
        },
    },
});
