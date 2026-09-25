<?php

declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Cookie\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Cookie\CookieException;
use Shopwell\Core\Content\Cookie\Struct\CookieEntryCollection;
use Shopwell\Core\Content\Cookie\Struct\CookieGroup;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CookieGroup::class)]
class CookieGroupTest extends TestCase
{
    public function testNotInitializedPropertiesGetter(): void
    {
        $cookieGroup = new CookieGroup('test.group');
        static::assertNull($cookieGroup->getCookie());
        static::assertNull($cookieGroup->getEntries());
    }

    public function testSetEntriesWithoutCookie(): void
    {
        $cookieEntryCollection = new CookieEntryCollection();
        $cookieGroup = new CookieGroup('test.group');
        $cookieGroup->setEntries($cookieEntryCollection);

        static::assertNull($cookieGroup->getCookie());
        static::assertSame($cookieEntryCollection, $cookieGroup->getEntries());
    }

    public function testSetEntriesIfCookiesAreSetThrowsException(): void
    {
        $cookieGroup = new CookieGroup('test.group');
        $cookieGroup->setCookie('test-cookie');

        $this->expectExceptionObject(CookieException::notAllowedPropertyAssignment('entries', 'cookie'));
        $cookieGroup->setEntries(new CookieEntryCollection());
    }

    public function testSetCookieWithoutEntries(): void
    {
        $cookieGroup = new CookieGroup('test.group');
        $cookieGroup->setCookie('test-cookie');

        static::assertSame('test-cookie', $cookieGroup->getCookie());
        static::assertNull($cookieGroup->getEntries());
    }

    public function testSetCookieIfEntriesAreSetThrowsException(): void
    {
        $cookieGroup = new CookieGroup('test.group');
        $cookieGroup->setEntries(new CookieEntryCollection());

        $this->expectExceptionObject(CookieException::notAllowedPropertyAssignment('cookie', 'entries'));
        $cookieGroup->setCookie('test-cookie');
    }
}
