<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Event\EventData;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerDefinition;
use Shopwell\Core\Framework\Event\EventData\EntityCollectionType;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EntityCollectionType::class)]
class EntityCollectionTypeTest extends TestCase
{
    public function testToArray(): void
    {
        $expected = [
            'type' => 'collection',
            'entityClass' => CustomerDefinition::class,
        ];

        static::assertSame($expected, (new EntityCollectionType(CustomerDefinition::class))->toArray());
    }
}
