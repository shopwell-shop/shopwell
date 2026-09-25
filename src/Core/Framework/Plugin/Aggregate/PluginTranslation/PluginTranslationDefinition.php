<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Plugin\Aggregate\PluginTranslation;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\PluginDefinition;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class PluginTranslationDefinition extends EntityTranslationDefinition
{
    final public const ENTITY_NAME = 'plugin_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return PluginTranslationCollection::class;
    }

    public function getEntityClass(): string
    {
        return PluginTranslationEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return PluginDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('label', 'label'))->addFlags(new Required()),
            (new LongTextField('description', 'description'))->addFlags(new AllowHtml()),
            new StringField('manufacturer_link', 'manufacturerLink'),
            new StringField('support_link', 'supportLink'),
            new CustomFields(),
        ]);
    }
}
