<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Entity\MembershipPlan\Aggregate\MembershipPlanTranslation;

use Shopwell\Core\Checkout\Loyalty\Entity\MembershipPlan\MembershipPlanDefinition;
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
class MembershipPlanTranslationDefinition extends EntityTranslationDefinition
{
    final public const ENTITY_NAME = 'membership_plan_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return MembershipPlanTranslationCollection::class;
    }

    public function getEntityClass(): string
    {
        return MembershipPlanTranslationEntity::class;
    }

    public function since(): ?string
    {
        return '6.7.18.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return MembershipPlanDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->addFlags(new ApiAware()),
            (new LongTextField('description', 'description'))->addFlags(new ApiAware()),
        ]);
    }
}
