<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Release;

use Shopwell\Core\Framework\ApprovalFlow\Definition\ApprovalFlowDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\CreatedByField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
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
use Shopwell\Core\System\User\UserDefinition;

/**
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Release\ApprovalReleaseServiceTest
 */
#[Package('framework')]
class ApprovalReleaseDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'approval_flow_release';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ApprovalReleaseEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ApprovalReleaseCollection::class;
    }

    protected function defaultFields(): array
    {
        return [new CreatedAtField()];
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('definition_id', 'definitionId', ApprovalFlowDefinition::class))->addFlags(new Required()),
            (new IntField('draft_revision', 'draftRevision', 1))->addFlags(new Required()),
            (new LongTextField('graph_json', 'graphJson'))->addFlags(new Required(), new AllowHtml(sanitized: false)),
            (new LongTextField('contract_json', 'contractJson'))->addFlags(new Required(), new AllowHtml(sanitized: false)),
            (new LongTextField('graph_plan_json', 'graphPlanJson'))->addFlags(new Required(), new AllowHtml(sanitized: false)),
            (new LongTextField('node_plan_json', 'nodePlanJson'))->addFlags(new Required(), new AllowHtml(sanitized: false)),
            (new LongTextField('dependencies_json', 'dependenciesJson'))->addFlags(new Required(), new AllowHtml(sanitized: false)),
            (new StringField('content_hash', 'contentHash', 64))->addFlags(new Required()),
            new CreatedByField([ApprovalReleaseWriteGuard::WRITE_SCOPE]),
            new ManyToOneAssociationField('definition', 'definition_id', ApprovalFlowDefinition::class, 'id', false),
            new ManyToOneAssociationField('createdBy', 'created_by_id', UserDefinition::class, 'id', false),
        ]);
    }
}
