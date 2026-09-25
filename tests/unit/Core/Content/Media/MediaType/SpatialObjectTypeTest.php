<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Media\MediaType;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Media\MediaType\SpatialObjectType;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SpatialObjectType::class)]
class SpatialObjectTypeTest extends TestCase
{
    public function testName(): void
    {
        static::assertSame('SPATIAL_OBJECT', (new SpatialObjectType())->getName());
    }
}
