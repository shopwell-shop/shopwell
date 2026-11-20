<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Result;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceDefinition;
use Shopwell\Core\Framework\ApprovalFlow\Release\ApprovalReleaseDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
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
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Result\ApprovalResultOutboxTest
 */
#[Package('framework')]
class ApprovalResultDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'approval_flow_result';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ApprovalResultEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ApprovalResultCollection::class;
    }

    protected function defaultFields(): array
    {
        return [new CreatedAtField()];
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('instance_id', 'instanceId', ApprovalInstanceDefinition::class))->addFlags(new Required()),
            (new FkField('release_id', 'releaseId', ApprovalReleaseDefinition::class))->addFlags(new Required()),
            (new IdField('command_id', 'commandId'))->addFlags(new Required()),
            (new IntField('aggregate_version', 'aggregateVersion', 1))->addFlags(new Required()),
            (new EnumField('outcome', 'outcome', ApprovalOutcome::Approved))->addFlags(new Required()),
            (new DateTimeField('finished_at', 'finishedAt'))->addFlags(new Required()),
            new ManyToOneAssociationField('instance', 'instance_id', ApprovalInstanceDefinition::class, 'id', false),
            new ManyToOneAssociationField('release', 'release_id', ApprovalReleaseDefinition::class, 'id', false),
        ]);
    }
}
