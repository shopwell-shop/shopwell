<?php declare(strict_types=1);

namespace Shopwell\Core\Test;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\DeliveryPosition;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopwell\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Payment\Cart\PaymentHandler\DefaultPayment;
use Shopwell\Core\Checkout\Payment\PaymentMethodEntity;
use Shopwell\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Country\Aggregate\CountryState\CountryStateEntity;
use Shopwell\Core\System\Country\CountryEntity;
use Shopwell\Core\System\Currency\CurrencyEntity;
use Shopwell\Core\System\SalesChannel\Context\LanguageInfo;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SalesChannel\SalesChannelDefinition;
use Shopwell\Core\System\SalesChannel\SalesChannelEntity;
use Shopwell\Core\System\Tax\TaxCollection;
use Shopwell\Core\System\Tax\TaxEntity;

/**
 * @internal
 */
#[Package('checkout')]
class Generator extends TestCase
{
    final public const TOKEN = 'test-token';
    final public const DOMAIN = 'test-domain';
    final public const NAVIGATION_CATEGORY = 'f8466865cc6a45e48ed98dd2f6a0a293';
    final public const TAX_CALCULATION_TYPE = SalesChannelDefinition::CALCULATION_TYPE_HORIZONTAL;
    final public const CUSTOMER_GROUP_DISPLAY_GROSS = true;
    final public const TAX = 'c725e107825c4c7281673aeea66ed67e';
    final public const TAX_RATE = 19.0;
    final public const PAYMENT_METHOD = 'cce0e1ca23de4c55868ce057f628c349';
    final public const SHIPPING_METHOD = '37dbe80c5cbb4852a97cb742ed04ba41';
    final public const COUNTRY = 'd4eb3205dd9444169b3f60c056c313a1';
    final public const COUNTRY_STATE = '119d6e30fc4f468daa88ff5b413e9322';
    final public const CUSTOMER_ADDRESS = '08f1594313494c3e9eb57bb53486fe61';
    final public const CUSTOMER = '42d58aa78cf14851968a786a66bab93a';
    final public const LANGUAGE_INFO_NAME = 'English';
    final public const LANGUAGE_INFO_LOCALE_CODE = 'en-GB';

