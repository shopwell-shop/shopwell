<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayEntity;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ArrayEntity::class)]
class ArrayEntityTest extends TestCase
{
    public function testMagicAccessReadsAndWritesTheData(): void
    {
        $entity = new ArrayEntity(['name' => 'shopwell']);

        static::assertSame('shopwell', $entity->__get('name'));
        static::assertTrue($entity->__isset('name'));
        static::assertFalse($entity->__isset('version'));

        $entity->__set('version', '6.7');
        static::assertSame('6.7', $entity->__get('version'));
    }

    public function testSettingTheIdUpdatesTheUniqueIdentifier(): void
    {
        $entity = new ArrayEntity();
        $entity->__set('id', 'entity-id');

        static::assertSame('entity-id', $entity->getId());
        static::assertSame('entity-id', $entity->getUniqueIdentifier());
    }

    public function testGetUniqueIdentifierFallsBackToTheIdInTheData(): void
    {
        static::assertSame('data-id', (new ArrayEntity(['id' => 'data-id']))->getUniqueIdentifier());
    }

    public function testArrayAccess(): void
    {
        $entity = new ArrayEntity(['name' => 'shopwell']);

        static::assertTrue($entity->offsetExists('name'));
        static::assertSame('shopwell', $entity->offsetGet('name'));
        static::assertNull($entity->offsetGet('unknown'));

        $entity->offsetSet('version', '6.7');
        static::assertSame('6.7', $entity->offsetGet('version'));

        $entity->offsetUnset('version');
        static::assertFalse($entity->offsetExists('version'));
    }

    public function testGetSetHasAndAll(): void
    {
        $entity = new ArrayEntity(['name' => 'shopwell']);

        static::assertTrue($entity->has('name'));
        static::assertFalse($entity->has('version'));
        static::assertSame('shopwell', $entity->get('name'));

        $entity->set('version', '6.7');

        static::assertSame(['name' => 'shopwell', 'version' => '6.7'], $entity->all());
    }

    public function testAssignMergesTheDataAndUpdatesTheUniqueIdentifier(): void
    {
        $entity = new ArrayEntity(['name' => 'shopwell', 'config' => ['a' => 1]]);

        $entity->assign(['id' => 'assigned-id', 'config' => ['b' => 2]]);

        static::assertSame('assigned-id', $entity->getUniqueIdentifier());
        static::assertSame(['a' => 1, 'b' => 2], $entity->get('config'));
    }

    public function testTranslationsLiveInTheTranslatedDataKey(): void
    {
        $entity = new ArrayEntity();

        static::assertSame([], $entity->getTranslated());
        static::assertNull($entity->getTranslation('name'));

        $entity->addTranslated('name', 'shopwell');

        static::assertSame('shopwell', $entity->getTranslation('name'));
        static::assertSame(['name' => 'shopwell'], $entity->getTranslated());
    }

    public function testGetVarsMergesTheDataOneLevelUp(): void
    {
        $vars = (new ArrayEntity(['name' => 'shopwell']))->getVars();

        static::assertArrayNotHasKey('data', $vars);
        static::assertSame('shopwell', $vars['name']);
    }

    public function testJsonSerializeFlattensTheData(): void
    {
        $json = (new ArrayEntity(['name' => 'shopwell']))->jsonSerialize();

        static::assertArrayNotHasKey('data', $json);
        static::assertArrayNotHasKey('createdAt', $json);
        static::assertSame('shopwell', $json['name']);
    }
}
