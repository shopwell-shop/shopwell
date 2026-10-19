<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Stripe;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\PaymentProviderFactory;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeApi;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeProvider;
use Shopwell\Tests\Unit\Core\System\Payment\Provider\Fixtures\PlatformProviders;

/**
 * Guards the declaration of the stripe interface.
 *
 * Payouts go to a connected account that has to finish its onboarding first, which is the one place
 * `requiresOnboarding` is true.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(StripeProvider::class)]
class StripeProviderTest extends TestCase
{
    public function testNamesItself(): void
    {
        $provider = PlatformProviders::stripe();

        static::assertSame('stripe', $provider->getName());
        static::assertSame('sw-settings-payment-provider.provider.stripe', $provider->getLabel());
        static::assertSame(StripeApi::class, $provider->getApiClass());
        static::assertNull($provider->getSourceName());
    }

    public function testOffersSixActionsAndNoClose(): void
    {
        static::assertSame(
            ['capture', 'refund', 'notification', 'query', 'transfer', 'transfer_query'],
            array_map(
                static fn ($action): string => $action->capability()->value,
                PlatformProviders::stripe()->getActions()
            )
        );
    }

    public function testShipsNoDecoderBecauseNoBrowserParameterSurvivesAReopen(): void
    {
        static::assertNull(PlatformProviders::stripe()->getPayParamsDecoder());
    }

    public function testDeclaresTheCapabilitiesWithoutARequestOfTheirOwn(): void
    {
        static::assertSame(
            [PaymentCapability::PARTIAL_REFUND, PaymentCapability::TRANSFER_NOTIFICATION],
            PlatformProviders::stripe()->getDeclaredCapabilities()
        );
    }

    public function testDeclaresTheMerchantCredentials(): void
    {
        $fields = PlatformProviders::stripe()->getCredentials();

        static::assertSame(
            ['secret_key', 'publishable_key', 'webhook_secret', 'api_version'],
            array_map(static fn ($field): string => $field->name, $fields)
        );
        static::assertTrue($fields[0]->required);
        static::assertTrue($fields[0]->secret);
        static::assertFalse($fields[1]->secret, 'The publishable key is public by design.');
        static::assertTrue($fields[2]->secret);
    }

    public function testDeclaresTheSingleCheckoutChannel(): void
    {
        $channels = PlatformProviders::stripe()->getChannels();

        static::assertCount(1, $channels);
        static::assertSame('stripe_checkout', $channels[0]->technicalName);
        static::assertSame('stripe', $channels[0]->providerTechnicalName);
        static::assertSame(PaymentFlow::REDIRECT, $channels[0]->flow);
        static::assertSame([], $channels[0]->currencies);
        static::assertSame([], $channels[0]->devices);
    }

    public function testDeclaresTheConnectedAccountAsItsPayoutIdentity(): void
    {
        $rules = PlatformProviders::stripe()->getTransferRules();

        static::assertSame(['stripe_account'], $rules->identityTypes, 'Stripe pays out to an account, not to a card.');
        static::assertTrue($rules->requiresOnboarding, 'A connected account has to be onboarded before the first payout.');
        static::assertSame([], $rules->currencies);
    }

    public function testDeclaresNoDeductionCalendarBecauseItCannotBeChargedRepeatedly(): void
    {
        static::assertNull(PlatformProviders::stripe()->getCycleRules());
    }

    public function testTheDeclarationIsCoherentEnoughToBuildTheSnapshot(): void
    {
        $provider = (new PaymentProviderFactory())->create(PlatformProviders::stripe());

        static::assertSame('stripe', $provider->technicalName);
        static::assertCount(1, $provider->channels);
        static::assertTrue($provider->supports(PaymentCapability::CAPTURE));
        static::assertFalse($provider->supports(PaymentCapability::CLOSE));
        static::assertFalse($provider->supports(PaymentCapability::MANDATE));
    }
}
