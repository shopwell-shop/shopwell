<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Alipay;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayApi;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayCaptureAction;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayNotificationExtractor;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayPayParamsDecoder;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayProvider;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayTransferNotificationVerifier;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\PaymentProviderFactory;
use Shopwell\Tests\Unit\Core\System\Payment\Provider\Fixtures\PlatformProviders;

/**
 * Guards the declaration of the alipay interface: what it offers, what it demands and what it can do.
 *
 * The values are the ones `Provider/Resources/config/alipay.xml` carried before the declaration became
 * code, field by field - a channel that loses its currency or a credential that loses its `secret` flag
 * would otherwise only be noticed by the merchant who can no longer be charged.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(AlipayProvider::class)]
class AlipayProviderTest extends TestCase
{
    public function testNamesItself(): void
    {
        $provider = PlatformProviders::alipay();

        static::assertSame('alipay', $provider->getName());
        static::assertSame('sw-settings-payment-provider.provider.alipay', $provider->getLabel());
        static::assertSame(AlipayApi::class, $provider->getApiClass());
        static::assertNull($provider->getSourceName(), 'A core interface has no plugin source.');
    }

    public function testOffersTheSevenActionsOfTheInterface(): void
    {
        $provider = PlatformProviders::alipay();

        $capabilities = array_map(
            static fn ($action): string => $action->capability()->value,
            $provider->getActions()
        );

        static::assertSame(
            ['capture', 'refund', 'notification', 'query', 'close', 'transfer', 'transfer_query'],
            $capabilities
        );
        static::assertInstanceOf(AlipayCaptureAction::class, $provider->getCaptureAction());
        static::assertInstanceOf(AlipayNotificationExtractor::class, $provider->getNotificationExtractor());
        static::assertInstanceOf(AlipayPayParamsDecoder::class, $provider->getPayParamsDecoder());
        static::assertInstanceOf(AlipayTransferNotificationVerifier::class, $provider->getTransferNotificationVerifier());
    }

    public function testDeclaresTheCapabilitiesWithoutARequestOfTheirOwn(): void
    {
        static::assertSame(
            [
                PaymentCapability::PARTIAL_REFUND,
                PaymentCapability::MANDATE,
                PaymentCapability::TRANSFER_NOTIFICATION,
            ],
            PlatformProviders::alipay()->getDeclaredCapabilities()
        );
    }

    public function testDeclaresTheCredentialsOfTheMerchantAccount(): void
    {
        $fields = PlatformProviders::alipay()->getCredentials();

        static::assertSame(
            ['app_id', 'sandbox', 'private_key', 'alipay_public_key', 'sign_type', 'gateway_url'],
            array_map(static fn ($field): string => $field->name, $fields)
        );

        static::assertTrue($fields[0]->required);
        static::assertSame('bool', $fields[1]->type);
        static::assertSame('0', $fields[1]->default);
        static::assertTrue($fields[2]->required);
        static::assertTrue($fields[2]->secret, 'The private key must never be echoed back.');
        static::assertSame('textarea', $fields[2]->type);
        static::assertSame(['RSA2' => 'RSA2'], $fields[4]->options);
        static::assertSame('RSA2', $fields[4]->default);
        static::assertSame(AlipayProvider::DEFAULT_GATEWAY_URL, $fields[5]->default);
    }

    public function testDeclaresTheFiveChannelsOfTheInterface(): void
    {
        $channels = PlatformProviders::alipay()->getChannels();

        static::assertSame(
            ['alipay_h5', 'alipay_web', 'alipay_qr', 'alipay_app', 'alipay_mini'],
            array_map(static fn ($channel): string => $channel->technicalName, $channels)
        );

        foreach ($channels as $channel) {
            static::assertSame('alipay', $channel->providerTechnicalName);
            static::assertSame(['CNY'], $channel->currencies);
            static::assertStringStartsWith('sw-settings-payment-provider.channel.', $channel->label);
        }

        static::assertSame(PaymentFlow::REDIRECT, $channels[0]->flow);
        static::assertSame(['wap'], $channels[0]->devices);
        static::assertSame(['http_method'], array_map(static fn ($field): string => $field->name, $channels[0]->params));
        static::assertSame(PaymentFlow::QRCODE, $channels[2]->flow);
        static::assertSame(['web', 'wap'], $channels[2]->devices, 'The qr channel serves both devices.');
        static::assertSame(PaymentFlow::SDK, $channels[3]->flow);
        static::assertSame(['app'], $channels[3]->devices);
        static::assertSame(['mini'], $channels[4]->devices);
        static::assertSame(['buyer_id' => true], $channels[4]->runtimeParams);
        static::assertSame(['op_app_id'], array_map(static fn ($field): string => $field->name, $channels[4]->params));
    }

    public function testDeclaresWhatItPaysOutTo(): void
    {
        $rules = PlatformProviders::alipay()->getTransferRules();

        static::assertSame(['alipay_user_id', 'alipay_logon_id', 'alipay_open_id', 'bankcard'], $rules->identityTypes);
        static::assertSame(['CNY'], $rules->currencies);
        static::assertSame(['holderName'], $rules->requiredFieldsFor('alipay_logon_id'));
        static::assertSame(['holderName', 'bankInfo'], $rules->requiredFieldsFor('bankcard'));
        static::assertSame([], $rules->requiredFieldsFor('alipay_user_id'));
        static::assertSame(['1', '2'], $rules->bankAccountTypes);
        static::assertNull($rules->holderNameThreshold, 'Alipay wants the holder name per identity type, not per amount.');
    }

    public function testDeclaresItsDeductionCalendar(): void
    {
        $rules = PlatformProviders::alipay()->getCycleRules();

        static::assertSame(28, $rules->maxAnchorDayOfMonth);
        static::assertSame(48, $rules->leadTimeHours);
        static::assertSame(7, $rules->windowStartHour);
        static::assertSame(22, $rules->windowEndHour);
        static::assertSame(5, $rules->maxRetries);
        static::assertSame('retryable', $rules->classifyFailureCode('ACQ.PAYMENT_FAIL'));
        static::assertSame('final', $rules->classifyFailureCode('ACQ.AGREEMENT_NOT_EXIST'));
        static::assertSame('unknown', $rules->classifyFailureCode('ACQ.SYSTEM_ERROR'));
    }

    public function testTheDeclarationIsCoherentEnoughToBuildTheSnapshot(): void
    {
        $provider = (new PaymentProviderFactory())->create(PlatformProviders::alipay());

        static::assertSame('alipay', $provider->technicalName);
        static::assertSame('sw-settings-payment-provider.provider.alipay', $provider->label);
        static::assertCount(6, $provider->credentials);
        static::assertCount(5, $provider->channels);
        static::assertSame('alipay', $provider->gatewayFactory->provider());

        foreach (PaymentCapability::cases() as $capability) {
            static::assertTrue(
                $provider->supports($capability),
                \sprintf('Alipay is expected to support "%s".', $capability->value)
            );
        }
    }
}
