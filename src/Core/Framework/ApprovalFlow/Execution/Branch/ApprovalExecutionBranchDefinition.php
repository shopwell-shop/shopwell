<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Branch;

use Shopwell\Core\Framework\ApprovalFlow\Execution\Scope\ApprovalExecutionScopeDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\EnumField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalParallelExecutionTest
 */
#[Package('framework')]
class ApprovalExecutionBranchDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'approval_flow_execution_branch';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ApprovalExecutionBranchEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ApprovalExecutionBranchCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('scope_id', 'scopeId', ApprovalExecutionScopeDefinition::class))->addFlags(new Required()),
            (new StringField('connection_hash', 'connectionHash', 64))->addFlags(new Required()),
            (new LongTextField('connection_id', 'connectionId'))->addFlags(new Required(), new AllowHtml(sanitized: false)),
            (new LongTextField('arrival_connection_id', 'arrivalConnectionId'))->addFlags(new AllowHtml(sanitized: false)),
            (new EnumField('status', 'status', ApprovalExecutionBranchStatus::Pending))->addFlags(new Required()),
            new ManyToOneAssociationField('scope', 'scope_id', ApprovalExecutionScopeDefinition::class, 'id', false),
        ]);
    }
}
