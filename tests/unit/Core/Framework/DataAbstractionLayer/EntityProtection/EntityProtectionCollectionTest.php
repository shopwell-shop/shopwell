<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\DataAbstractionLayer\EntityProtection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtectionCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityProtection\ReadProtection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityProtection\WriteProtection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EntityProtectionCollection::class)]
class EntityProtectionCollectionTest extends TestCase
{
    public function testAddKeysTheElementByItsClass(): void
    {
        $collection = new EntityProtectionCollection();
        $protection = new ReadProtection();

        $collection->add($protection);

        static::assertSame($protection, $collection->get(ReadProtection::class));
    }

    public function testSetIgnoresTheGivenKeyInFavorOfTheClass(): void
    {
        $collection = new EntityProtectionCollection();
        $protection = new WriteProtection();

        $collection->set('custom-key', $protection);

        static::assertNull($collection->get('custom-key'));
        static::assertSame($protection, $collection->get(WriteProtection::class));
    }

    public function testApiAlias(): void
    {
        static::assertSame('dal_protection_collection', (new EntityProtectionCollection())->getApiAlias());
    }
}
