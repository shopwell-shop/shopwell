<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App;

use Shopwell\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopwell\Core\Framework\App\Aggregate\ActionButton\ActionButtonDefinition;
use Shopwell\Core\Framework\App\Aggregate\AppMcpPrompt\AppMcpPromptDefinition;
use Shopwell\Core\Framework\App\Aggregate\AppMcpResource\AppMcpResourceDefinition;
use Shopwell\Core\Framework\App\Aggregate\AppMcpTool\AppMcpToolDefinition;
use Shopwell\Core\Framework\App\Aggregate\AppPaymentMethod\AppPaymentMethodDefinition;
use Shopwell\Core\Framework\App\Aggregate\AppScriptCondition\AppScriptConditionDefinition;
use Shopwell\Core\Framework\App\Aggregate\AppShippingMethod\AppShippingMethodDefinition;
use Shopwell\Core\Framework\App\Aggregate\AppTranslation\AppTranslationDefinition;
use Shopwell\Core\Framework\App\Aggregate\CmsBlock\AppCmsBlockDefinition;
use Shopwell\Core\Framework\App\Aggregate\FlowAction\AppFlowActionDefinition;
use Shopwell\Core\Framework\App\Aggregate\FlowEvent\AppFlowEventDefinition;
use Shopwell\Core\Framework\App\Template\TemplateDefinition;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\BlobField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Since;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Script\ScriptDefinition;
use Shopwell\Core\Framework\Webhook\WebhookDefinition;
use Shopwell\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetDefinition;
use Shopwell\Core\System\Integration\IntegrationDefinition;
use Shopwell\Core\System\TaxProvider\TaxProviderDefinition;

/**
 * @internal
 *
 * @phpstan-type SourceConfig array<string, mixed>
 */
