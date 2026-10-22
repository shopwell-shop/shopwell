<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Wallet\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Wallet\Service\WalletSettings;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

/**
 * The defaults matter here: a shop that never opened the wallet settings has no value saved, and each
 * caller used to answer that with its own zero - which read as "the wallet is switched off".
 *
 * @internal
 */
#[Package('checkout')]
#[CoversClass(WalletSettings::class)]
class WalletSettingsTest extends TestCase
{
    public function testAnUnsavedChannelRunsTheWallet(): void
    {
        $settings = new WalletSettings(new StaticSystemConfigService());

        static::assertTrue($settings->isEnabled());
    }

    public function testADisabledChannelTurnsTheWalletOff(): void
    {
        $settings = new WalletSettings(new StaticSystemConfigService([
            WalletSettings::CONFIG_KEY_ENABLED => false,
        ]));

        static::assertFalse($settings->isEnabled());
    }

    public function testMaxPaymentPercentFallsBackToTheWholeOrder(): void
    {
        static::assertSame(100.0, (new WalletSettings(new StaticSystemConfigService()))->maxPaymentPercent());
    }

    public function testTopUpLimitsAreUnrestrictedByDefault(): void
    {
        $settings = new WalletSettings(new StaticSystemConfigService());

        static::assertSame(0.0, $settings->minTopUpAmount());
        static::assertSame(0.0, $settings->maxTopUpAmount());
    }

    public function testConfiguredValuesWin(): void
    {
        $settings = new WalletSettings(new StaticSystemConfigService([
            WalletSettings::CONFIG_KEY_MAX_PAYMENT_PERCENT => 40.0,
            WalletSettings::CONFIG_KEY_MIN_TOP_UP_AMOUNT => 10.0,
            WalletSettings::CONFIG_KEY_MAX_TOP_UP_AMOUNT => 5000.0,
        ]));

        static::assertSame(40.0, $settings->maxPaymentPercent());
        static::assertSame(10.0, $settings->minTopUpAmount());
        static::assertSame(5000.0, $settings->maxTopUpAmount());
    }

    public function testPresetsFallBackToTheSuggestedAmounts(): void
    {
        static::assertSame([100.0, 200.0, 500.0, 1000.0, 2000.0], (new WalletSettings(new StaticSystemConfigService()))->topUpPresets());
    }

    public function testPresetsAreCleanedUpAndSorted(): void
    {
        $settings = new WalletSettings(new StaticSystemConfigService([
            WalletSettings::CONFIG_KEY_TOP_UP_PRESETS => '300, 100 ,abc,, -5, 100',
        ]));

        static::assertSame([100.0, 300.0], $settings->topUpPresets());
    }

    public function testABlankPresetListIsTreatedAsUnset(): void
    {
        $settings = new WalletSettings(new StaticSystemConfigService([
            WalletSettings::CONFIG_KEY_TOP_UP_PRESETS => '   ',
        ]));

        static::assertSame([100.0, 200.0, 500.0, 1000.0, 2000.0], $settings->topUpPresets());
    }

    public function testValuesAreReadForTheGivenChannel(): void
    {
        $settings = new WalletSettings(new StaticSystemConfigService([
            'channel-a' => [WalletSettings::CONFIG_KEY_MAX_PAYMENT_PERCENT => 20.0],
        ]));

        static::assertSame(20.0, $settings->maxPaymentPercent('channel-a'));
        static::assertSame(100.0, $settings->maxPaymentPercent('channel-b'));
    }
}
