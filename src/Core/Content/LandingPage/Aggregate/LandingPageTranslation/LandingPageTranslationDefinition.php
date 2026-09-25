<?php declare(strict_types=1);

namespace Shopwell\Core\Content\LandingPage\Aggregate\LandingPageTranslation;

use Shopwell\Core\Content\LandingPage\LandingPageDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class LandingPageTranslationDefinition extends EntityTranslationDefinition
{
    final public const ENTITY_NAME = 'landing_page_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return LandingPageTranslationCollection::class;
    }

    public function getEntityClass(): string
    {
        return LandingPageTranslationEntity::class;
    }

    public function since(): ?string
    {
        return '6.4.0.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return LandingPageDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->addFlags(new ApiAware(), new Required()),
            (new StringField('url', 'url'))->addFlags(new ApiAware(), new Required()),
            (new JsonField('slot_config', 'slotConfig'))->addFlags(new ApiAware()),
            (new LongTextField('meta_title', 'metaTitle'))->addFlags(new ApiAware()),
            (new LongTextField('meta_description', 'metaDescription'))->addFlags(new ApiAware()),
            (new LongTextField('keywords', 'keywords'))->addFlags(new ApiAware()),
            (new CustomFields())->addFlags(new ApiAware()),
        ]);
    }
}
