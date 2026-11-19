<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Draft;

use Shopwell\Core\Framework\ApprovalFlow\Definition\ApprovalFlowDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\UpdatedByField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\User\UserDefinition;

/**
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Draft\ApprovalDraftServiceTest
 */
#[Package('framework')]
class ApprovalDraftDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'approval_flow_draft';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ApprovalDraftEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ApprovalDraftCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('definition_id', 'definitionId', ApprovalFlowDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            (new IntField('revision', 'revision', 1))->addFlags(new Required()),
            // GraphCodec admits the JSON. The text serializer must preserve authored strings verbatim.
            (new LongTextField('graph_json', 'graphJson'))->addFlags(new Required(), new AllowHtml(sanitized: false)),
            new UpdatedByField([ApprovalDraftWriteGuard::WRITE_SCOPE]),

            new ManyToOneAssociationField('definition', 'definition_id', ApprovalFlowDefinition::class, 'id', false),
            new ManyToOneAssociationField('updatedBy', 'updated_by_id', UserDefinition::class, 'id', false),
        ]);
    }
}
