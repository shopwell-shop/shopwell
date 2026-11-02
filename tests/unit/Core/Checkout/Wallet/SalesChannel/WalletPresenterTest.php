<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Wallet\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWallet\CustomerWalletEntity;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWalletTopUp\CustomerWalletTopUpEntity;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWalletTransaction\CustomerWalletTransactionEntity;
use Shopwell\Core\Checkout\Wallet\SalesChannel\WalletPresenter;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(WalletPresenter::class)]
class WalletPresenterTest extends TestCase
{
    private const CURRENCY_ID = 'c1b2c3d4e5f60718293a4b5c6d7e8f90';

    private WalletPresenter $presenter;

    protected function setUp(): void
    {
        $this->presenter = new WalletPresenter();
    }

    public function testWalletKeepsTheLockedBalanceTheAdministrationShows(): void
    {
        $wallet = new CustomerWalletEntity();
        $wallet->setId('wallet-id');
        $wallet->setCurrencyId(self::CURRENCY_ID);
        $wallet->setBalance(42.5);
        $wallet->setLockedBalance(5.0);

        static::assertSame([
            'id' => 'wallet-id',
            'currencyId' => self::CURRENCY_ID,
            'balance' => 42.5,
            'lockedBalance' => 5.0,
        ], $this->presenter->wallet($wallet));
    }

    public function testTransactionCarriesEverythingAStatementLineNeeds(): void
    {
        $createdAt = new \DateTimeImmutable('2026-06-01 12:00:00');

        $transaction = new CustomerWalletTransactionEntity();
        $transaction->setId('transaction-id');
        $transaction->setType('top_up');
        $transaction->setAmount(128.0);
        $transaction->setBalanceAfter(128.0);
        $transaction->setCurrencyId(self::CURRENCY_ID);
        $transaction->setComment('goodwill');
        $transaction->setCreatedAt($createdAt);

        static::assertSame([
            'id' => 'transaction-id',
            'type' => 'top_up',
            'amount' => 128.0,
            'balanceAfter' => 128.0,
            'currencyId' => self::CURRENCY_ID,
            'orderId' => null,
            'externalReference' => null,
            'comment' => 'goodwill',
            'createdAt' => $createdAt->format(\DATE_ATOM),
        ], $this->presenter->transaction($transaction));
    }

    public function testTopUpIsPresentedWithItsStateAndChannel(): void
    {
        $topUp = new CustomerWalletTopUpEntity();
        $topUp->setId('top-up-id');
        $topUp->setAmount(128.0);
        $topUp->setCurrency('CNY');
        $topUp->setState(CustomerWalletTopUpEntity::STATE_OPEN);
        $topUp->setChannelTechnicalName('alipay_h5');

        $presented = $this->presenter->topUp($topUp);

        static::assertSame('top-up-id', $presented['id']);
        static::assertSame(CustomerWalletTopUpEntity::STATE_OPEN, $presented['state']);
        static::assertSame('alipay_h5', $presented['channel']);
        static::assertNull($presented['creditedAt']);
    }
}
