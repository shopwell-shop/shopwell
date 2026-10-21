<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\OrderReturn\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\OrderReturn\Service\OrderReturnSettings;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

/**
 * A shop that never opened the return settings has no value saved, so every reader has to fall back
 * to the declared default. `windowDays` is the one that hurts when it is wrong: a zero reads as "no
 * returns at all" and closes the storefront without anyone noticing.
 *
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(OrderReturnSettings::class)]
class OrderReturnSettingsTest extends TestCase
{
    public function testAnUnsavedChannelAcceptsReturns(): void
    {
        $settings = new OrderReturnSettings(new StaticSystemConfigService());

        static::assertTrue($settings->isEnabled());
    }

    public function testAnUnsavedChannelAcceptsGuestReturns(): void
    {
        static::assertTrue((new OrderReturnSettings(new StaticSystemConfigService()))->allowGuest());
    }

    public function testTheWindowIsThirtyDaysWhenNothingIsSaved(): void
    {
        static::assertSame(30, (new OrderReturnSettings(new StaticSystemConfigService()))->windowDays());
    }

    public function testTheWindowCountsFromTheDeliveryByDefault(): void
    {
        static::assertTrue((new OrderReturnSettings(new StaticSystemConfigService()))->windowStartsAtDelivery());
    }

    public function testTheApprovalDeadlineIsTwoDays(): void
    {
        static::assertSame(48, (new OrderReturnSettings(new StaticSystemConfigService()))->approvalDeadlineHours());
    }

    public function testAnOverdueRequestIsNotApprovedOnItsOwn(): void
    {
        static::assertFalse((new OrderReturnSettings(new StaticSystemConfigService()))->autoApprove());
    }

    public function testTheParcelHasFourteenDaysToArrive(): void
    {
        static::assertSame(14, (new OrderReturnSettings(new StaticSystemConfigService()))->returnDeadlineDays());
    }

    public function testAcceptedGoodsArePutBackIntoTheStock(): void
    {
        static::assertTrue((new OrderReturnSettings(new StaticSystemConfigService()))->restockOnReceive());
    }

    public function testConfiguredValuesWin(): void
    {
        $settings = new OrderReturnSettings(new StaticSystemConfigService([
            OrderReturnSettings::CONFIG_KEY_ENABLED => false,
            OrderReturnSettings::CONFIG_KEY_ALLOW_GUEST => false,
            OrderReturnSettings::CONFIG_KEY_WINDOW_DAYS => 14,
            OrderReturnSettings::CONFIG_KEY_WINDOW_STARTS_AT_DELIVERY => false,
            OrderReturnSettings::CONFIG_KEY_APPROVAL_DEADLINE_HOURS => 24,
            OrderReturnSettings::CONFIG_KEY_AUTO_APPROVE => true,
            OrderReturnSettings::CONFIG_KEY_RETURN_DEADLINE_DAYS => 7,
            OrderReturnSettings::CONFIG_KEY_RESTOCK_ON_RECEIVE => false,
        ]));

        static::assertFalse($settings->isEnabled());
        static::assertFalse($settings->allowGuest());
        static::assertSame(14, $settings->windowDays());
        static::assertFalse($settings->windowStartsAtDelivery());
        static::assertSame(24, $settings->approvalDeadlineHours());
        static::assertTrue($settings->autoApprove());
        static::assertSame(7, $settings->returnDeadlineDays());
        static::assertFalse($settings->restockOnReceive());
    }

    public function testASavedZeroWindowIsKept(): void
    {
        $settings = new OrderReturnSettings(new StaticSystemConfigService([
            OrderReturnSettings::CONFIG_KEY_WINDOW_DAYS => 0,
        ]));

        static::assertSame(0, $settings->windowDays());
    }

    public function testSomethingThatIsNoNumberFallsBackToTheDefault(): void
    {
        $settings = new OrderReturnSettings(new StaticSystemConfigService([
            OrderReturnSettings::CONFIG_KEY_WINDOW_DAYS => 'thirty',
            OrderReturnSettings::CONFIG_KEY_APPROVAL_DEADLINE_HOURS => null,
        ]));

        static::assertSame(30, $settings->windowDays());
        static::assertSame(48, $settings->approvalDeadlineHours());
    }

    public function testValuesAreReadForTheGivenChannel(): void
    {
        $settings = new OrderReturnSettings(new StaticSystemConfigService([
            'channel-a' => [OrderReturnSettings::CONFIG_KEY_WINDOW_DAYS => 7],
        ]));

        static::assertSame(7, $settings->windowDays('channel-a'));
        static::assertSame(30, $settings->windowDays('channel-b'));
    }
}
