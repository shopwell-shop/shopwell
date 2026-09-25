<?php

declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Cart\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Address\Error\AddressValidationError;
use Shopwell\Core\Checkout\Cart\Address\Error\BillingAddressBlockedError;
use Shopwell\Core\Checkout\Cart\Address\Error\BillingAddressCountryRegionMissingError;
use Shopwell\Core\Checkout\Cart\Address\Error\BillingAddressSalutationMissingError;
use Shopwell\Core\Checkout\Cart\Address\Error\ShippingAddressBlockedError;
use Shopwell\Core\Checkout\Cart\Address\Error\ShippingAddressCountryRegionMissingError;
use Shopwell\Core\Checkout\Cart\Address\Error\ShippingAddressSalutationMissingError;
use Shopwell\Core\Checkout\Cart\Error\Error;
use Shopwell\Core\Checkout\Cart\Error\GenericCartError;
use Shopwell\Core\Checkout\Cart\Error\IncompleteLineItemError;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopwell\Core\Checkout\Gateway\Error\CheckoutGatewayError;
use Shopwell\Core\Checkout\Payment\Cart\Error\PaymentMethodBlockedError;
use Shopwell\Core\Checkout\Promotion\Cart\Error\AutoPromotionNotFoundError;
use Shopwell\Core\Checkout\Promotion\Cart\Error\PromotionExcludedError;
use Shopwell\Core\Checkout\Promotion\Cart\Error\PromotionNotEligibleError;
use Shopwell\Core\Checkout\Promotion\Cart\Error\PromotionNotFoundError;
use Shopwell\Core\Checkout\Promotion\Cart\Error\PromotionsOnCartPriceZeroError;
use Shopwell\Core\Checkout\Promotion\Cart\PromotionCartAddedInformationError;
use Shopwell\Core\Checkout\Promotion\Cart\PromotionCartDeletedInformationError;
use Shopwell\Core\Checkout\Shipping\Cart\Error\ShippingMethodBlockedError;
use Shopwell\Core\Content\Product\Cart\MinOrderQuantityError;
use Shopwell\Core\Content\Product\Cart\ProductNotFoundError;
use Shopwell\Core\Content\Product\Cart\ProductOutOfStockError;
use Shopwell\Core\Content\Product\Cart\ProductStockReachedError;
use Shopwell\Core\Content\Product\Cart\PurchaseStepsError;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Assert\Serialization;
use Shopwell\Storefront\Checkout\Cart\Error\PaymentMethodChangedError;
use Shopwell\Storefront\Checkout\Cart\Error\ShippingMethodChangedError;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Error::class)]
class ErrorTest extends TestCase
{
    public function testShippingMethodBlockedErrorSerialization(): void
    {
        $id = Uuid::randomHex();
        $error = new ShippingMethodBlockedError(
            id: $id,
            name: 'foo',
            reason: 'bar',
        );

        static::assertSame($id, $error->getShippingMethodId());
        static::assertSame('foo', $error->getName());
        static::assertSame('bar', $error->getReason());

        $unserialized = Serialization::assertRoundTrip($error);

        static::assertSame($id, $unserialized->getShippingMethodId());
        static::assertSame('foo', $unserialized->getName());
        static::assertSame('bar', $unserialized->getReason());
    }

    #[DataProvider('serializationDataProvider')]
    public function testErrorSerialization(Error $error): void
    {
        $unserialized = Serialization::assertRoundTrip($error);

        // Call all public methods without parameters (i.e. getters) to make sure the don't throw an exception
        $refClass = new \ReflectionClass($error);
        $refMethods = $refClass->getMethods(\ReflectionMethod::IS_PUBLIC);
        foreach ($refMethods as $method) {
            if ($method->getNumberOfParameters() !== 0) {
                continue;
            }

            /** @deprecated tag:v6.8.0 - remove whole if statement */
            if ($method->getName() === 'getRoute') {
                // Skip getRoute method as it is deprecated and will be removed in v6.8.0.0
                continue;
            }

            $method->invoke($error);
        }
    }

