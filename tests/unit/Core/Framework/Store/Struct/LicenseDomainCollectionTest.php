<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Store\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Store\Struct\LicenseDomainCollection;
use Shopwell\Core\Framework\Store\Struct\LicenseDomainStruct;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(LicenseDomainCollection::class)]
class LicenseDomainCollectionTest extends TestCase
{
    public function testAddKeysTheElementByItsDomain(): void
    {
        $collection = new LicenseDomainCollection();
        $domain = (new LicenseDomainStruct())->assign(['domain' => 'example.com']);

        $collection->add($domain);

        static::assertSame($domain, $collection->get('example.com'));
    }

    public function testSetIgnoresTheGivenKeyInFavorOfTheDomain(): void
    {
        $collection = new LicenseDomainCollection();
        $domain = (new LicenseDomainStruct())->assign(['domain' => 'example.com']);

        $collection->set('custom-key', $domain);

        static::assertNull($collection->get('custom-key'));
        static::assertSame($domain, $collection->get('example.com'));
    }

    public function testApiAlias(): void
    {
        static::assertSame('store_license_domain_collection', (new LicenseDomainCollection())->getApiAlias());
    }
}
