<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Api\Acl\Role;

use Shopwell\Core\Framework\App\AppDefinition;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtectionCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityProtection\WriteProtection;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Integration\Aggregate\IntegrationRole\IntegrationRoleDefinition;
use Shopwell\Core\System\Integration\IntegrationDefinition;
use Shopwell\Core\System\User\UserDefinition;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class AclRoleDefinition extends EntityDefinition
{
    final public const PRIVILEGE_READ = 'read';
    final public const PRIVILEGE_CREATE = 'create';
    final public const PRIVILEGE_UPDATE = 'update';
    final public const PRIVILEGE_DELETE = 'delete';

    final public const PRIVILEGE_DEPENDENCE = [
        AclRoleDefinition::PRIVILEGE_READ => [],
        AclRoleDefinition::PRIVILEGE_CREATE => [AclRoleDefinition::PRIVILEGE_READ],
        AclRoleDefinition::PRIVILEGE_UPDATE => [AclRoleDefinition::PRIVILEGE_READ],
        AclRoleDefinition::PRIVILEGE_DELETE => [AclRoleDefinition::PRIVILEGE_READ],
    ];

    final public const ENTITY_NAME = 'acl_role';
    final public const ALL_ROLE_KEY = 'all';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return AclRoleCollection::class;
    }

    public function getEntityClass(): string
    {
        return AclRoleEntity::class;
    }

    public function getDefaults(): array
    {
        return ['privileges' => []];
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineProtections(): EntityProtectionCollection
    {
        return new EntityProtectionCollection([
            // The generic CRUD API must not create or update ACL roles.
            new WriteProtection(Context::SYSTEM_SCOPE, Context::USER_SCOPE),
        ]);
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of ACL role.'),
            (new StringField('name', 'name'))->addFlags(new Required())->setDescription('Name of the ACL role defined.'),
            (new LongTextField('description', 'description'))->setDescription('A short description of the ACL role.'),
            (new ListField('privileges', 'privileges', StringField::class))->addFlags(new Required())->setDescription('Privileges like read, write, delete, etc.'),
            (new DateTimeField('deleted_at', 'deletedAt'))->setDescription('Time and date when the ACL role was deleted.'),
            new ManyToManyAssociationField('users', UserDefinition::class, AclUserRoleDefinition::class, 'acl_role_id', 'user_id'),
            (new OneToOneAssociationField('app', 'id', 'acl_role_id', AppDefinition::class, false))->addFlags(new RestrictDelete()),
            new ManyToManyAssociationField('integrations', IntegrationDefinition::class, IntegrationRoleDefinition::class, 'acl_role_id', 'integration_id'),
        ]);
    }
}
