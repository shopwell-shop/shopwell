<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Wallet\Service;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnEntity;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWalletTransaction\CustomerWalletTransactionCollection;
use Shopwell\Core\Checkout\Wallet\Service\WalletPaymentService;
use Shopwell\Core\Checkout\Wallet\Service\WalletRefundSource;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Currency\CurrencyEntity;
use Shopwell\Core\Test\TestDefaults;

/**
 * The split of a return is read from the ledger with a real query, not from a stub that answers the
 * filters it is given - a filter on the wrong field would pass a unit test and find nothing here.
 *
 * @internal
 */
#[Package('checkout')]
class WalletRefundSourceTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<CustomerWalletTransactionCollection>
     */
    private EntityRepository $transactionRepository;

    protected function setUp(): void
    {
        $this->transactionRepository = static::getContainer()->get('customer_wallet_transaction.repository');
    }

    public function testRefundableAmountIsTheLedgerOfThatOneOrder(): void
    {
        $context = Context::createDefaultContext();
        $customerId = $this->createCustomer();
        $walletId = $this->walletIdOf($customerId);
        $orderId = Uuid::randomHex();

        $this->book($walletId, 'consume', -100.0, $orderId, $context);
        $this->book($walletId, 'refund', 30.0, $orderId, $context);
        // another order of the same wallet must not be part of this return
        $this->book($walletId, 'consume', -50.0, Uuid::randomHex(), $context);

        $source = new WalletRefundSource($this->transactionRepository, $this->createMock(WalletPaymentService::class));

        static::assertSame(7000, $source->refundableAmount($this->orderReturn($customerId), $this->order($orderId), $context));
    }

    public function testAnOrderThatNeverUsedTheWalletRefundsNothing(): void
    {
        $context = Context::createDefaultContext();
        $customerId = $this->createCustomer();

        $source = new WalletRefundSource($this->transactionRepository, $this->createMock(WalletPaymentService::class));

        static::assertSame(0, $source->refundableAmount($this->orderReturn($customerId), $this->order(Uuid::randomHex()), $context));
    }

    private function book(string $walletId, string $type, float $amount, string $orderId, Context $context): void
    {
        $this->transactionRepository->create([[
            'id' => Uuid::randomHex(),
            'customerWalletId' => $walletId,
            'type' => $type,
            'amount' => $amount,
            'balanceAfter' => $amount,
            'currencyId' => Defaults::CURRENCY,
            'orderId' => $orderId,
        ]], $context);
    }

    private function orderReturn(string $customerId): OrderReturnEntity
    {
        return (new OrderReturnEntity())->assign([
            'id' => 'return-id',
            'returnNumber' => 'RT-1',
            'customerId' => $customerId,
            'currency' => $this->currency()->getIsoCode(),
            'refundAmount' => 1999,
        ]);
    }

    private function order(string $orderId): OrderEntity
    {
        return (new OrderEntity())->assign([
            'id' => $orderId,
            'currencyId' => Defaults::CURRENCY,
        ]);
    }

    private function currency(): CurrencyEntity
    {
        $currency = static::getContainer()->get('currency.repository')
            ->search(new Criteria([Defaults::CURRENCY]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertInstanceOf(CurrencyEntity::class, $currency);

        return $currency;
    }

    private function walletIdOf(string $customerId): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('customerId', $customerId),
            new EqualsFilter('currencyId', Defaults::CURRENCY)
        );
        $criteria->setLimit(1);

        $walletId = static::getContainer()->get('customer_wallet.repository')
            ->searchIds($criteria, Context::createDefaultContext())
            ->firstId();

        static::assertNotNull($walletId);

        return $walletId;
    }

    private function createCustomer(): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        static::getContainer()->get('customer.repository')->create([[
            'id' => $customerId,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'defaultShippingAddress' => [
                'id' => $addressId,
                'name' => 'Max Mustermann',
                'street' => 'Musterstraße 1',
                'city' => 'Schöppingen',
                'zipcode' => '12345',
                'gender' => $this->getValidGender(),
                'countryId' => $this->getValidCountryId(),
            ],
            'defaultBillingAddressId' => $addressId,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => Uuid::randomHex() . '@example.com',
            'password' => TestDefaults::HASHED_PASSWORD,
            'name' => 'Max Mustermann',
            'customerNumber' => '12345',
        ]], Context::createDefaultContext());

        return $customerId;
    }
}