    /**
     * @param array<string, string[]> $areaRuleIds
     * @param array<array-key, mixed> $overrides
     */
    public static function generateSalesChannelContext(
        ?Context $baseContext = null,
        ?string $token = null,
        ?string $domainId = null,
        ?SalesChannelEntity $salesChannel = null,
        ?CurrencyEntity $currency = null,
        ?CustomerGroupEntity $currentCustomerGroup = null,
        ?TaxCollection $taxRules = null,
        ?PaymentMethodEntity $paymentMethod = null,
        ?ShippingMethodEntity $shippingMethod = null,
        ?ShippingLocation $shippingLocation = null,
        ?CustomerEntity $customer = null,
        ?CashRoundingConfig $itemRounding = null,
        ?CashRoundingConfig $totalRounding = null,
        ?array $areaRuleIds = [],
        ?LanguageInfo $languageInfo = null,
        ?CountryEntity $country = null,
        ?CountryStateEntity $countryState = null,
        ?CustomerAddressEntity $customerAddress = null,
        ?array $overrides = [],
    ): SalesChannelContext {
        $baseContext ??= Context::createDefaultContext();

        $token ??= self::TOKEN;

        $domainId ??= self::DOMAIN;

        if (!$salesChannel) {
            $salesChannel = new SalesChannelEntity();
            $salesChannel->setId(TestDefaults::SALES_CHANNEL);
            $salesChannel->setNavigationCategoryId(self::NAVIGATION_CATEGORY);
            $salesChannel->setTaxCalculationType(self::TAX_CALCULATION_TYPE);
            $salesChannel->setNavigationCategoryDepth(2);
            $salesChannel->setTypeId(Defaults::SALES_CHANNEL_TYPE_STOREFRONT);
        }

        if (!$currency) {
            $currency = new CurrencyEntity();
            $currency->setId($baseContext->getCurrencyId());
            $currency->setFactor($baseContext->getCurrencyFactor());
        }

        if (!$currentCustomerGroup) {
            $currentCustomerGroup = new CustomerGroupEntity();
            $currentCustomerGroup->setId(TestDefaults::FALLBACK_CUSTOMER_GROUP);
            $currentCustomerGroup->setDisplayGross(self::CUSTOMER_GROUP_DISPLAY_GROSS);
        }

        if (!$taxRules) {
            $tax = new TaxEntity();
            $tax->setId(self::TAX);
            $tax->setTaxRate(self::TAX_RATE);

            $taxRules = new TaxCollection([$tax]);
        }

        if (!$paymentMethod) {
            $paymentMethod = new PaymentMethodEntity();
            $paymentMethod->setId(self::PAYMENT_METHOD);
            $paymentMethod->setHandlerIdentifier(DefaultPayment::class);
            $paymentMethod->setName('Generated Payment');
            $paymentMethod->setActive(true);
        }

        $salesChannel->setPaymentMethodIds([$paymentMethod->getId()]);
        $salesChannel->setPaymentMethodId($paymentMethod->getId());
        $salesChannel->setPaymentMethod($paymentMethod);

        if (!$shippingMethod) {
            $shippingMethod = new ShippingMethodEntity();
            $shippingMethod->setId(self::SHIPPING_METHOD);
            $shippingMethod->setName('Generated Shipping');
            $shippingMethod->setActive(true);
        }

        $salesChannel->setShippingMethodId($shippingMethod->getId());
        $salesChannel->setShippingMethod($shippingMethod);

        if (!$shippingLocation) {
            if (!$country) {
                $country = new CountryEntity();
                $country->setId(self::COUNTRY);
            }

            if (!$countryState) {
                $countryState = new CountryStateEntity();
                $countryState->setId(self::COUNTRY_STATE);
                $countryState->setCountryId($country->getId());
                $countryState->setCountry($country);
            }

            if (!$customerAddress) {
                $customerAddress = new CustomerAddressEntity();
                $customerAddress->setId(self::CUSTOMER_ADDRESS);
            }

            $customerAddress->setCountryId($country->getId());
            $customerAddress->setCountry($country);
            $customerAddress->setCountryStateId($countryState->getId());
            $customerAddress->setCountryState($countryState);

            $shippingLocation = ShippingLocation::createFromAddress($customerAddress);
        }

        if (!$customer) {
            $customer = new CustomerEntity();
            $customer->setId(self::CUSTOMER);
            $customer->setGroupId($currentCustomerGroup->getId());
            $customer->setGroup($currentCustomerGroup);
            $customer->setSalesChannelId($salesChannel->getId());
            $customer->setSalesChannel($salesChannel);
            $customer->setGuest(false);
        }

        $itemRounding ??= clone $baseContext->getRounding();

        $totalRounding ??= clone $baseContext->getRounding();

        $areaRuleIds ??= [];

        $languageInfo ??= self::createLanguageInfo();

        $salesChannelContext = new SalesChannelContext(
            $baseContext,
            $token,
            $domainId,
            $salesChannel,
            $currency,
            $currentCustomerGroup,
            $taxRules,
            $paymentMethod,
            $shippingMethod,
            $shippingLocation,
            $customer,
            $itemRounding,
            $totalRounding,
            $languageInfo,
            $areaRuleIds,
        );

        if ($overrides) {
            $salesChannelContext->assign($overrides);
        }

        return $salesChannelContext;
    }

    public static function createCart(): Cart
    {
        $cart = new Cart('test');
        $cart->setLineItems(
            new LineItemCollection([
                (new LineItem('A', 'product', 'A', 27))
                    ->setPrice(new CalculatedPrice(10, 270, new CalculatedTaxCollection(), new TaxRuleCollection(), 27)),
                (new LineItem('B', 'test', 'B', 5))
                    ->setGood(false)
                    ->setPrice(new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection())),
            ])
        );
        $cart->setPrice(
            new CartPrice(
                275.0,
                275.0,
                0,
                new CalculatedTaxCollection(),
                new TaxRuleCollection(),
                CartPrice::TAX_STATE_GROSS
            )
        );

        return $cart;
    }

    public static function createCartWithDelivery(): Cart
    {
        $cart = static::createCart();

        $shippingMethod = new ShippingMethodEntity();
        $calculatedPrice = new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection());
        $deliveryDate = new DeliveryDate(new \DateTime(), new \DateTime());

        $deliveryPositionCollection = new DeliveryPositionCollection();
        foreach ($cart->getLineItems() as $lineItem) {
            $deliveryPosition = new DeliveryPosition(
                'anyIdentifier',
                $lineItem,
                $lineItem->getQuantity(),
                $calculatedPrice,
                $deliveryDate
            );

            $lineItem->setDeliveryInformation(new DeliveryInformation(1000, 10.0, false, 2, null, 10.0, 10.0, 10.0));

            $deliveryPositionCollection->add($deliveryPosition);
        }

        $delivery = new Delivery(
            $deliveryPositionCollection,
            $deliveryDate,
            $shippingMethod,
            new ShippingLocation(new CountryEntity(), null, null),
            $calculatedPrice
        );

        $cart->addDeliveries(new DeliveryCollection([$delivery]));

        return $cart;
    }

    public static function createLanguageInfo(
        ?string $id = null,
        ?string $name = null,
    ): LanguageInfo {
        return new LanguageInfo(
            $id ?? Defaults::LANGUAGE_SYSTEM,
            $name ?? self::LANGUAGE_INFO_NAME,
        );
    }
}
