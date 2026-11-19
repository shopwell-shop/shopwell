<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Instance;

use Shopwell\Core\Framework\ApprovalFlow\Release\ApprovalReleaseDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\EnumField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceServiceTest
 */
#[Package('framework')]
class ApprovalInstanceDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'approval_flow_instance';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ApprovalInstanceEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ApprovalInstanceCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('release_id', 'releaseId', ApprovalReleaseDefinition::class))->addFlags(new Required()),
            (new EnumField('status', 'status', ApprovalInstanceStatus::Draft))->addFlags(new Required()),
            (new IntField('aggregate_version', 'aggregateVersion', 1))->addFlags(new Required()),
            new ManyToOneAssociationField('release', 'release_id', ApprovalReleaseDefinition::class, 'id', false),
        ]);
    }
}
