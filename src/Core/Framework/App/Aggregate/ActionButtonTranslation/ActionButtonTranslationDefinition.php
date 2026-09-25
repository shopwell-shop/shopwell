<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Aggregate\ActionButtonTranslation;

use Shopwell\Core\Framework\App\Aggregate\ActionButton\ActionButtonDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class ActionButtonTranslationDefinition extends EntityTranslationDefinition
{
    final public const ENTITY_NAME = 'app_action_button_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ActionButtonTranslationEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ActionButtonTranslationCollection::class;
    }

    public function since(): ?string
    {
        return '6.3.1.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return ActionButtonDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('label', 'label'))->addFlags(new Required()),
        ]);
    }
}
