/**
 * @sw-package framework
 */
import type { SmsGatewayProvider } from '../../service/sms-gateway-provider.service';
import type RepositoryType from 'src/core/data/repository.data';
import template from './sw-settings-sms-template-detail.html.twig';

const { Mixin } = Shopwell;
const { Criteria } = Shopwell.Data;

const SCENES = ['otp', 'transaction', 'marketing'];

/**
 * A logical template: the parameter contract, the bodies per language and where it goes at the
 * providers.
 *
 * The body is edited in the language the administration is switched to, like the one of a mail
 * template: a language without a body of its own inherits the one of the default language, which the
 * renderer falls back to.
 *
 * @sw-package framework
 * @private
 */
export default Shopwell.Component.wrapComponentConfig({
    name: 'sw-settings-sms-template-detail',

    template,

    inject: ['repositoryFactory', 'acl', 'smsGatewayProviderService'],

    mixins: [Mixin.getByName('notification')],

    props: {
        templateId: {
            // the route hands the id over, so the type of the key is already known here
            type: String as unknown as PropType<EntityKey<'sms_template'> | null>,
            required: false,
            default: null,
        },
    },

    data(): {
        template: EntitySchema.Entity<'sms_template'> | null;
        providers: SmsGatewayProvider[];
        gateways: Array<EntitySchema.Entity<'sms_gateway'>>;
        paramsSchemaText: string;
        isLoading: boolean;
        isSaving: boolean;
    } {
        return {
            template: null,
            providers: [],
            gateways: [],
            paramsSchemaText: '',
            isLoading: true,
            isSaving: false,
        };
    },

    computed: {
        isNew(): boolean {
            return this.templateId === null;
        },

        templateRepository(): RepositoryType<'sms_template'> {
            return this.repositoryFactory.create('sms_template');
        },

        gatewayRepository(): RepositoryType<'sms_gateway'> {
            return this.repositoryFactory.create('sms_gateway');
        },

        title(): string {
            return this.isNew
                ? this.$t('sw-settings-sms.template.detailTitleCreate')
                : this.$t('sw-settings-sms.template.detailTitleEdit');
        },

        sceneOptions(): Array<{ value: string; label: string }> {
            return SCENES.map((scene) => {
                return {
                    value: scene,
                    label: this.$t(`sw-settings-sms.template.scene.${scene}`),
                };
            });
        },
    },

    created() {
        void this.createdComponent();
    },

    methods: {
        async createdComponent(): Promise<void> {
            this.isLoading = true;

            try {
                await Promise.all([this.loadProviders(), this.loadGateways()]);

                if (this.templateId === null) {
                    const newTemplate = this.templateRepository.create();
                    newTemplate.scene = 'otp';
                    newTemplate.enabled = true;

                    this.template = newTemplate;
                } else {
                    await this.loadTemplate();
                }

                this.paramsSchemaText = this.template?.paramsSchema
                    ? JSON.stringify(this.template.paramsSchema, null, 2)
                    : '';
            } catch (error) {
                this.createNotificationError({
                    message: (error as Error).message,
                });
            } finally {
                this.isLoading = false;
            }
        },

        async loadProviders(): Promise<void> {
            this.providers = await this.smsGatewayProviderService.list();
        },

        async loadGateways(): Promise<void> {
            const result = await this.gatewayRepository.search(new Criteria(1, 100));

            this.gateways = result
                .getIds()
                .map((id) => result.get(id))
                .filter(
                    (gateway): gateway is EntitySchema.Entity<'sms_gateway'> => gateway !== undefined && gateway !== null,
                );
        },

        /**
         * The mappings come with the template, they are saved with it in one write.
         */
        async loadTemplate(): Promise<void> {
            if (this.templateId === null) {
                return;
            }

            const criteria = new Criteria(1, 1);
            criteria.addFilter(Criteria.equals('id', this.templateId));
            criteria.addAssociation('translations');
            criteria.addAssociation('mappings');

            const result = await this.templateRepository.search(criteria);

            this.template = result.first() ?? null;
        },

        /**
         * The language the administration is switched to decides which body is shown, the page reads
         * the template again so the values of that language are the ones on screen.
         */
        async onLanguageChange(): Promise<void> {
            if (this.isNew) {
                return;
            }

            await this.loadTemplate();
        },

        collectParamsSchema(): Array<Record<string, unknown>> | null {
            if (this.paramsSchemaText.trim() === '') {
                return null;
            }

            return JSON.parse(this.paramsSchemaText) as Array<Record<string, unknown>>;
        },

        async onSave(): Promise<void> {
            if (this.template === null) {
                return;
            }

            let paramsSchema: Array<Record<string, unknown>> | null = null;

            try {
                paramsSchema = this.collectParamsSchema();
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-settings-sms.template.labelParamsSchemaHelpText'),
                });

                return;
            }

            this.isSaving = true;

            try {
                this.template.paramsSchema = paramsSchema;

                await this.templateRepository.save(this.template);

                this.createNotificationSuccess({
                    message: this.$t('sw-settings-sms.notification.saveSuccess'),
                });

                if (this.isNew) {
                    void this.$router.push({
                        name: 'sw.settings.sms.template.detail',
                        params: { id: this.template?.id ?? '' },
                    });

                    return;
                }

                await this.loadTemplate();
            } catch (error) {
                this.createNotificationError({
                    message: (error as Error).message,
                });
            } finally {
                this.isSaving = false;
            }
        },
    },
});
