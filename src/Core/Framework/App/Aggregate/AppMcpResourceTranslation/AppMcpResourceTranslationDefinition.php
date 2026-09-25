<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Aggregate\AppMcpResourceTranslation;

use Shopwell\Core\Framework\App\Aggregate\AppMcpResource\AppMcpResourceDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class AppMcpResourceTranslationDefinition extends EntityTranslationDefinition
{
    final public const ENTITY_NAME = 'app_mcp_resource_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return AppMcpResourceTranslationEntity::class;
    }

    public function getCollectionClass(): string
    {
        return AppMcpResourceTranslationCollection::class;
    }

    public function since(): ?string
    {
        return '6.7.11.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return AppMcpResourceDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('label', 'label'))->addFlags(new Required()),
            new LongTextField('description', 'description'),
        ]);
    }
}