    /**
     * @return iterable<class-string<Error>, array{0: Error}>
     */
    public static function serializationDataProvider(): iterable
    {
        yield AddressValidationError::class => [new AddressValidationError(true, new ConstraintViolationList(), 'address-id-123')];
        yield BillingAddressBlockedError::class => [new BillingAddressBlockedError('foo', 'address-id-123')];
        yield BillingAddressCountryRegionMissingError::class => [new BillingAddressCountryRegionMissingError(self::createCustomerAddress())];
        yield BillingAddressSalutationMissingError::class => [new BillingAddressSalutationMissingError(self::createCustomerAddress())];
        yield ShippingAddressBlockedError::class => [new ShippingAddressBlockedError('foo', 'address-id-123')];
        yield ShippingAddressCountryRegionMissingError::class => [new ShippingAddressCountryRegionMissingError(self::createCustomerAddress())];
        yield ShippingAddressSalutationMissingError::class => [new ShippingAddressSalutationMissingError(self::createCustomerAddress())];
        yield GenericCartError::class => [new GenericCartError('foo', 'bar', [], Error::LEVEL_ERROR, false, false, false)];
        yield IncompleteLineItemError::class => [new IncompleteLineItemError('foo', 'bar')];
        yield CheckoutGatewayError::class => [new CheckoutGatewayError('foo', Error::LEVEL_NOTICE, true)];
        yield PaymentMethodBlockedError::class => [new PaymentMethodBlockedError('foo', Uuid::randomHex(), 'reason')];
        yield AutoPromotionNotFoundError::class => [new AutoPromotionNotFoundError('foo')];
        yield PromotionExcludedError::class => [new PromotionExcludedError('foo')];
        yield PromotionNotEligibleError::class => [new PromotionNotEligibleError('foo')];
        yield PromotionNotFoundError::class => [new PromotionNotFoundError('foo')];
        yield PromotionsOnCartPriceZeroError::class => [new PromotionsOnCartPriceZeroError(['foo', 'bar'])];
        yield PromotionCartAddedInformationError::class => [new PromotionCartAddedInformationError(self::createLineItem())];
        yield PromotionCartDeletedInformationError::class => [new PromotionCartDeletedInformationError(self::createLineItem())];
        yield ShippingMethodBlockedError::class => [new ShippingMethodBlockedError(id: Uuid::randomHex(), name: 'foo', reason: 'reason')];
        yield MinOrderQuantityError::class => [new MinOrderQuantityError(Uuid::randomHex(), 'foo', 5)];
        yield ProductNotFoundError::class => [new ProductNotFoundError(Uuid::randomHex())];
        yield ProductOutOfStockError::class => [new ProductOutOfStockError(Uuid::randomHex(), 'foo')];
        yield ProductStockReachedError::class => [new ProductStockReachedError(Uuid::randomHex(), 'foo', 1)];
        yield PurchaseStepsError::class => [new PurchaseStepsError(Uuid::randomHex(), 'foo', 5)];
        yield PaymentMethodChangedError::class => [new PaymentMethodChangedError(oldPaymentMethodId: Uuid::randomHex(), oldPaymentMethodName: 'foo', newPaymentMethodId: Uuid::randomHex(), newPaymentMethodName: 'bar', reason: 'reason')];
        yield ShippingMethodChangedError::class => [new ShippingMethodChangedError(oldShippingMethodId: Uuid::randomHex(), oldShippingMethodName: 'foo', newShippingMethodId: Uuid::randomHex(), newShippingMethodName: 'bar', reason: 'reason')];
    }

    private static function createCustomerAddress(): CustomerAddressEntity
    {
        $address = new CustomerAddressEntity();
        $address->setId(Uuid::randomHex());

        $address->setCustomerId(Uuid::randomHex());
        $address->setCountryId(Uuid::randomHex());
        $address->setFirstName('John');
        $address->setLastName('Doe');
        $address->setZipcode('12345');
        $address->setCity('Testcity');
        $address->setStreet('Teststreet 1');

        return $address;
    }

    private static function createLineItem(): LineItem
    {
        $lineItem = new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE, Uuid::randomHex(), 2);
        $lineItem->setLabel('LineItem label');

        return $lineItem;
    }
}
