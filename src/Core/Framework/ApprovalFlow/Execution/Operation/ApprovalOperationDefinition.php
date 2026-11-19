<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Operation;

use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceDefinition;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceStatus;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\EnumField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\AllowEmptyString;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceServiceTest
 */
#[Package('framework')]
class ApprovalOperationDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'approval_flow_operation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ApprovalOperationEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ApprovalOperationCollection::class;
    }

    protected function defaultFields(): array
    {
        return [new CreatedAtField()];
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('instance_id', 'instanceId', ApprovalInstanceDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            (new IdField('command_id', 'commandId'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('operation', 'operation', 64))->addFlags(new Required()),
            (new StringField('request_hash', 'requestHash', 64))->addFlags(new Required()),
            (new LongTextField('actor_json', 'actorJson'))->addFlags(new Required(), new AllowHtml(sanitized: false)),
            (new LongTextField('reason', 'reason'))->addFlags(new Required(), new AllowEmptyString(), new AllowHtml(sanitized: false)),
            (new EnumField('previous_status', 'previousStatus', ApprovalInstanceStatus::Draft))->addFlags(new Required()),
            (new EnumField('status', 'status', ApprovalInstanceStatus::Draft))->addFlags(new Required()),
            (new IntField('previous_version', 'previousVersion', 1))->addFlags(new Required()),
            (new IntField('aggregate_version', 'aggregateVersion', 1))->addFlags(new Required()),
            new ManyToOneAssociationField('instance', 'instance_id', ApprovalInstanceDefinition::class, 'id', false),
        ]);
    }
}
