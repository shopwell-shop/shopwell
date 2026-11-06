<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Wallet\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWallet\CustomerWalletCollection;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWallet\CustomerWalletEntity;
use Shopwell\Core\Checkout\Wallet\Service\WalletAccountService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(WalletAccountService::class)]
class WalletAccountServiceTest extends TestCase
{
    private const CUSTOMER_ID = 'customer-id';

    private const CURRENCY_ID = 'currency-id';

    private const WALLET_ID = 'wallet-id';

    /**
     * @var StaticEntityRepository<CustomerWalletCollection>
     */
    private StaticEntityRepository $walletRepository;

    private WalletAccountService $accounts;

    private Context $context;

    protected function setUp(): void
    {
        $this->walletRepository = StaticEntityRepository::of(CustomerWalletCollection::class);
        $this->accounts = new WalletAccountService($this->walletRepository);
        $this->context = Context::createDefaultContext();
    }

    public function testBalanceIsZeroWithoutWallet(): void
    {
        $this->walletRepository->addSearch(new CustomerWalletCollection());

        static::assertSame(0.0, $this->accounts->balance(self::CUSTOMER_ID, self::CURRENCY_ID, $this->context));
    }

    public function testBalanceIsAnsweredFromTheWalletRow(): void
    {
        $this->walletRepository->addSearch(new CustomerWalletCollection([$this->createWallet(42.5)]));

        static::assertSame(42.5, $this->accounts->balance(self::CUSTOMER_ID, self::CURRENCY_ID, $this->context));
    }

    public function testOpenCreatesTheWalletWithAZeroBalance(): void
    {
        $this->walletRepository->addSearch(new CustomerWalletCollection());

        $this->accounts->open(self::CUSTOMER_ID, self::CURRENCY_ID, $this->context);

        $wallet = $this->walletRepository->creates[0][0];
        static::assertSame(self::CUSTOMER_ID, $wallet['customerId']);
        static::assertSame(self::CURRENCY_ID, $wallet['currencyId']);
        static::assertSame(0.0, $wallet['balance']);
        static::assertSame(0.0, $wallet['lockedBalance']);
    }

    public function testOpenLeavesAnExistingWalletUntouched(): void
    {
        $this->walletRepository->addSearch(new CustomerWalletCollection([$this->createWallet(42.5)]));

        $this->accounts->open(self::CUSTOMER_ID, self::CURRENCY_ID, $this->context);

        static::assertSame([], $this->walletRepository->creates);
        static::assertSame([], $this->walletRepository->updates);
    }

    private function createWallet(float $balance): CustomerWalletEntity
    {
        $wallet = new CustomerWalletEntity();
        $wallet->setId(self::WALLET_ID);
        $wallet->setCustomerId(self::CUSTOMER_ID);
        $wallet->setCurrencyId(self::CURRENCY_ID);
        $wallet->setBalance($balance);

        return $wallet;
    }
}
