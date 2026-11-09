<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ContentSystem\Fixture;

use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;

/**
 * @internal
 */
final class LoaderConfigSpecificationFixture
{
    /**
     * The entity source's specification shape: a required entity name seeded by the
     * capability template plus a required property reference filled by the author.
     */
    public static function entityProperty(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('entity', ConfigKeyKind::EntityName, 'string', required: true),
            new ConfigKeySpecification('property', ConfigKeyKind::PropertyReference, 'string', required: true),
        ]);
    }
}
