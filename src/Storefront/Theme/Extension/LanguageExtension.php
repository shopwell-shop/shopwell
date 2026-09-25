<?php declare(strict_types=1);

namespace Shopwell\Storefront\Theme\Extension;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Language\LanguageDefinition;
use Shopwell\Storefront\Theme\Aggregate\ThemeTranslationDefinition;

#[Package('discovery')]
class LanguageExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new OneToManyAssociationField('themeTranslations', ThemeTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete())
        );
    }

    public function getEntityName(): string
    {
        return LanguageDefinition::ENTITY_NAME;
    }
}
