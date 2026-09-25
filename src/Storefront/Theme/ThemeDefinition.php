<?php declare(strict_types=1);

namespace Shopwell\Storefront\Theme;

use Shopwell\Core\Content\Media\MediaDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelDefinition;
use Shopwell\Storefront\Theme\Aggregate\ThemeChildDefinition;
use Shopwell\Storefront\Theme\Aggregate\ThemeMediaDefinition;
use Shopwell\Storefront\Theme\Aggregate\ThemeSalesChannelDefinition;
use Shopwell\Storefront\Theme\Aggregate\ThemeTranslationDefinition;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class ThemeDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'theme';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return ThemeCollection::class;
    }

    public function getEntityClass(): string
    {
        return ThemeEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            (new StringField('technical_name', 'technicalName'))->addFlags(new ApiAware()),
            (new StringField('name', 'name'))->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new StringField('author', 'author'))->addFlags(new ApiAware(), new Required()),
            (new TranslatedField('description'))->addFlags(new ApiAware()),
            (new TranslatedField('labels'))->addFlags(new ApiAware()),
            (new TranslatedField('helpTexts'))->addFlags(new ApiAware()),
            (new TranslatedField('customFields'))->addFlags(new ApiAware()),
            (new FkField('preview_media_id', 'previewMediaId', MediaDefinition::class))->addFlags(new ApiAware()),
            (new FkField('parent_theme_id', 'parentThemeId', self::class))->addFlags(new ApiAware()),
            new JsonField('theme_json', 'themeJson'),
            (new JsonField('base_config', 'baseConfig'))->addFlags(new ApiAware()),
            (new JsonField('config_values', 'configValues'))->addFlags(new ApiAware()),
            (new BoolField('active', 'active'))->addFlags(new ApiAware(), new Required()),

            (new TranslationsAssociationField(ThemeTranslationDefinition::class, 'theme_id'))->addFlags(new Required()),
            new ManyToManyAssociationField('salesChannels', SalesChannelDefinition::class, ThemeSalesChannelDefinition::class, 'theme_id', 'sales_channel_id'),
            (new ManyToManyAssociationField('media', MediaDefinition::class, ThemeMediaDefinition::class, 'theme_id', 'media_id'))->addFlags(new ApiAware()),
            new ManyToOneAssociationField('previewMedia', 'preview_media_id', MediaDefinition::class),
            new ManyToManyAssociationField('dependentThemes', self::class, ThemeChildDefinition::class, 'parent_id', 'child_id'),
        ]);
    }
}
