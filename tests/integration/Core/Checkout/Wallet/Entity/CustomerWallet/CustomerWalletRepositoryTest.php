<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Wallet\Entity\CustomerWallet;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWallet\CustomerWalletCollection;
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
class CustomerWalletRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<CustomerWalletCollection>
     */
    private EntityRepository $repository;

    protected function setUp(): void
    {
        $this->repository = static::getContainer()->get('customer_wallet.repository');
    }

    public function testWalletIsOpenedWithTheCustomer(): void
    {
        $context = Context::createDefaultContext();
        $customerId = $this->createCustomer();
        $id = $this->walletIdOf($customerId);

        $wallet = $this->repository->search(new Criteria([$id]), $context)->getEntities()->get($id);

        static::assertNotNull($wallet);
        static::assertSame($customerId, $wallet->getCustomerId());
        static::assertSame(Defaults::CURRENCY, $wallet->getCurrencyId());
        static::assertSame(0.0, $wallet->getBalance());
        static::assertSame(0.0, $wallet->getLockedBalance());
    }

    public function testWalletCarriesTheCurrencyAssociation(): void
    {
        $context = Context::createDefaultContext();
        $id = $this->walletIdOf($this->createCustomer());

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('currency');

        $wallet = $this->repository->search($criteria, $context)->getEntities()->get($id);

        static::assertNotNull($wallet);
        static::assertNotNull($wallet->getCurrency());
    }

    public function testWalletBalanceCanBeUpdated(): void
    {
        $context = Context::createDefaultContext();
        $id = $this->walletIdOf($this->createCustomer());

        $this->repository->update([[
            'id' => $id,
            'balance' => 125.0,
        ]], $context);

        $wallet = $this->repository->search(new Criteria([$id]), $context)->getEntities()->get($id);

        static::assertNotNull($wallet);
        static::assertSame(125.0, $wallet->getBalance());
    }

    /**
     * A customer owns a wallet in the default currency from the moment it exists, so this test reads
     * that wallet instead of opening a second one, which the unique key on customer and currency
     * would reject.
     */
    private function walletIdOf(string $customerId): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('customerId', $customerId),
            new EqualsFilter('currencyId', Defaults::CURRENCY)
        );
        $criteria->setLimit(1);

        $id = $this->repository->searchIds($criteria, Context::createDefaultContext())->firstId();
        static::assertNotNull($id);

        return $id;
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
