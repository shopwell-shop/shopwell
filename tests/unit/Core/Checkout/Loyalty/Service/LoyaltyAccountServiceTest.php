<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Loyalty\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerCollection;
use Shopwell\Core\Checkout\Ledger\RowLock;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyAccount\LoyaltyAccountCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyAccount\LoyaltyAccountEntity;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier\LoyaltyTierCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier\LoyaltyTierEntity;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyAccountService;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltySettings;
use Shopwell\Core\Checkout\Loyalty\Service\TierResolver;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(LoyaltyAccountService::class)]
class LoyaltyAccountServiceTest extends TestCase
{
    private const CUSTOMER_ID = 'customer-id';

    private const NOW = '2025-01-01 00:00:00';

    /**
     * @var StaticEntityRepository<LoyaltyAccountCollection>
     */
    private StaticEntityRepository $loyaltyAccountRepository;

    /**
     * @var StaticEntityRepository<LoyaltyTierCollection>
     */
    private StaticEntityRepository $tierRepository;

    /**
     * @var StaticEntityRepository<CustomerCollection>
     */
    private StaticEntityRepository $customerRepository;

    private RowLock&Stub $rows;

    private LoyaltyAccountService $accounts;

    private Context $context;

    protected function setUp(): void
    {
        $this->loyaltyAccountRepository = StaticEntityRepository::of(LoyaltyAccountCollection::class);
        $this->tierRepository = StaticEntityRepository::of(LoyaltyTierCollection::class);
        $this->customerRepository = StaticEntityRepository::of(CustomerCollection::class);
        $this->rows = static::createStub(RowLock::class);
        $this->context = Context::createDefaultContext();

        $this->accounts = $this->service();
    }

    public function testAnAccountOfACustomerIsAnswered(): void
    {
        $account = new LoyaltyAccountEntity();
        $account->setId('account-id');
        $account->setPointsBalance(250);

        $this->loyaltyAccountRepository->addSearch(new LoyaltyAccountCollection([$account]));

        static::assertSame($account, $this->accounts->account(self::CUSTOMER_ID, $this->context));
    }

    public function testACustomerWithoutAnAccountAnswersNothing(): void
    {
        $this->loyaltyAccountRepository->addSearch(new LoyaltyAccountCollection());

        static::assertNull($this->accounts->account(self::CUSTOMER_ID, $this->context));
    }

    public function testBalanceIsZeroWithoutAnAccount(): void
    {
        $this->loyaltyAccountRepository->addSearch(new LoyaltyAccountCollection());

        static::assertSame(0, $this->accounts->balance(self::CUSTOMER_ID, $this->context));
    }

    public function testBalanceAnswersTheProjectedBalance(): void
    {
        $account = new LoyaltyAccountEntity();
        $account->setId('account-id');
        $account->setPointsBalance(250);

        $this->loyaltyAccountRepository->addSearch(new LoyaltyAccountCollection([$account]));

        static::assertSame(250, $this->accounts->balance(self::CUSTOMER_ID, $this->context));
    }

    public function testOpenWritesAnAccountInTheTierThatZeroTierPointsUnlocks(): void
    {
        $this->loyaltyAccountRepository->addSearch(new LoyaltyAccountCollection());
        $this->tierRepository->addSearch(new LoyaltyTierCollection([$this->createTier('bronze', 1, 0)]));

        $rows = $this->createMock(RowLock::class);
        $rows->expects($this->once())
            ->method('createUnlessExists')
            ->with(
                $this->loyaltyAccountRepository,
                static::callback(static fn (array $row): bool => $row['customerId'] === self::CUSTOMER_ID
                    && $row['pointsBalance'] === 0
                    && $row['tierId'] === 'bronze'),
                $this->context,
            );

        $this->service($rows)->open(self::CUSTOMER_ID, $this->context);
    }

    /**
     * The account of a customer that already owns one is left alone - opening it again must not reset
     * a balance or a paid tier.
     */
    public function testOpenLeavesAnExistingAccountUntouched(): void
    {
        $account = new LoyaltyAccountEntity();
        $account->setId('account-id');

        $this->loyaltyAccountRepository->addSearch(new LoyaltyAccountCollection([$account]));

        $rows = $this->createMock(RowLock::class);
        $rows->expects($this->never())->method('createUnlessExists');

        $this->service($rows)->open(self::CUSTOMER_ID, $this->context);
    }

    /**
     * The service under test with the row lock the test needs: opening an account is the one call that
     * goes through it, so the tests that assert on it bring their own double.
     */
    private function service(?RowLock $rows = null): LoyaltyAccountService
    {
        return new LoyaltyAccountService(
            $this->loyaltyAccountRepository,
            new TierResolver(
                $this->tierRepository,
                $this->loyaltyAccountRepository,
                $this->customerRepository,
                new LoyaltySettings(new StaticSystemConfigService()),
                new EventDispatcher(),
                new MockClock(self::NOW),
            ),
            $rows ?? $this->rows,
        );
    }

    private function createTier(string $technicalName, int $level, ?int $minTierPoints): LoyaltyTierEntity
    {
        $tier = new LoyaltyTierEntity();
        $tier->setId($technicalName);
        $tier->setTechnicalName($technicalName);
        $tier->setLevel($level);
        $tier->setMinTierPoints($minTierPoints);
        $tier->setActive(true);

        return $tier;
    }
}
