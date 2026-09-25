<?php

declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Cookie\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Cookie\Struct\CookieGroup;
use Shopwell\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopwell\Core\Framework\FrameworkException;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CookieGroupCollection::class)]
class CookieGroupCollectionTest extends TestCase
{
    public function testCookieGroupIsIndexedByItsSnippetNameWithConstructor(): void
    {
        $cookieGroup = new CookieGroup('test.group');

        $collection = new CookieGroupCollection([$cookieGroup]);

        // Ensure that the cookie group is indexed by its snippet name
        static::assertSame($cookieGroup, $collection->get('test.group'));
    }

    public function testCookieGroupIsIndexedByItsSnippetNameWithAdd(): void
    {
        $collection = new CookieGroupCollection();

        $cookieGroup = new CookieGroup('test.group');

        $collection->add($cookieGroup);

        // Ensure that the cookie group is indexed by its snippet name
        static::assertSame($cookieGroup, $collection->get('test.group'));
    }

    public function testAddThrowsExceptionWhenWrongObjectIsAdded(): void
    {
        $this->expectExceptionObject(FrameworkException::collectionElementInvalidType(CookieGroup::class, \stdClass::class));
        /** @phpstan-ignore argument.type (Pass wrong type on purpose to test validation) */
        (new CookieGroupCollection())->add(new \stdClass());
    }
}
