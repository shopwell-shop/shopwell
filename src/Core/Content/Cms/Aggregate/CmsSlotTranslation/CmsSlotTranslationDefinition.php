<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Cms\Aggregate\CmsSlotTranslation;

use Shopwell\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotDefinition;
use Shopwell\Core\Content\Cms\DataAbstractionLayer\Field\SlotConfigField;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class CmsSlotTranslationDefinition extends EntityTranslationDefinition
{
    final public const ENTITY_NAME = 'cms_slot_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return CmsSlotTranslationEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return CmsSlotDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new SlotConfigField('config', 'config'))->addFlags(new ApiAware()),
            (new CustomFields())->addFlags(new ApiAware()),
        ]);
    }
}
