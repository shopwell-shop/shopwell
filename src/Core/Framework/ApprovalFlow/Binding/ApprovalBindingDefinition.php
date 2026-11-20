<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Binding;

use Shopwell\Core\Framework\ApprovalFlow\Release\ApprovalReleaseDefinition;
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
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Binding\ApprovalBindingServiceTest
 */
#[Package('framework')]
class ApprovalBindingDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'approval_flow_binding';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ApprovalBindingEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ApprovalBindingCollection::class;
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
            (new StringField('business_version', 'businessVersion', 64))->addFlags(new Required()),
            (new IntField('revision', 'revision', 1))->addFlags(new Required()),
            (new FkField('release_id', 'releaseId', ApprovalReleaseDefinition::class))->addFlags(new Required()),
            (new LongTextField('form_schema_id', 'formSchemaId'))->addFlags(new Required(), new AllowHtml(sanitized: false)),
            (new StringField('form_schema_hash', 'formSchemaHash', 64))->addFlags(new Required()),
            (new LongTextField('form_schema', 'formSchema'))->addFlags(new Required(), new AllowHtml(sanitized: false)),
            new CreatedByField([ApprovalBindingWriteGuard::WRITE_SCOPE]),
        ]);
    }
}
