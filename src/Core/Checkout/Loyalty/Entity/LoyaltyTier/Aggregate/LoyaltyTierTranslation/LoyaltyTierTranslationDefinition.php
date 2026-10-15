<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier\Aggregate\LoyaltyTierTranslation;

use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier\LoyaltyTierDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class LoyaltyTierTranslationDefinition extends EntityTranslationDefinition
{
    final public const ENTITY_NAME = 'loyalty_tier_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return LoyaltyTierTranslationCollection::class;
    }

    public function getEntityClass(): string
    {
        return LoyaltyTierTranslationEntity::class;
    }

    public function since(): ?string
    {
        return '6.7.18.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return LoyaltyTierDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->addFlags(new ApiAware()),
            (new LongTextField('description', 'description'))->addFlags(new ApiAware()),
        ]);
    }
}
