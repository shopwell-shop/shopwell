<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Definition;

use Shopwell\Core\Framework\ApprovalFlow\Draft\ApprovalDraftDefinition;
use Shopwell\Core\Framework\ApprovalFlow\Draft\ApprovalDraftWriteGuard;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\CreatedByField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\User\UserDefinition;

/**
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Draft\ApprovalDraftServiceTest
 */
#[Package('framework')]
class ApprovalFlowDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'approval_flow_definition';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ApprovalFlowEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ApprovalFlowCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->addFlags(new Required()),
            new CreatedByField([ApprovalDraftWriteGuard::WRITE_SCOPE]),
            new ManyToOneAssociationField('createdBy', 'created_by_id', UserDefinition::class, 'id', false),
            new OneToOneAssociationField('draft', 'id', 'definition_id', ApprovalDraftDefinition::class, false),
        ]);
    }
}
