<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Cart\Address\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Address\Error\BillingAddressCountryRegionMissingError;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(BillingAddressCountryRegionMissingError::class)]
class BillingAddressCountryRegionMissingErrorTest extends TestCase
{
    public function testAPI(): void
    {
        $address = new CustomerAddressEntity();
        $address->setName('Max Mustermann');
        $address->setStreet('Musterstraße 1');
        $address->setZipcode('12345');
        $address->setCity('Musterstadt');
        $address->setId('address-id');

        $error = new BillingAddressCountryRegionMissingError($address);

        static::assertSame('country-region-missing-billing-address', $error->getId());
        static::assertSame('A country region needs to be defined for the billing address "Max Mustermann, 12345 Musterstadt".', $error->getMessage());
        static::assertSame('country-region-missing-billing-address', $error->getMessageKey());
        static::assertSame(10, $error->getLevel());
        static::assertTrue($error->blockOrder());
        static::assertSame(['addressId' => 'address-id'], $error->getParameters());
    }
}
