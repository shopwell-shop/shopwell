<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Aggregate\FlowActionTranslation;

use Shopwell\Core\Framework\App\Aggregate\FlowAction\AppFlowActionDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class AppFlowActionTranslationDefinition extends EntityTranslationDefinition
{
    final public const ENTITY_NAME = 'app_flow_action_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return AppFlowActionTranslationEntity::class;
    }

    public function getCollectionClass(): string
    {
        return AppFlowActionTranslationCollection::class;
    }

    public function since(): ?string
    {
        return '6.4.10.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return AppFlowActionDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('label', 'label'))->addFlags(new Required()),
            new LongTextField('description', 'description'),
            new StringField('headline', 'headline'),
            new CustomFields(),
        ]);
    }
}
