<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Loyalty\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltySettings;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

/**
 * The defaults matter here: a shop that never opened the membership settings has no value saved, and
 * reading those as zero made a point worth nothing and the redemption cover 0 % of an order.
 *
 * @internal
 */
#[Package('checkout')]
#[CoversClass(LoyaltySettings::class)]
class LoyaltySettingsTest extends TestCase
{
    public function testAnUnsavedChannelRunsTheMembership(): void
    {
        static::assertTrue((new LoyaltySettings(new StaticSystemConfigService()))->isEnabled());
    }

    public function testADisabledChannelTurnsTheMembershipOff(): void
    {
        $settings = new LoyaltySettings(new StaticSystemConfigService([
            LoyaltySettings::CONFIG_KEY_ENABLED => false,
        ]));

        static::assertFalse($settings->isEnabled());
    }

    public function testAnUnsavedShopEarnsAndRedeemsWithTheDefaultsTheSettingsCardShows(): void
    {
        $settings = new LoyaltySettings(new StaticSystemConfigService());

        static::assertSame(1, $settings->pointsPerCurrencyUnit());
        static::assertSame(1, $settings->growthPerCurrencyUnit());
        static::assertSame(0.01, $settings->pointValueInCurrency());
        static::assertSame(100, $settings->minRedeemablePoints());
        static::assertSame(50.0, $settings->maxRedeemablePercent());
        static::assertSame(365, $settings->pointsValidityDays());
        static::assertFalse($settings->tierAutoGroupSync());
    }

    public function testConfiguredValuesWin(): void
    {
        $settings = new LoyaltySettings(new StaticSystemConfigService([
            LoyaltySettings::CONFIG_KEY_POINTS_PER_CURRENCY_UNIT => 2,
            LoyaltySettings::CONFIG_KEY_GROWTH_PER_CURRENCY_UNIT => 3,
            LoyaltySettings::CONFIG_KEY_POINT_VALUE_IN_CURRENCY => 0.05,
            LoyaltySettings::CONFIG_KEY_MIN_REDEEMABLE_POINTS => 10,
            LoyaltySettings::CONFIG_KEY_MAX_REDEEMABLE_PERCENT => 80.0,
            LoyaltySettings::CONFIG_KEY_POINTS_VALIDITY_DAYS => 0,
            LoyaltySettings::CONFIG_KEY_TIER_AUTO_GROUP_SYNC => true,
        ]));

        static::assertSame(2, $settings->pointsPerCurrencyUnit());
        static::assertSame(3, $settings->growthPerCurrencyUnit());
        static::assertSame(0.05, $settings->pointValueInCurrency());
        static::assertSame(10, $settings->minRedeemablePoints());
        static::assertSame(80.0, $settings->maxRedeemablePercent());
        static::assertSame(0, $settings->pointsValidityDays());
        static::assertTrue($settings->tierAutoGroupSync());
    }

    public function testValuesAreReadForTheGivenChannel(): void
    {
        $settings = new LoyaltySettings(new StaticSystemConfigService([
            'channel-a' => [LoyaltySettings::CONFIG_KEY_MAX_REDEEMABLE_PERCENT => 100.0],
        ]));

        static::assertSame(100.0, $settings->maxRedeemablePercent('channel-a'));
        static::assertSame(50.0, $settings->maxRedeemablePercent('channel-b'));
    }
}
