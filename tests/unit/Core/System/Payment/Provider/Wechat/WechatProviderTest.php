<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Wechat;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\PaymentProviderFactory;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatApi;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatCaptureAction;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatPayParamsDecoder;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatProvider;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatTransferNotificationVerifier;
use Shopwell\Tests\Unit\Core\System\Payment\Provider\Fixtures\PlatformProviders;

/**
 * Guards the declaration of the wechat pay interface.
 *
 * The `appid` is the interesting part: it is a parameter of the channel and not a credential, because
 * every product is bound to a different appid while all of them share the merchant id and its keys.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(WechatProvider::class)]
class WechatProviderTest extends TestCase
{
    public function testNamesItself(): void
    {
        $provider = PlatformProviders::wechat();

        static::assertSame('wechat', $provider->getName());
        static::assertSame('sw-settings-payment-provider.provider.wechat', $provider->getLabel());
        static::assertSame(WechatApi::class, $provider->getApiClass());
        static::assertNull($provider->getSourceName());
    }

    public function testOffersTheSevenActionsOfTheInterface(): void
    {
        $provider = PlatformProviders::wechat();

        static::assertSame(
            ['capture', 'refund', 'notification', 'query', 'close', 'transfer', 'transfer_query'],
            array_map(static fn ($action): string => $action->capability()->value, $provider->getActions())
        );
        static::assertInstanceOf(WechatCaptureAction::class, $provider->getCaptureAction());
        static::assertInstanceOf(WechatPayParamsDecoder::class, $provider->getPayParamsDecoder());
        static::assertInstanceOf(WechatTransferNotificationVerifier::class, $provider->getTransferNotificationVerifier());
    }

    public function testDeclaresTheCapabilitiesWithoutARequestOfTheirOwn(): void
    {
        static::assertSame(
            [
                PaymentCapability::PARTIAL_REFUND,
                PaymentCapability::MANDATE,
                PaymentCapability::TRANSFER_NOTIFICATION,
            ],
            PlatformProviders::wechat()->getDeclaredCapabilities()
        );
    }

    public function testDeclaresTheMerchantCredentialsButNoAppid(): void
    {
        $fields = PlatformProviders::wechat()->getCredentials();

        static::assertSame(
            ['mch_id', 'mch_serial', 'mch_private_key', 'apiv3_key', 'platform_serial', 'platform_cert', 'platform_certs'],
            array_map(static fn ($field): string => $field->name, $fields)
        );
        static::assertNotContains('appid', array_map(static fn ($field): string => $field->name, $fields));

        static::assertTrue($fields[0]->required);
        static::assertTrue($fields[2]->secret);
        static::assertTrue($fields[3]->secret);
        static::assertTrue($fields[3]->required);
        static::assertSame('password', $fields[3]->type);
        static::assertNotNull($fields[6]->helpText, 'The rotation hint of the platform certificates has to survive.');
    }

    public function testDeclaresTheFiveChannelsWithTheirAppid(): void
    {
        $channels = PlatformProviders::wechat()->getChannels();

        static::assertSame(
            ['wechat_h5', 'wechat_native', 'wechat_jsapi', 'wechat_mini', 'wechat_app'],
            array_map(static fn ($channel): string => $channel->technicalName, $channels)
        );

        foreach ($channels as $channel) {
            static::assertSame('wechat', $channel->providerTechnicalName);
            static::assertSame(['CNY'], $channel->currencies);
            static::assertContains('appid', array_map(static fn ($field): string => $field->name, $channel->params));
        }

        static::assertSame(PaymentFlow::REDIRECT, $channels[0]->flow);
        static::assertSame(['wap'], $channels[0]->devices);
        static::assertSame(PaymentFlow::QRCODE, $channels[1]->flow);
        static::assertSame(['openid' => true], $channels[2]->runtimeParams);
        static::assertSame(['openid' => true], $channels[3]->runtimeParams);
        static::assertSame(['app'], $channels[4]->devices);
        static::assertSame([], $channels[4]->runtimeParams, 'The app product brings its own identity.');
    }

    public function testDeclaresWhatItPaysOutTo(): void
    {
        $rules = PlatformProviders::wechat()->getTransferRules();

        static::assertSame(['wechat_open_id'], $rules->identityTypes, 'A wechat openid only exists inside the app that issued it.');
        static::assertSame(['CNY'], $rules->currencies);
        static::assertSame(WechatProvider::HOLDER_NAME_THRESHOLD, $rules->holderNameThreshold);
        static::assertTrue($rules->requiresHolderNameFor(WechatProvider::HOLDER_NAME_THRESHOLD));
        static::assertFalse($rules->requiresHolderNameFor(WechatProvider::HOLDER_NAME_THRESHOLD - 1));
        static::assertSame(WechatProvider::CONFIRM_WINDOW, $rules->confirmWindow);
    }

    public function testDeclaresItsDeductionCalendar(): void
    {
        $rules = PlatformProviders::wechat()->getCycleRules();

        static::assertNull($rules->maxAnchorDayOfMonth, 'Wechat caps no day of the month.');
        static::assertSame(0, $rules->leadTimeHours);
        static::assertSame(7, $rules->windowStartHour);
        static::assertSame(22, $rules->windowEndHour);
        static::assertSame('retryable', $rules->classifyFailureCode('NOTENOUGH'));
        static::assertSame('final', $rules->classifyFailureCode('CONTRACT_NOT_EXIST'));
        static::assertSame('unknown', $rules->classifyFailureCode('ORDERPAID'));
    }

    public function testTheDeclarationIsCoherentEnoughToBuildTheSnapshot(): void
    {
        $provider = (new PaymentProviderFactory())->create(PlatformProviders::wechat());

        static::assertSame('wechat', $provider->technicalName);
        static::assertCount(7, $provider->credentials);
        static::assertCount(5, $provider->channels);
        static::assertTrue($provider->supports(PaymentCapability::MANDATE));
        static::assertTrue($provider->supports(PaymentCapability::TRANSFER_NOTIFICATION));
        static::assertTrue($provider->supports(PaymentCapability::CAPTURE));
    }
}
