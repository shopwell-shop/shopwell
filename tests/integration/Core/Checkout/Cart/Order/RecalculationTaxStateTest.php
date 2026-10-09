<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Cart\Order;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Order\RecalculationService;
use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopwell\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopwell\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopwell\Core\Checkout\Order\OrderCollection;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\Order\OrderStates;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopwell\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('checkout')]
class RecalculationTaxStateTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Context $context;

    /**
     * @var EntityRepository<OrderCollection>
     */
    private EntityRepository $orderRepository;

    private string $germanyId;

    private string $austriaId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = Context::createDefaultContext();
        $this->orderRepository = static::getContainer()->get('order.repository');
        $this->germanyId = $this->getCountryIdByIso('DE');
        $this->austriaId = $this->getCountryIdByIso('AT');

        // Companies are tax-free in Austria
        static::getContainer()->get('country.repository')->update([[
            'id' => $this->austriaId,
            'companyTax' => ['enabled' => true, 'currencyId' => Defaults::CURRENCY, 'amount' => 0.0],
            'checkVatIdPattern' => false,
        ]], $this->context);
    }

    public function testGermanOrderStaysTaxableAfterTheMatchingCustomerAddressWasEdited(): void
    {
        $austrianAddressId = Uuid::randomHex();
        $germanAddressId = Uuid::randomHex();
        $customerId = $this->createBusinessCustomer(
            defaultAddress: $this->getAddressData($austrianAddressId, $this->austriaId, 'Getreidegasse 9', '5020', 'Salzburg'),
            otherAddress: $this->getAddressData($germanAddressId, $this->germanyId, 'Ebbinghoff 10', '48624', 'Schöppingen'),
        );
        $orderId = $this->createOrder(
            $customerId,
            $this->getAddressData(Uuid::randomHex(), $this->germanyId, 'Ebbinghoff 10', '48624', 'Schöppingen'),
        );

        // The customer's German address no longer matches the order address
        static::getContainer()->get('customer_address.repository')->update([[
            'id' => $germanAddressId,
            'street' => 'Ebbinghoff 11',
        ]], $this->context);

        $versionContext = $this->context->createWithVersionId($this->orderRepository->createVersion($orderId, $this->context));
        static::getContainer()->get(RecalculationService::class)->recalculate($orderId, $versionContext);

        $order = $this->orderRepository->search(new Criteria([$orderId]), $versionContext)->getEntities()->first();
        static::assertInstanceOf(OrderEntity::class, $order);
        static::assertNotSame(CartPrice::TAX_STATE_FREE, $order->getTaxStatus());
        static::assertGreaterThan(0.0, $order->getPrice()->getCalculatedTaxes()->getAmount());
    }

    public function testRecalculatingTwoOrdersWithTheSameAddressKeepsTheCustomerAddressesUntouched(): void
    {
        $austrianAddressId = Uuid::randomHex();
        $germanAddressId = Uuid::randomHex();
        $customerId = $this->createBusinessCustomer(
            defaultAddress: $this->getAddressData($austrianAddressId, $this->austriaId, 'Getreidegasse 9', '5020', 'Salzburg'),
            otherAddress: $this->getAddressData($germanAddressId, $this->germanyId, 'Ebbinghoff 10', '48624', 'Schöppingen'),
        );
        $firstOrderAddressId = Uuid::randomHex();
        $secondOrderAddressId = Uuid::randomHex();
        $orderIds = [
            $this->createOrder($customerId, $this->getAddressData($firstOrderAddressId, $this->germanyId, 'Ebbinghoff 10', '48624', 'Schöppingen')),
            $this->createOrder($customerId, $this->getAddressData($secondOrderAddressId, $this->germanyId, 'Ebbinghoff 10', '48624', 'Schöppingen')),
        ];

        static::getContainer()->get('customer_address.repository')->update([[
            'id' => $germanAddressId,
            'street' => 'Ebbinghoff 11',
        ]], $this->context);

        foreach ($orderIds as $orderId) {
            $versionContext = $this->context->createWithVersionId($this->orderRepository->createVersion($orderId, $this->context));
            static::getContainer()->get(RecalculationService::class)->recalculate($orderId, $versionContext);

            $criteria = (new Criteria([$orderId]))->addAssociation('billingAddress');
            $order = $this->orderRepository->search($criteria, $versionContext)->getEntities()->first();
            static::assertInstanceOf(OrderEntity::class, $order);
            static::assertNotSame(CartPrice::TAX_STATE_FREE, $order->getTaxStatus());
            static::assertSame('Ebbinghoff 10', $order->getBillingAddress()?->getStreet());
        }

        /** @var EntityRepository<CustomerAddressCollection> $customerAddressRepository */
        $customerAddressRepository = static::getContainer()->get('customer_address.repository');
        $customerAddresses = $customerAddressRepository
            ->search((new Criteria())->addFilter(new EqualsFilter('customerId', $customerId)), $this->context)
            ->getEntities();
        static::assertEqualsCanonicalizing([$austrianAddressId, $germanAddressId], array_values($customerAddresses->getIds()));
        static::assertSame('Ebbinghoff 11', $customerAddresses->get($germanAddressId)?->getStreet());
        static::assertSame('Getreidegasse 9', $customerAddresses->get($austrianAddressId)?->getStreet());
    }

    /**
     * @param array<string, string> $defaultAddress
     * @param array<string, string> $otherAddress
     */
    private function createBusinessCustomer(array $defaultAddress, array $otherAddress): string
    {
        $customerId = Uuid::randomHex();

        static::getContainer()->get('customer.repository')->create([[
            'id' => $customerId,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'defaultBillingAddressId' => $defaultAddress['id'],
            'defaultShippingAddressId' => $defaultAddress['id'],
            'customerNumber' => 'CUSTOMER-1',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'email' => $customerId . '@example.com',
            'password' => TestDefaults::HASHED_PASSWORD,
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'company' => 'Shopwell',
            'vatIds' => ['ATU12345678'],
            'addresses' => [
                ['customerId' => $customerId, ...$defaultAddress],
                ['customerId' => $customerId, ...$otherAddress],
            ],
        ]], $this->context);

        return $customerId;
    }

    /**
     * @param array<string, string> $address
     */
    private function createOrder(string $customerId, array $address): string
    {
        $orderId = Uuid::randomHex();
        $deliveryId = Uuid::randomHex();
        $lineItemId = Uuid::randomHex();
        $stateIdLoader = static::getContainer()->get(InitialStateIdLoader::class);
        $rounding = ['decimals' => 2, 'interval' => 0.01, 'roundForNet' => true];
        $taxRules = new TaxRuleCollection([new TaxRule(19)]);

        $this->orderRepository->create([[
            'id' => $orderId,
            'orderNumber' => Uuid::randomHex(),
            'price' => new CartPrice(100, 119, 100, new CalculatedTaxCollection(), $taxRules, CartPrice::TAX_STATE_NET),
            'shippingCosts' => new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'stateId' => $stateIdLoader->get(OrderStates::STATE_MACHINE),
            'currencyId' => Defaults::CURRENCY,
            'currencyFactor' => 1,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'itemRounding' => $rounding,
            'totalRounding' => $rounding,
            'billingAddressId' => $address['id'],
            'addresses' => [$address],
            'orderCustomer' => [
                'customerId' => $customerId,
                'email' => 'test@example.com',
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'salutationId' => $this->getValidSalutationId(),
            ],
            'lineItems' => [[
                'id' => $lineItemId,
                'identifier' => $lineItemId,
                'quantity' => 1,
                'type' => 'custom',
                'label' => 'Test',
                'price' => new CalculatedPrice(100, 100, new CalculatedTaxCollection(), $taxRules),
                'priceDefinition' => new QuantityPriceDefinition(100, $taxRules),
            ]],
            'deliveries' => [[
                'id' => $deliveryId,
                'stateId' => $stateIdLoader->get(OrderDeliveryStates::STATE_MACHINE),
                'shippingMethodId' => $this->getValidShippingMethodId(),
                'shippingOrderAddressId' => $address['id'],
                'shippingCosts' => new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
                'shippingDateEarliest' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT),
                'shippingDateLatest' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT),
            ]],
        ]], $this->context);

        $this->orderRepository->update([['id' => $orderId, 'primaryOrderDeliveryId' => $deliveryId]], $this->context);

        return $orderId;
    }

    /**
     * @return array<string, string>
     */
    private function getAddressData(string $id, string $countryId, string $street, string $zipcode, string $city): array
    {
        return [
            'id' => $id,
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'company' => 'Shopwell',
            'street' => $street,
            'zipcode' => $zipcode,
            'city' => $city,
            'countryId' => $countryId,
        ];
    }

    private function getCountryIdByIso(string $iso): string
    {
        $countryId = static::getContainer()->get('country.repository')
            ->searchIds((new Criteria())->addFilter(new EqualsFilter('iso', $iso)), $this->context)
            ->firstId();
        static::assertIsString($countryId);

        return $countryId;
    }
}
