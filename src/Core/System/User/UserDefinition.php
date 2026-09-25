<?php declare(strict_types=1);

namespace Shopwell\Core\System\User;

use Shopwell\Core\Checkout\Customer\CustomerDefinition;
use Shopwell\Core\Checkout\Order\OrderDefinition;
use Shopwell\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogDefinition;
use Shopwell\Core\Content\Media\MediaDefinition;
use Shopwell\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopwell\Core\Framework\Api\Acl\Role\AclUserRoleDefinition;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityProtection\CloneProtection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtectionCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityProtection\WriteProtection;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\EmailField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\PasswordField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TimeZoneField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Locale\LocaleDefinition;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryDefinition;
use Shopwell\Core\System\User\Aggregate\UserAccessKey\UserAccessKeyDefinition;
use Shopwell\Core\System\User\Aggregate\UserConfig\UserConfigDefinition;
use Shopwell\Core\System\User\Aggregate\UserRecovery\UserRecoveryDefinition;

/**
 * @codeCoverageIgnore
 */
#[Package('fundamentals@framework')]
class UserDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'user';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return UserCollection::class;
    }

    public function getEntityClass(): string
    {
        return UserEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    public function getDefaults(): array
    {
        return [
            'admin' => false,
            'timeZone' => 'UTC',
        ];
    }

    protected function defineProtections(): EntityProtectionCollection
    {
        return new EntityProtectionCollection([
            new CloneProtection(Context::SYSTEM_SCOPE),
            new WriteProtection(Context::SYSTEM_SCOPE, Context::USER_SCOPE),
        ]);
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of the user.'),
            (new FkField('locale_id', 'localeId', LocaleDefinition::class))->addFlags(new Required())->setDescription('Unique identity of locale.'),
            (new StringField('username', 'username'))->addFlags(new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING))->setDescription('Username of the user.'),
            (new PasswordField('password', 'password', \PASSWORD_DEFAULT, [], PasswordField::FOR_ADMIN))->removeFlag(ApiAware::class)->addFlags(new Required()),
            (new StringField('first_name', 'firstName'))->addFlags(new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING))->setDescription('First name of the user.'),
            (new StringField('last_name', 'lastName'))->addFlags(new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING))->setDescription('Last name of the user.'),
            (new StringField('title', 'title'))->addFlags(new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING))->setDescription('Title of the user.'),
            (new EmailField('email', 'email'))->addFlags(new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING))->setDescription('Email of the user.'),
            (new BoolField('active', 'active'))->setDescription('When boolean value is `true`, the user is enabled.'),
            // The regular Admin API CRUD endpoint authorizes elevated admin changes in the controller; direct DAL writes remain write-protected.
            // @see \Shopwell\Core\Framework\Api\Controller\UserController
            (new BoolField('admin', 'admin'))->addFlags((new WriteProtected(Context::SYSTEM_SCOPE))->allowWriteThroughAdminApi())->setDescription('Parameter that indicates if the user is an admin.'),
            (new JsonField('mcp_allowlist', 'mcpAllowlist'))->setDescription(
                'Optional per-type MCP allowlist for this user. Structured as {tools, resources, prompts} '
                . 'where each key is null (unrestricted) or a list of allowed names/URIs.'
            ),
            (new DateTimeField('last_updated_password_at', 'lastUpdatedPasswordAt'))->setDescription('Parameter that indicates when the password was last updated by the user.'),
            (new TimeZoneField('time_zone', 'timeZone'))->addFlags(new Required())->setDescription('Time configuration in the user\'s profile.'),
            (new CustomFields())->setDescription('Additional fields that offer a possibility to add own fields for the different program-areas.'),
            new ManyToOneAssociationField('locale', 'locale_id', LocaleDefinition::class, 'id', false),
            (new FkField('avatar_id', 'avatarId', MediaDefinition::class))->setDescription('Unique identity of the avatar.'),
            new ManyToOneAssociationField('avatarMedia', 'avatar_id', MediaDefinition::class),
            (new OneToManyAssociationField('media', MediaDefinition::class, 'user_id'))->addFlags(new SetNullOnDelete()),
            (new OneToManyAssociationField('accessKeys', UserAccessKeyDefinition::class, 'user_id', 'id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('configs', UserConfigDefinition::class, 'user_id', 'id'))->addFlags(new CascadeDelete()),
            new OneToManyAssociationField('stateMachineHistoryEntries', StateMachineHistoryDefinition::class, 'user_id', 'id'),
            (new OneToManyAssociationField('importExportLogEntries', ImportExportLogDefinition::class, 'user_id', 'id'))->addFlags(new SetNullOnDelete()),
            new ManyToManyAssociationField('aclRoles', AclRoleDefinition::class, AclUserRoleDefinition::class, 'user_id', 'acl_role_id'),
            new OneToOneAssociationField('recoveryUser', 'id', 'user_id', UserRecoveryDefinition::class, false),
            (new StringField('store_token', 'storeToken'))->removeFlag(ApiAware::class),
            new OneToManyAssociationField('createdOrders', OrderDefinition::class, 'created_by_id', 'id'),
            new OneToManyAssociationField('updatedOrders', OrderDefinition::class, 'updated_by_id', 'id'),
            new OneToManyAssociationField('createdCustomers', CustomerDefinition::class, 'created_by_id', 'id'),
            new OneToManyAssociationField('updatedCustomers', CustomerDefinition::class, 'updated_by_id', 'id'),
        ]);
    }
}
