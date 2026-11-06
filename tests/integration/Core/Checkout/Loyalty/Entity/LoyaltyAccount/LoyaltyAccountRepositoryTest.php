<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Loyalty\Entity\LoyaltyAccount;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyAccount\LoyaltyAccountCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyAccount\LoyaltyAccountEntity;
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
class LoyaltyAccountRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<LoyaltyAccountCollection>
     */
    private EntityRepository $repository;

    protected function setUp(): void
    {
        $this->repository = static::getContainer()->get('loyalty_account.repository');
    }

    public function testMembershipIsOpenedWithTheCustomer(): void
    {
        $context = Context::createDefaultContext();
        $customerId = $this->createCustomer();
        $id = $this->membershipIdOf($customerId);

        $loyalty = $this->repository->search(new Criteria([$id]), $context)->getEntities()->get($id);

        static::assertNotNull($loyalty);
        static::assertSame($customerId, $loyalty->getCustomerId());
        static::assertSame(0, $loyalty->getPointsBalance());
        static::assertSame(0, $loyalty->getTierPoints());
    }

    public function testMembershipCarriesTheTier(): void
    {
        $context = Context::createDefaultContext();
        $customerId = $this->createCustomer();
        $tierId = $this->createTier();

        $id = $this->membershipIdOf($customerId);
        $validUntil = new \DateTime('2026-01-01');

        $this->repository->update([[
            'id' => $id,
            'pointsBalance' => 500,
            'tierPoints' => 5000,
            'tierId' => $tierId,
            'membershipPaidUntil' => $validUntil,
        ]], $context);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('tier');

        $loyalty = $this->repository->search($criteria, $context)->getEntities()->get($id);

        static::assertNotNull($loyalty);
        static::assertSame($tierId, $loyalty->getTierId());
        static::assertNotNull($loyalty->getTier());
        static::assertSame('Test Tier', $loyalty->getTier()->getName());
        static::assertSame($validUntil->format('Y-m-d'), $loyalty->getMembershipPaidUntil()?->format('Y-m-d'));
    }

    public function testLoyaltyBalanceCanBeUpdated(): void
    {
        $context = Context::createDefaultContext();
        $id = $this->membershipIdOf($this->createCustomer());

        $this->repository->update([[
            'id' => $id,
            'pointsBalance' => 175,
        ]], $context);

        $loyalty = $this->repository->search(new Criteria([$id]), $context)->getEntities()->get($id);

        static::assertNotNull($loyalty);
        static::assertSame(175, $loyalty->getPointsBalance());
    }

    /**
     * The point account is one row per customer, and everything that opens an account relies on the
     * database refusing a second one: `LoyaltyAccountService::open()` treats that rejection as "somebody
     * else was faster" instead of booking into a duplicate. Without the unique key the protection is
     * only a comment, so the constraint itself is pinned here.
     */
    public function testACustomerCannotOwnTwoPointAccounts(): void
    {
        $context = Context::createDefaultContext();
        $customerId = $this->createCustomer();

        $this->expectException(UniqueConstraintViolationException::class);

        $this->repository->create([[
            'id' => Uuid::randomHex(),
            'customerId' => $customerId,
        ]], $context);
    }

    /**
     * The reverse side is to-one: a customer has one account, so reading it must not hand back a
     * collection that the caller has to unwrap.
     */
    public function testTheCustomerCarriesItsPointAccountAsASingleToOne(): void
    {
        $context = Context::createDefaultContext();
        $customerId = $this->createCustomer();

        $criteria = new Criteria([$customerId]);
        $criteria->addAssociation('loyaltyAccount');

        $customer = static::getContainer()->get('customer.repository')
            ->search($criteria, $context)
            ->getEntities()
            ->get($customerId);

        static::assertInstanceOf(CustomerEntity::class, $customer);
        static::assertInstanceOf(LoyaltyAccountEntity::class, $customer->getLoyaltyAccount());
        static::assertSame($customerId, $customer->getLoyaltyAccount()->getCustomerId());
    }

    /**
     * A customer owns a point account from the moment it exists, so this test reads that account
     * instead of opening a second one, which the unique key on the customer would reject.
     */
    private function membershipIdOf(string $customerId): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));
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

    private function createTier(): string
    {
        $tierId = Uuid::randomHex();

        static::getContainer()->get('loyalty_tier.repository')->create([[
            'id' => $tierId,
            'technicalName' => 'test-tier',
            'level' => 1,
            'active' => true,
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['name' => 'Test Tier'],
            ],
        ]], Context::createDefaultContext());

        return $tierId;
    }
}
