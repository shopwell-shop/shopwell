<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Node;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Branch\ApprovalExecutionBranchDefinition;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceDefinition;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Scope\ApprovalExecutionScopeDefinition;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Task\ApprovalTaskDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\EnumField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalNodeProgressionTest
 */
#[Package('framework')]
class ApprovalNodeExecutionDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'approval_flow_execution';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ApprovalNodeExecutionEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ApprovalNodeExecutionCollection::class;
    }

    public function getDefaults(): array
    {
        return ['generation' => 1, 'advanced' => false];
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('instance_id', 'instanceId', ApprovalInstanceDefinition::class))->addFlags(new Required()),
            (new LongTextField('node_id', 'nodeId'))->addFlags(new Required(), new AllowHtml(sanitized: false)),
            (new LongTextField('incoming_connection_id', 'incomingConnectionId'))->addFlags(new AllowHtml(sanitized: false)),
            (new IntField('sequence', 'sequence', 1))->addFlags(new Required()),
            (new EnumField('status', 'status', ApprovalNodeExecutionStatus::Waiting))->addFlags(new Required()),
            new FkField('scope_id', 'scopeId', ApprovalExecutionScopeDefinition::class),
            new FkField('branch_id', 'branchId', ApprovalExecutionBranchDefinition::class),
            new FkField('opened_scope_id', 'openedScopeId', ApprovalExecutionScopeDefinition::class),
            new FkField('waiting_scope_id', 'waitingScopeId', ApprovalExecutionScopeDefinition::class),
            (new IntField('generation', 'generation', 1))->addFlags(new Required()),
            (new BoolField('advanced', 'advanced'))->addFlags(new Required()),
            new ManyToOneAssociationField('scope', 'scope_id', ApprovalExecutionScopeDefinition::class, 'id', false),
            new ManyToOneAssociationField('branch', 'branch_id', ApprovalExecutionBranchDefinition::class, 'id', false),
            new ManyToOneAssociationField('openedScope', 'opened_scope_id', ApprovalExecutionScopeDefinition::class, 'id', false),
            new ManyToOneAssociationField('waitingScope', 'waiting_scope_id', ApprovalExecutionScopeDefinition::class, 'id', false),
            new StringField('approval_policy', 'approvalPolicy'),
            new StringField('approval_policy_version', 'approvalPolicyVersion'),
            new EnumField('outcome', 'outcome', ApprovalOutcome::Approved),
            new ManyToOneAssociationField('instance', 'instance_id', ApprovalInstanceDefinition::class, 'id', false),
            new OneToManyAssociationField('tasks', ApprovalTaskDefinition::class, 'execution_id'),
        ]);
    }
}
