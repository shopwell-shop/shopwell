<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Task;

use Shopwell\Core\Framework\ApprovalFlow\Execution\Node\ApprovalNodeExecutionDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\EnumField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalNodeProgressionTest
 */
#[Package('framework')]
class ApprovalTaskDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'approval_flow_task';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ApprovalTaskEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ApprovalTaskCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('execution_id', 'executionId', ApprovalNodeExecutionDefinition::class))->addFlags(new Required()),
            (new IdField('assignee_id', 'assigneeId'))->addFlags(new Required()),
            (new EnumField('status', 'status', ApprovalTaskStatus::Pending))->addFlags(new Required()),
            new FkField('replaces_task_id', 'replacesTaskId', self::class),
            new IdField('completion_command_id', 'completionCommandId'),
            new StringField('completion_reason', 'completionReason'),
            new ManyToOneAssociationField('execution', 'execution_id', ApprovalNodeExecutionDefinition::class, 'id', false),
        ]);
    }
}
