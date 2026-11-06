<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Loyalty\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyBenefitResolver;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyPointService;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltySettings;
use Shopwell\Core\Checkout\Loyalty\Subscriber\WalletTopUpLoyaltySubscriber;
use Shopwell\Core\Checkout\Wallet\Event\WalletTopUpCreditedEvent;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

/**
 * Paying money into the wallet is the second way to earn points, and it is the only one that can be
 * switched off completely: a shop that never touched the setting pays nothing, and the money still
 * lands on the wallet.
 *
 * @internal
 */
#[Package('checkout')]
#[CoversClass(WalletTopUpLoyaltySubscriber::class)]
class WalletTopUpLoyaltySubscriberTest extends TestCase
{
    public function testItListensToTheCreditedTopUp(): void
    {
        static::assertSame(
            [WalletTopUpCreditedEvent::class => 'onTopUpCredited'],
            WalletTopUpLoyaltySubscriber::getSubscribedEvents()
        );
    }

    public function testATopUpPaysTheConfiguredRateWithTheTierMultiplier(): void
    {
        $customerId = Uuid::randomHex();
        $topUpId = Uuid::randomHex();

        $points = $this->createMock(LoyaltyPointService::class);
        $points->expects($this->once())
            ->method('award')
            ->with($customerId, 192, null, $topUpId . '-bonus', static::isInstanceOf(Context::class));

        $this->subscriber($points, [LoyaltySettings::CONFIG_KEY_TOP_UP_POINTS_PER_CURRENCY_UNIT => 1], 1.5)
            ->onTopUpCredited($this->event($topUpId, $customerId, 128.0));
    }

    public function testTheBonusIsOffWhenTheShopNeverConfiguredIt(): void
    {
        $points = $this->createMock(LoyaltyPointService::class);
        $points->expects($this->never())->method('award');

        $this->subscriber($points, [])
            ->onTopUpCredited($this->event(Uuid::randomHex(), Uuid::randomHex(), 128.0));
    }

    public function testADisabledMembershipPaysNothing(): void
    {
        $points = $this->createMock(LoyaltyPointService::class);
        $points->expects($this->never())->method('award');

        $this->subscriber($points, [
            LoyaltySettings::CONFIG_KEY_ENABLED => false,
            LoyaltySettings::CONFIG_KEY_TOP_UP_POINTS_PER_CURRENCY_UNIT => 1,
        ])->onTopUpCredited($this->event(Uuid::randomHex(), Uuid::randomHex(), 128.0));
    }

    public function testTheBonusIsFlooredSoItNeverPaysMoreThanTheRate(): void
    {
        $topUpId = Uuid::randomHex();

        $points = $this->createMock(LoyaltyPointService::class);
        $points->expects($this->once())
            ->method('award')
            ->with(static::anything(), 99, null, $topUpId . '-bonus', static::anything());

        $this->subscriber($points, [LoyaltySettings::CONFIG_KEY_TOP_UP_POINTS_PER_CURRENCY_UNIT => 1])
            ->onTopUpCredited($this->event($topUpId, Uuid::randomHex(), 99.9));
    }

    public function testATopUpBelowOnePointBooksNothing(): void
    {
        $points = $this->createMock(LoyaltyPointService::class);
        $points->expects($this->never())->method('award');

        $this->subscriber($points, [LoyaltySettings::CONFIG_KEY_TOP_UP_POINTS_PER_CURRENCY_UNIT => 1])
            ->onTopUpCredited($this->event(Uuid::randomHex(), Uuid::randomHex(), 0.9));
    }

    /**
     * The credit is committed before this runs, so a failure here cannot undo it - and throwing into
     * the payment notification would only make the gateway report the payment again, which answers
     * "already settled" and loses the bonus for good.
     */
    public function testABrokenBookingIsReportedInsteadOfFailingTheNotification(): void
    {
        $points = $this->createMock(LoyaltyPointService::class);
        $points->expects($this->once())
            ->method('award')
            ->willThrowException(new \RuntimeException('the ledger is down'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $benefits = static::createStub(LoyaltyBenefitResolver::class);
        $benefits->method('earnMultiplier')->willReturn(1.0);

        $subscriber = new WalletTopUpLoyaltySubscriber(
            $points,
            new LoyaltySettings(new StaticSystemConfigService([
                LoyaltySettings::CONFIG_KEY_TOP_UP_POINTS_PER_CURRENCY_UNIT => 1,
            ])),
            $benefits,
            $logger,
        );

        $subscriber->onTopUpCredited($this->event(Uuid::randomHex(), Uuid::randomHex(), 128.0));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function subscriber(LoyaltyPointService $points, array $config, float $multiplier = 1.0): WalletTopUpLoyaltySubscriber
    {
        $benefits = static::createStub(LoyaltyBenefitResolver::class);
        $benefits->method('earnMultiplier')->willReturn($multiplier);

        return new WalletTopUpLoyaltySubscriber(
            $points,
            new LoyaltySettings(new StaticSystemConfigService($config)),
            $benefits,
            static::createStub(LoggerInterface::class),
        );
    }

    private function event(string $topUpId, string $customerId, float $amount): WalletTopUpCreditedEvent
    {
        return new WalletTopUpCreditedEvent($topUpId, $customerId, 'CNY', $amount, Context::createDefaultContext());
    }
}
