<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\_fixtures;

use Shopwell\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * Entity whose PascalCase schema name contains consecutive capital letters
 * (SEOUrl <-> s_e_o_url) to cover the PascalCase to snake_case conversion of acronyms.
 *
 * @internal
 */
class SEOUrlDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 's_e_o_url';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField(
                'simpleThings',
                'simple_id',
                SimpleDefinition::class,
                'id'
            ))->addFlags(new ApiAware(SalesChannelApiSource::class)),
        ]);
    }
}
