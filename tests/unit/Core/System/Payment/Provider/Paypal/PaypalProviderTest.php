<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Paypal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\PaymentProviderFactory;
use Shopwell\Core\System\Payment\Provider\Paypal\PaypalApi;
use Shopwell\Core\System\Payment\Provider\Paypal\PaypalProvider;
use Shopwell\Tests\Unit\Core\System\Payment\Provider\Fixtures\PlatformProviders;

/**
 * Guards the declaration of the paypal interface.
 *
 * The two absences are as important as the presences: no close action, because an order that was
 * created and never approved expires on its own, and no pay params decoder, because the approval url is
 * only one link of the recorded body.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaypalProvider::class)]
class PaypalProviderTest extends TestCase
{
    public function testNamesItself(): void
    {
        $provider = PlatformProviders::paypal();

        static::assertSame('paypal', $provider->getName());
        static::assertSame('sw-settings-payment-provider.provider.paypal', $provider->getLabel());
        static::assertSame(PaypalApi::class, $provider->getApiClass());
        static::assertNull($provider->getSourceName());
    }

    public function testOffersSixActionsAndNoClose(): void
    {
        static::assertSame(
            ['capture', 'refund', 'notification', 'query', 'transfer', 'transfer_query'],
            array_map(
                static fn ($action): string => $action->capability()->value,
                PlatformProviders::paypal()->getActions()
            )
        );
    }

    public function testShipsNoDecoderSoAReturningPayerIsCapturedAgain(): void
    {
        static::assertNull(PlatformProviders::paypal()->getPayParamsDecoder());
    }

    public function testHandsTheHttpClientToItsApi(): void
    {
        static::assertCount(
            1,
            PlatformProviders::paypal()->getApiArguments(),
            'The api fetches the certificate a webhook is signed with.'
        );
    }

    public function testDeclaresTheCapabilitiesWithoutARequestOfTheirOwn(): void
    {
        static::assertSame(
            [PaymentCapability::PARTIAL_REFUND, PaymentCapability::TRANSFER_NOTIFICATION],
            PlatformProviders::paypal()->getDeclaredCapabilities()
        );
    }

    public function testDeclaresTheMerchantCredentials(): void
    {
        $fields = PlatformProviders::paypal()->getCredentials();

        static::assertSame(
            ['client_id', 'client_secret', 'environment', 'webhook_id', 'brand_name'],
            array_map(static fn ($field): string => $field->name, $fields)
        );
        static::assertTrue($fields[0]->required);
        static::assertTrue($fields[1]->secret);
        static::assertTrue($fields[1]->required);
        static::assertSame(['sandbox' => 'sandbox', 'live' => 'live'], $fields[2]->options);
        static::assertSame('sandbox', $fields[2]->default);
    }

    public function testDeclaresTheSingleCheckoutChannel(): void
    {
        $channels = PlatformProviders::paypal()->getChannels();

        static::assertCount(1, $channels);
        static::assertSame('paypal_checkout', $channels[0]->technicalName);
        static::assertSame('paypal', $channels[0]->providerTechnicalName);
        static::assertSame(PaymentFlow::REDIRECT, $channels[0]->flow);
        static::assertSame([], $channels[0]->currencies, 'What the account can be paid in is a property of the account.');
        static::assertSame([], $channels[0]->devices);
    }

    public function testDeclaresWhatItPaysOutTo(): void
    {
        $rules = PlatformProviders::paypal()->getTransferRules();

        static::assertSame(['paypal_email', 'paypal_phone', 'paypal_payer_id'], $rules->identityTypes);
        static::assertSame([], $rules->currencies, 'The receiving account decides the currency.');
        static::assertFalse($rules->requiresOnboarding);
    }

    public function testDeclaresNoDeductionCalendarBecauseItCannotBeChargedRepeatedly(): void
    {
        static::assertNull(PlatformProviders::paypal()->getCycleRules());
    }

    public function testTheDeclarationIsCoherentEnoughToBuildTheSnapshot(): void
    {
        $provider = (new PaymentProviderFactory())->create(PlatformProviders::paypal());

        static::assertSame('paypal', $provider->technicalName);
        static::assertCount(1, $provider->channels);
        static::assertTrue($provider->supports(PaymentCapability::CAPTURE));
        static::assertFalse($provider->supports(PaymentCapability::CLOSE), 'Paypal has no close action.');
        static::assertFalse($provider->supports(PaymentCapability::MANDATE));
    }
}
