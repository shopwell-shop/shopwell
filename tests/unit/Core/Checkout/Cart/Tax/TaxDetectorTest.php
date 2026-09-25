<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Cart\Tax;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopwell\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopwell\Core\Checkout\Cart\Tax\TaxDetector;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\TaxFreeConfig;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\System\Country\CountryEntity;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(TaxDetector::class)]
class TaxDetectorTest extends TestCase
{
    public function testIsCompanyTaxFreeWithEuCountryAndValidVatIdMatchingPattern(): void
    {
        $country = (new CountryEntity())->assign([
            'companyTax' => new TaxFreeConfig(true),
            'isEu' => true,
            'vatIdPattern' => '(DE)?[0-9]{9}',
            'checkVatIdPattern' => true,
        ]);

        $customer = (new CustomerEntity())->assign([
            'company' => 'EU Company',
            'vatIds' => ['DE123456789'],
        ]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);

        $detector = new TaxDetector();
        static::assertTrue($detector->isCompanyTaxFree($context, $country));
    }

    public function testIsCompanyTaxFreeWithEuCountryAndInvalidVatIdPattern(): void
    {
        $country = (new CountryEntity())->assign([
            'companyTax' => new TaxFreeConfig(true),
            'isEu' => true,
            'vatIdPattern' => '(DE)?[0-9]{9}',
            'checkVatIdPattern' => true,
        ]);

        $customer = (new CustomerEntity())->assign([
            'company' => 'EU Company',
            'vatIds' => ['INVALID-VAT'],
        ]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);

        $detector = new TaxDetector();
        static::assertFalse($detector->isCompanyTaxFree($context, $country));
    }

    public function testGetDecoratedThrowsDecorationPatternException(): void
    {
        $detector = new TaxDetector();

        $this->expectExceptionObject(new DecorationPatternException(TaxDetector::class));

        $detector->getDecorated();
    }

    public function testGetTaxStateReturnsFreeWhenNetDelivery(): void
    {
        $country = (new CountryEntity())->assign([
            'customerTax' => new TaxFreeConfig(true),
        ]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getShippingLocation')->willReturn(ShippingLocation::createFromCountry($country));

        $detector = new TaxDetector();
        static::assertSame(CartPrice::TAX_STATE_FREE, $detector->getTaxState($context));
    }

    public function testGetTaxStateReturnsGrossWhenNotNetDeliveryAndUseGross(): void
    {
        $country = (new CountryEntity())->assign([
            'customerTax' => new TaxFreeConfig(false),
            'companyTax' => new TaxFreeConfig(false),
        ]);

        $customerGroup = new CustomerGroupEntity();
        $customerGroup->setDisplayGross(true);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getShippingLocation')->willReturn(ShippingLocation::createFromCountry($country));
        $context->method('getCurrentCustomerGroup')->willReturn($customerGroup);

        $detector = new TaxDetector();
        static::assertSame(CartPrice::TAX_STATE_GROSS, $detector->getTaxState($context));
    }

    public function testGetTaxStateReturnsNetWhenNotNetDeliveryAndNotUseGross(): void
    {
        $country = (new CountryEntity())->assign([
            'customerTax' => new TaxFreeConfig(false),
            'companyTax' => new TaxFreeConfig(false),
        ]);

        $customerGroup = new CustomerGroupEntity();
        $customerGroup->setDisplayGross(false);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getShippingLocation')->willReturn(ShippingLocation::createFromCountry($country));
        $context->method('getCurrentCustomerGroup')->willReturn($customerGroup);

        $detector = new TaxDetector();
        static::assertSame(CartPrice::TAX_STATE_NET, $detector->getTaxState($context));
    }

    public function testIsCompanyTaxFreeReturnsTrueWhenNonEuCountry(): void
    {
        $country = (new CountryEntity())->assign([
            'companyTax' => new TaxFreeConfig(true),
            'isEu' => false,
        ]);

        $customer = (new CustomerEntity())->assign([
            'company' => 'Non-EU Company',
        ]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);

        $detector = new TaxDetector();
        static::assertTrue($detector->isCompanyTaxFree($context, $country));
    }

    public function testIsCompanyTaxFreeReturnsFalseWhenCustomerIsNull(): void
    {
        $country = (new CountryEntity())->assign([
            'companyTax' => new TaxFreeConfig(true),
            'isEu' => false,
        ]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn(null);

        $detector = new TaxDetector();
        static::assertFalse($detector->isCompanyTaxFree($context, $country));
    }

    public function testIsCompanyTaxFreeReturnsFalseWhenEuCountryAndEmptyVatIds(): void
    {
        $country = (new CountryEntity())->assign([
            'companyTax' => new TaxFreeConfig(true),
            'isEu' => true,
            'vatIdPattern' => '(DE)?[0-9]{9}',
            'checkVatIdPattern' => true,
        ]);

        $customer = (new CustomerEntity())->assign([
            'company' => 'EU Company',
            'vatIds' => [],
        ]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);

        $detector = new TaxDetector();
        static::assertFalse($detector->isCompanyTaxFree($context, $country));
    }

    public function testIsCompanyTaxFreeReturnsFalseWhenCustomerHasNoCompany(): void
    {
        $country = (new CountryEntity())->assign([
            'companyTax' => new TaxFreeConfig(true),
            'isEu' => false,
        ]);

        $customer = (new CustomerEntity())->assign([
            'company' => null,
        ]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);

        $detector = new TaxDetector();
        static::assertFalse($detector->isCompanyTaxFree($context, $country));
    }

    public function testIsCompanyTaxFreeReturnsFalseWhenCountryCompanyTaxDisabled(): void
    {
        $country = (new CountryEntity())->assign([
            'companyTax' => new TaxFreeConfig(false),
            'isEu' => true,
        ]);

        $customer = (new CustomerEntity())->assign([
            'company' => 'Test Company',
            'vatIds' => ['DE123456789'],
        ]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);

        $detector = new TaxDetector();
        static::assertFalse($detector->isCompanyTaxFree($context, $country));
    }

    public function testIsCompanyTaxFreeWithEuCountryAndMultipleValidVatIdsMatchingPattern(): void
    {
        $country = (new CountryEntity())->assign([
            'companyTax' => new TaxFreeConfig(true),
            'isEu' => true,
            'vatIdPattern' => '(DE)?[0-9]{9}',
            'checkVatIdPattern' => true,
        ]);

        $customer = (new CustomerEntity())->assign([
            'company' => 'EU Company',
            'vatIds' => ['DE123456789', 'DE987654321'],
        ]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);

        $detector = new TaxDetector();
        static::assertTrue($detector->isCompanyTaxFree($context, $country));
    }

    public function testIsCompanyTaxFreeWithEuCountryAndMultipleVatIdsOneInvalidReturnsFalse(): void
    {
        $country = (new CountryEntity())->assign([
            'companyTax' => new TaxFreeConfig(true),
            'isEu' => true,
            'vatIdPattern' => '(DE)?[0-9]{9}',
            'checkVatIdPattern' => true,
        ]);

        $customer = (new CustomerEntity())->assign([
            'company' => 'EU Company',
            'vatIds' => ['DE123456789', 'INVALID'],
        ]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);

        $detector = new TaxDetector();
        static::assertFalse($detector->isCompanyTaxFree($context, $country));
    }
}
