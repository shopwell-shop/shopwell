<?php declare(strict_types=1);

namespace Shopwell\Storefront\Theme\Extension;

use Shopwell\Core\Content\Media\MediaDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Storefront\Theme\Aggregate\ThemeMediaDefinition;
use Shopwell\Storefront\Theme\ThemeDefinition;

#[Package('discovery')]
class MediaExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new OneToManyAssociationField('themes', ThemeDefinition::class, 'preview_media_id')
        );

        $collection->add(
            (new ManyToManyAssociationField('themeMedia', ThemeDefinition::class, ThemeMediaDefinition::class, 'media_id', 'theme_id'))->addFlags(new RestrictDelete())
        );
    }

    public function getEntityName(): string
    {
        return MediaDefinition::ENTITY_NAME;
    }
}
