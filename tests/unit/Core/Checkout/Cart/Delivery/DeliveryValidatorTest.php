<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Cart\Delivery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\Delivery\DeliveryValidator;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopwell\Core\Checkout\Cart\Error\ErrorCollection;
use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopwell\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Country\CountryEntity;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(DeliveryValidator::class)]
class DeliveryValidatorTest extends TestCase
{
    private const SHIPPING_METHOD_AVAILABILITY_RULE_ID = 'shipping-method-availability-rule-id';

    public function testValidateDeliveryShallBeValid(): void
    {
        $cart = new Cart('test');
        $context = $this->createMock(SalesChannelContext::class);
        $cart->setDeliveries(new DeliveryCollection([$this->generateDeliveryDummy(self::SHIPPING_METHOD_AVAILABILITY_RULE_ID)]));
        $context->expects($this->once())->method('getRuleIds')->willReturn([self::SHIPPING_METHOD_AVAILABILITY_RULE_ID]);

        $validator = new DeliveryValidator();
        $errors = new ErrorCollection();
        $validator->validate($cart, $errors, $context);

        static::assertCount(0, $errors, 'A delivery without a valid availability rule id should be valid but an error is thrown.');
    }

    public function testValidateDeliveryShippingMethodWithNoAvailabilityRuleShallBeValid(): void
    {
        $cart = new Cart('test');
        $context = static::createStub(SalesChannelContext::class);
        $cart->setDeliveries(new DeliveryCollection([$this->generateDeliveryDummy(null)]));

        $validator = new DeliveryValidator();
        $errors = new ErrorCollection();
        $validator->validate($cart, $errors, $context);

        static::assertCount(0, $errors, 'A delivery without an availability rule should be valid but an error is thrown.');
    }

    public function testValidateDeliveryShippingMethodAvailabilityRuleIdWithEmptyStringShallThrowAnError(): void
    {
        $cart = new Cart('test');
        $context = static::createStub(SalesChannelContext::class);
        $cart->setDeliveries(new DeliveryCollection([$this->generateDeliveryDummy('')]));

        $validator = new DeliveryValidator();
        $errors = new ErrorCollection();
        $validator->validate($cart, $errors, $context);

        static::assertCount(1, $errors, 'A delivery with an empty string as availability rule should not be valid but no error is thrown.');
        static::assertSame('Shipping method Test not available. Reason: rule not matching or inactive', $errors->first()?->getMessage());
    }

    private function generateDeliveryDummy(?string $availabilityRuleId): Delivery
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId('shipping-method-id');
        $shippingMethod->setTranslated(['name' => 'Test']);
        $shippingMethod->setAvailabilityRuleId($availabilityRuleId);
        $shippingMethod->setActive(true);

        $deliveryDate = new DeliveryDate(new \DateTime(), new \DateTime());

        return new Delivery(
            new DeliveryPositionCollection(),
            $deliveryDate,
            $shippingMethod,
            new ShippingLocation(new CountryEntity(), null, null),
            new CalculatedPrice(5, 5, new CalculatedTaxCollection(), new TaxRuleCollection())
        );
    }
}
