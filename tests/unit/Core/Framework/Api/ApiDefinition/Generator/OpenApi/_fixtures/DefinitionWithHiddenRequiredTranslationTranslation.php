<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\OpenApi\_fixtures;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal
 */
class DefinitionWithHiddenRequiredTranslationTranslation extends EntityTranslationDefinition
{
    final public const ENTITY_NAME = DefinitionWithHiddenRequiredTranslation::ENTITY_NAME . '_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function getParentDefinitionClass(): string
    {
        return DefinitionWithHiddenRequiredTranslation::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('hidden_translated', 'hiddenTranslated'))->addFlags(new ApiAware(), new Required()),
        ]);
    }
}
