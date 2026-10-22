<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Wallet\Entity\CustomerWalletTransaction;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWalletTransaction\CustomerWalletTransactionCollection;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('checkout')]
class CustomerWalletTransactionRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<CustomerWalletTransactionCollection>
     */
    private EntityRepository $repository;

    protected function setUp(): void
    {
        $this->repository = static::getContainer()->get('customer_wallet_transaction.repository');
    }

    public function testCreateWalletTransaction(): void
    {
        $context = Context::createDefaultContext();
        $walletId = $this->createWallet();

        $id = Uuid::randomHex();

        $this->repository->create([[
            'id' => $id,
            'customerWalletId' => $walletId,
            'type' => 'top_up',
            'amount' => 25.0,
            'balanceAfter' => 25.0,
            'currencyId' => Defaults::CURRENCY,
            'externalReference' => 'ref-1',
        ]], $context);

        $transaction = $this->repository->search(new Criteria([$id]), $context)->getEntities()->get($id);

        static::assertNotNull($transaction);
        static::assertSame($walletId, $transaction->getCustomerWalletId());
        static::assertSame('top_up', $transaction->getType());
        static::assertSame(25.0, $transaction->getAmount());
        static::assertSame(25.0, $transaction->getBalanceAfter());
        static::assertSame(Defaults::CURRENCY, $transaction->getCurrencyId());
        static::assertSame('ref-1', $transaction->getExternalReference());
    }

    public function testCreateWalletTransactionWithWalletAssociation(): void
    {
        $context = Context::createDefaultContext();
        $walletId = $this->createWallet();

        $id = Uuid::randomHex();

        $this->repository->create([[
            'id' => $id,
            'customerWalletId' => $walletId,
            'type' => 'consume',
            'amount' => -10.0,
            'balanceAfter' => 15.0,
            'currencyId' => Defaults::CURRENCY,
            'comment' => 'paid with wallet',
        ]], $context);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('wallet');

        $transaction = $this->repository->search($criteria, $context)->getEntities()->get($id);

        static::assertNotNull($transaction);
        static::assertNotNull($transaction->getWallet());
        static::assertSame($walletId, $transaction->getWallet()->getId());
        static::assertSame('paid with wallet', $transaction->getComment());
    }

    /**
     * A customer owns a wallet in the default currency from the moment it exists, so the transactions
     * are booked onto that wallet instead of a second one, which the unique key on customer and
     * currency would reject.
     */
    private function createWallet(): string
    {
        $customerId = $this->createCustomer();

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
            'gender' => $this->getValidGender(),
            'customerNumber' => '12345',
        ]], Context::createDefaultContext());

        return $customerId;
    }
}
