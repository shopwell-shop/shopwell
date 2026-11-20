<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Binding;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Binding\ApprovalBindingServiceTest
 */
#[Package('framework')]
class ApprovalBindingHeadDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'approval_flow_binding_head';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ApprovalBindingHeadEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ApprovalBindingHeadCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('business_type', 'businessType', 128))->addFlags(new Required()),
            (new IntField('latest_revision', 'latestRevision', 0))->addFlags(new Required()),
            new IntField('active_revision', 'activeRevision', 1),
            (new IntField('version', 'version', 0))->addFlags(new Required()),
        ]);
    }
}