#[Package('framework')]
class AppDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'app';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return AppEntity::class;
    }

    public function getCollectionClass(): string
    {
        return AppCollection::class;
    }

    public function getDefaults(): array
    {
        return [
            'active' => false,
            'configurable' => false,
            'allowDisable' => true,
            'modules' => [],
            'cookies' => [],
            'allowedHosts' => [],
            'templateLoadPriority' => 0,
            'sourceType' => 'local',
            'selfManaged' => false,
            'requestedPrivileges' => [],
        ];
    }

    public function since(): ?string
    {
        return '6.3.1.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of App.'),
            (new StringField('name', 'name'))->addFlags(new Required())->setDescription('Name of the app.'),
            (new StringField('path', 'path', 4096))->addFlags(new Required())->setDescription('A relative URL to the app.'),
            (new StringField('author', 'author'))->setDescription('Creator of the App.'),
            (new StringField('copyright', 'copyright'))->setDescription('Legal rights on the created app.'),
            (new StringField('license', 'license'))->setDescription('Software license\'s like MIT, etc.'),
            (new BoolField('active', 'active'))->addFlags(new Required())->setDescription('When boolean value is `true`, the app is enabled for selection.'),
            (new BoolField('configurable', 'configurable'))->addFlags(new Required())->setDescription('When boolean value is `true`, the app is configurable for further customizations.'),
            (new StringField('privacy', 'privacy'))->setDescription('Privacy-related configuration properties like user data protection, consent mechanisms, or data privacy compliance for an app.'),
            (new StringField('version', 'version'))->addFlags(new Required())->setDescription('Version of the plugin.'),
            (new BlobField('icon', 'iconRaw'))->removeFlag(ApiAware::class),
            (new StringField('icon', 'icon'))->addFlags(new WriteProtected(), new Runtime())->setDescription('Icon for the app.'),
            (new StringField('app_secret', 'appSecret'))->removeFlag(ApiAware::class)->addFlags(new WriteProtected(Context::SYSTEM_SCOPE)),
            (new ListField('unconfirmed_app_secrets', 'unconfirmedAppSecrets', StringField::class))->removeFlag(ApiAware::class)->addFlags(new WriteProtected(Context::SYSTEM_SCOPE)),
            (new ListField('modules', 'modules', JsonField::class))->setDescription('Configuration properties or settings related to modules of an app.'),
            (new JsonField('main_module', 'mainModule'))->setDescription('Configuration properties or settings related to main modules of an app.'),
            (new ListField('cookies', 'cookies', JsonField::class))->setDescription('Configuration properties or settings related to cookies of an app.'),
            (new BoolField('allow_disable', 'allowDisable'))->addFlags(new Required())->setDescription('When boolean value is `true`, then the users have the option to deactivate specific aspects of the app.'),
            (new StringField('base_app_url', 'baseAppUrl', 1024))->setDescription('Root URL for an app.'),
            (new ListField('allowed_hosts', 'allowedHosts', StringField::class))->setDescription('Indicates the allowed or permitted hosts that the application can communicate with or accept requests from.'),
            (new IntField('template_load_priority', 'templateLoadPriority'))->setDescription('A numerical value to prioritize one of the templates from the list.'),
            new StringField('checkout_gateway_url', 'checkoutGatewayUrl'),
            new StringField('context_gateway_url', 'contextGatewayUrl'),
            new StringField('in_app_purchases_gateway_url', 'inAppPurchasesGatewayUrl'),
            new StringField('source_type', 'sourceType'),
            new JsonField('source_config', 'sourceConfig'),
            new BoolField('self_managed', 'selfManaged'),
            (new ListField('requested_privileges', 'requestedPrivileges', StringField::class))->addFlags(new Required()),

            (new TranslationsAssociationField(AppTranslationDefinition::class, 'app_id'))->addFlags(new Required(), new CascadeDelete()),
            new TranslatedField('label'),
            new TranslatedField('description'),
            new TranslatedField('privacyPolicyExtensions'),
            (new TranslatedField('customFields'))->addFlags(new Since('6.4.1.0')),

            (new FkField('integration_id', 'integrationId', IntegrationDefinition::class))->addFlags(new Required())->setDescription('Unique identity of integration.'),
            new OneToOneAssociationField('integration', 'integration_id', 'id', IntegrationDefinition::class),

            (new FkField('acl_role_id', 'aclRoleId', AclRoleDefinition::class))->addFlags(new Required())->setDescription('Unique identity of ACL Role.'),
            new OneToOneAssociationField('aclRole', 'acl_role_id', 'id', AclRoleDefinition::class),

            (new OneToManyAssociationField('customFieldSets', CustomFieldSetDefinition::class, 'app_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('actionButtons', ActionButtonDefinition::class, 'app_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('templates', TemplateDefinition::class, 'app_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('scripts', ScriptDefinition::class, 'app_id'))->addFlags(new CascadeDelete())->removeFlag(ApiAware::class),
            (new OneToManyAssociationField('webhooks', WebhookDefinition::class, 'app_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('paymentMethods', AppPaymentMethodDefinition::class, 'app_id'))->addFlags(new SetNullOnDelete()),
            (new OneToManyAssociationField('taxProviders', TaxProviderDefinition::class, 'app_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('scriptConditions', AppScriptConditionDefinition::class, 'app_id'))->addFlags(new CascadeDelete())->removeFlag(ApiAware::class),
            (new OneToManyAssociationField('cmsBlocks', AppCmsBlockDefinition::class, 'app_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('flowActions', AppFlowActionDefinition::class, 'app_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('flowEvents', AppFlowEventDefinition::class, 'app_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('appShippingMethods', AppShippingMethodDefinition::class, 'app_id'))->addFlags(new SetNullOnDelete()),
            (new OneToManyAssociationField('mcpTools', AppMcpToolDefinition::class, 'app_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('mcpPrompts', AppMcpPromptDefinition::class, 'app_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('mcpResources', AppMcpResourceDefinition::class, 'app_id'))->addFlags(new CascadeDelete()),
        ]);
    }
}
