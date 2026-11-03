<?php declare(strict_types=1);

namespace Shopwell\Core\System\AdminMenu\Aggregate\AdminMenuItemTranslation;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\AdminMenu\AdminMenuItemDefinition;

/**
 * @codeCoverageIgnore
 */
#[Package('fundamentals@framework')]
class AdminMenuItemTranslationDefinition extends EntityTranslationDefinition
{
    final public const ENTITY_NAME = 'admin_menu_item_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return AdminMenuItemTranslationCollection::class;
    }

    public function getEntityClass(): string
    {
        return AdminMenuItemTranslationEntity::class;
    }

    public function since(): ?string
    {
        return '6.7.18.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return AdminMenuItemDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('title', 'title'))->addFlags(new ApiAware())->setDescription('Translated label of the menu item shown in the administration navigation.'),
            (new StringField('group_title', 'groupTitle'))->addFlags(new ApiAware())->setDescription('Translated label of the group this item is listed under.'),
        ]);
    }
}
