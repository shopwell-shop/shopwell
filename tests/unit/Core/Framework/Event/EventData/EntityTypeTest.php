<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Event\EventData;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerDefinition;
use Shopwell\Core\Framework\Event\EventData\EntityType;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EntityType::class)]
class EntityTypeTest extends TestCase
{
    public function testToArray(): void
    {
        $definition = CustomerDefinition::class;

        $expected = [
            'type' => 'entity',
            'entityClass' => CustomerDefinition::class,
            'entityName' => 'customer',
        ];

        static::assertSame($expected, (new EntityType($definition))->toArray());
        static::assertSame($expected, (new EntityType(new CustomerDefinition()))->toArray());
    }
}
