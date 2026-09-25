<?php declare(strict_types=1);

namespace Shopwell\Core\System\Integration;

use Shopwell\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopwell\Core\Framework\App\AppDefinition;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityProtection\CloneProtection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtectionCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\PasswordField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Integration\Aggregate\IntegrationRole\IntegrationRoleDefinition;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryDefinition;

/**
 * @codeCoverageIgnore
 */
#[Package('fundamentals@framework')]
class IntegrationDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'integration';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return IntegrationCollection::class;
    }

    public function getEntityClass(): string
    {
        return IntegrationEntity::class;
    }

    public function getDefaults(): array
    {
        return [
            'admin' => false,
        ];
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineProtections(): EntityProtectionCollection
    {
        return new EntityProtectionCollection([new CloneProtection(Context::SYSTEM_SCOPE)]);
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of Integration.'),
            (new StringField('label', 'label'))->addFlags(new Required())->setDescription('Label given to Integration.'),
            (new StringField('access_key', 'accessKey'))->addFlags(new Required())->setDescription('Access key to store api.'),
            (new PasswordField('secret_access_key', 'secretAccessKey'))->addFlags(new Required())->setDescription('Secret key required for secure communication.'),
            (new DateTimeField('last_usage_at', 'lastUsageAt'))->setDescription('Date and time when teh integration was last used.'),
            // The regular Admin API CRUD endpoint authorizes elevated admin changes in the controller; direct DAL writes remain write-protected.
            // @see \Shopwell\Core\Framework\Api\Controller\IntegrationController
            (new BoolField('admin', 'admin'))->addFlags((new WriteProtected(Context::SYSTEM_SCOPE))->allowWriteThroughAdminApi())->setDescription('When boolean value is `true`, it indicates this is a administrative integration that requires elevated permissions.'),
            (new JsonField('mcp_allowlist', 'mcpAllowlist'))->setDescription('Optional per-type MCP allowlist for this integration. Structured as {tools, resources, prompts} where each key is null (unrestricted) or a list of allowed names/URIs. When null all capabilities are accessible.'),
            (new CustomFields())->setDescription('Additional fields that offer a possibility to add own fields for the different program-areas.'),
            (new DateTimeField('deleted_at', 'deletedAt'))->setDescription('Date and time when the integration was deleted.'),

            (new OneToOneAssociationField('app', 'id', 'integration_id', AppDefinition::class, false))->addFlags(new RestrictDelete()),
            new OneToManyAssociationField('stateMachineHistoryEntries', StateMachineHistoryDefinition::class, 'integration_id', 'id'),
            new ManyToManyAssociationField('aclRoles', AclRoleDefinition::class, IntegrationRoleDefinition::class, 'integration_id', 'acl_role_id'),
        ]);
    }
}
