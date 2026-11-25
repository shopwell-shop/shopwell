<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Request;

use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBindingDefinition;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
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

/**
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Request\ApprovalRequestServiceTest
 */
#[Package('framework')]
class ApprovalRequestDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'approval_flow_request';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ApprovalRequestEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ApprovalRequestCollection::class;
    }

    protected function defaultFields(): array
    {
        return [new CreatedAtField()];
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('business_type', 'businessType', 128))->addFlags(new Required()),
            (new StringField('business_id', 'businessId', 255))->addFlags(new Required()),
            (new IntField('submission_round', 'submissionRound', 1))->addFlags(new Required()),
            (new StringField('business_revision', 'businessRevision', 64))->addFlags(new Required()),
            (new FkField('binding_id', 'bindingId', ApprovalBindingDefinition::class))->addFlags(new Required()),
            (new FkField('instance_id', 'instanceId', ApprovalInstanceDefinition::class))->addFlags(new Required()),
            (new IdField('initiator_id', 'initiatorId'))->addFlags(new Required()),
            (new LongTextField('form_json', 'formJson'))->addFlags(new Required(), new AllowHtml(sanitized: false)),
            new ManyToOneAssociationField('instance', 'instance_id', ApprovalInstanceDefinition::class, 'id', false),
            new ManyToOneAssociationField('binding', 'binding_id', ApprovalBindingDefinition::class, 'id', false),
        ]);
    }
}
