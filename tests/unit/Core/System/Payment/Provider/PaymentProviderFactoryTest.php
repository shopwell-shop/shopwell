<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentAction;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentApi;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\PaymentConfigField;
use Shopwell\Core\System\Payment\Provider\PaymentProviderFactory;
use Shopwell\Tests\Unit\Core\System\Payment\Provider\Fixtures\TestNotificationExtractor;
use Shopwell\Tests\Unit\Core\System\Payment\Provider\Fixtures\TestPaymentAction;
use Shopwell\Tests\Unit\Core\System\Payment\Provider\Fixtures\TestPaymentApi;
use Shopwell\Tests\Unit\Core\System\Payment\Provider\Fixtures\TestPaymentDecorator;
use Shopwell\Tests\Unit\Core\System\Payment\Provider\Fixtures\TestPaymentProvider;
use Shopwell\Tests\Unit\Core\System\Payment\Provider\Fixtures\TestPayParamsDecoder;
use Shopwell\Tests\Unit\Core\System\Payment\Provider\Fixtures\TestTransferNotificationVerifier;

/**
 * Guards the conversion of a declaration into the snapshot the platform works with.
 *
 * Every check here used to be something a developer had to remember, and every one of them fails in a
 * way that stays invisible until money is involved: a duplicate credential field overwrites a stored
 * secret, a channel claiming another interface charges the wrong merchant account, a capability
 * without an action announces something nobody executes, a payout notification without a verifier
 * trusts a forged body.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentProviderFactory::class)]
class PaymentProviderFactoryTest extends TestCase
{
    private PaymentProviderFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new PaymentProviderFactory();
    }

    public function testBuildsTheSnapshotOutOfTheDeclaration(): void
    {
        $capture = new TestPaymentAction(PaymentCapability::CAPTURE);
        $decoder = new TestPayParamsDecoder();
        $extractor = new TestNotificationExtractor();
        $verifier = new TestTransferNotificationVerifier();

        $provider = $this->factory->create(new TestPaymentProvider(
            name: 'testpay',
            label: 'test.provider.label',
            actions: [$capture],
            credentials: [new PaymentConfigField(name: 'api_key', required: true, secret: true)],
            channels: [$this->channel('testpay_qr')],
            extractor: $extractor,
            decoder: $decoder,
            verifier: $verifier,
            declaredCapabilities: [PaymentCapability::TRANSFER_NOTIFICATION],
        ));

        static::assertSame('testpay', $provider->technicalName);
        static::assertSame('test.provider.label', $provider->label);
        static::assertSame($extractor, $provider->notificationExtractor);
        static::assertSame($decoder, $provider->payParamsDecoder);
        static::assertSame('testpay', $provider->gatewayFactory->provider());
        static::assertCount(1, $provider->credentials);
        static::assertCount(1, $provider->channels);
        static::assertTrue($provider->hasChannel('testpay_qr'));
        static::assertSame('api_key', $provider->credentialField('api_key')?->name);
        static::assertNull($provider->credentialField('unknown'));
        static::assertSame(['api_key'], $provider->credentialNames());
    }

    public function testTheCapabilitySetIsTheUnionOfTheActionsAndTheDeclaration(): void
    {
        $provider = $this->factory->create($this->declaration([
            new TestPaymentAction(PaymentCapability::CAPTURE),
            new TestPaymentAction(PaymentCapability::REFUND),
            new TestPaymentAction(PaymentCapability::REFUND),
        ], declaredCapabilities: [PaymentCapability::PARTIAL_REFUND, PaymentCapability::PARTIAL_REFUND]));

        static::assertSame(
            [PaymentCapability::CAPTURE, PaymentCapability::REFUND, PaymentCapability::PARTIAL_REFUND],
            $provider->capabilities
        );

        static::assertTrue($provider->supports(PaymentCapability::CAPTURE));
        static::assertTrue($provider->supports(PaymentCapability::PARTIAL_REFUND));
        static::assertFalse($provider->supports(PaymentCapability::CLOSE));
    }

    /**
     * A capture action that only serves one channel is an ordinary action of the gateway: it answers
     * `supports()` for that channel and is ignored for every other one.
     */
    public function testTheChannelCaptureActionsBecomePartOfTheCapabilitySet(): void
    {
        $provider = $this->factory->create(new TestPaymentProvider(
            actions: [new TestPaymentAction(PaymentCapability::CAPTURE)],
            channels: [$this->channel('testpay_qr')],
            channelCaptureActions: ['testpay_qr' => new TestPaymentAction(PaymentCapability::CLOSE)],
        ));

        static::assertSame(
            [PaymentCapability::CAPTURE, PaymentCapability::CLOSE],
            $provider->capabilities
        );
    }

    public function testAcceptsTheSameActionTwiceWithoutDuplicatingIt(): void
    {
        $capture = new TestPaymentAction(PaymentCapability::CAPTURE);

        $provider = $this->factory->create(new TestPaymentProvider(
            actions: [$capture, $capture],
            channels: [$this->channel('testpay_qr')],
            channelCaptureActions: ['testpay_qr' => $capture],
        ));

        static::assertSame([PaymentCapability::CAPTURE], $provider->capabilities);
    }

    public function testRejectsATechnicalNameTheNotifyUrlCannotCarry(): void
    {
        $this->expectExceptionObject(PaymentException::invalidProviderName('my pay'));

        $this->factory->create($this->declaration([], name: 'my pay'));
    }

    public function testRejectsATechnicalNameWithAPathSeparator(): void
    {
        $this->expectExceptionObject(PaymentException::invalidProviderName('test/pay'));

        $this->factory->create($this->declaration([], name: 'test/pay'));
    }

    public function testRejectsACredentialFieldWithoutAName(): void
    {
        $this->expectException(PaymentException::class);

        $this->factory->create($this->declaration([], credentials: [new PaymentConfigField(name: '')]));
    }

    public function testRejectsACredentialFieldDeclaredTwice(): void
    {
        $this->expectException(PaymentException::class);

        $this->factory->create($this->declaration([], credentials: [
            new PaymentConfigField(name: 'api_key'),
            new PaymentConfigField(name: 'api_key', type: PaymentConfigField::TYPE_PASSWORD),
        ]));
    }

    public function testRejectsAChannelWithoutAName(): void
    {
        $this->expectException(PaymentException::class);

        $this->factory->create($this->declaration([], channels: [$this->channel('')]));
    }

    public function testRejectsAChannelDeclaredTwice(): void
    {
        $this->expectException(PaymentException::class);

        $this->factory->create($this->declaration([], channels: [$this->channel('testpay_qr'), $this->channel('testpay_qr')]));
    }

    /**
     * A channel that names another interface would be charged on that interface's merchant account
     * while the payment order says `testpay` - the two rows would never agree again.
     */
    public function testRejectsAChannelThatClaimsAnotherInterface(): void
    {
        $this->expectException(PaymentException::class);

        $this->factory->create($this->declaration([], channels: [
            new PaymentChannel(
                technicalName: 'testpay_qr',
                providerTechnicalName: 'somebody_else',
                label: 'test.channel.label',
                flow: PaymentFlow::QRCODE,
            ),
        ]));
    }

    public function testRejectsAnInterfaceThatCannotCapture(): void
    {
        $this->expectException(PaymentException::class);

        $this->factory->create($this->declaration([new TestPaymentAction(PaymentCapability::REFUND)]));
    }

    /**
     * A request capability is read off the actions - declaring one would announce something no code
     * executes.
     */
    public function testRejectsADeclaredRequestCapability(): void
    {
        $this->expectException(PaymentException::class);

        $this->factory->create($this->declaration(
            [new TestPaymentAction(PaymentCapability::CAPTURE)],
            declaredCapabilities: [PaymentCapability::CLOSE],
        ));
    }

    /**
     * A policy capability has no request, so an action that claims it could never be dispatched.
     */
    public function testRejectsAnActionThatDeclaresAPolicyCapability(): void
    {
        $this->expectException(PaymentException::class);

        $this->factory->create($this->declaration([
            new TestPaymentAction(PaymentCapability::CAPTURE),
            new TestPaymentAction(PaymentCapability::MANDATE),
        ]));
    }

    public function testRejectsAnAnnouncedPayoutNotificationWithoutAVerifier(): void
    {
        $this->expectException(PaymentException::class);

        $this->factory->create($this->declaration(
            [new TestPaymentAction(PaymentCapability::CAPTURE)],
            declaredCapabilities: [PaymentCapability::TRANSFER_NOTIFICATION],
        ));
    }

    /**
     * The other direction is just as wrong: a verifier nobody declared is dead code, and the platform
     * would reject every payout notification of an interface that can send them.
     */
    public function testRejectsAVerifierWithoutTheAnnouncedCapability(): void
    {
        $this->expectException(PaymentException::class);

        $this->factory->create($this->declaration(
            [new TestPaymentAction(PaymentCapability::CAPTURE)],
            verifier: new TestTransferNotificationVerifier(),
        ));
    }

    public function testRejectsAnApiThatIsNoPaymentApi(): void
    {
        $this->expectExceptionObject(PaymentException::invalidProviderDeclaration(
            \sprintf(
                'The payment interface "testpay" names the api "%s", which does not extend %s.',
                \stdClass::class,
                AbstractPaymentApi::class
            )
        ));

        $this->factory->create(new TestPaymentProvider(
            actions: [new TestPaymentAction(PaymentCapability::CAPTURE)],
            channels: [$this->channel('testpay_qr')],
            apiClass: \stdClass::class,
        ));
    }

    public function testADecoratorKeepsTheInterfaceItWrapsIntact(): void
    {
        $base = $this->declaration([new TestPaymentAction(PaymentCapability::CAPTURE)]);

        $provider = $this->factory->create(new TestPaymentDecorator(
            $base,
            [new TestPaymentAction(PaymentCapability::REFUND)],
        ));

        static::assertSame('testpay', $provider->technicalName);
        static::assertSame([PaymentCapability::CAPTURE, PaymentCapability::REFUND], $provider->capabilities);
        static::assertCount(1, $provider->credentials, 'The credentials of the decorated declaration survive.');
        static::assertCount(1, $provider->channels);
    }

    /**
     * `payment_provider_config.credentials` is keyed by field name: a decorator that writes its own
     * list from scratch makes the stored value of the fields it forgot unreachable.
     */
    public function testADecoratorMayNotDropACredentialField(): void
    {
        $base = $this->declaration([new TestPaymentAction(PaymentCapability::CAPTURE)]);

        $this->expectException(PaymentException::class);

        $this->factory->create(new TestPaymentDecorator($base, [], [new PaymentConfigField(name: 'other')]));
    }

    /**
     * A decorator that changes the technical name would book its payments under a name that has no
     * configuration and no app authorization.
     */
    public function testADecoratorMayNotChangeTheTechnicalName(): void
    {
        $base = new TestPaymentProvider(
            actions: [new TestPaymentAction(PaymentCapability::CAPTURE)],
        );

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessageMatches('/has to keep the technical name/');

        $this->factory->create(new TestPaymentDecorator($base, nameOverride: 'somebody_else'));
    }

    /**
     * The tag key is what the container and the notify url address the interface by, the name the
     * declaration gives itself is what every stored row is keyed by - a declaration that disagrees
     * with its own tag would be configured under one name and paid under the other.
     */
    public function testRejectsADeclarationWhoseTagKeyIsNotItsTechnicalName(): void
    {
        $this->expectException(PaymentException::class);
        $this->expectExceptionMessageMatches('/tag key and the technical name have to be the same/');

        $this->factory->create($this->declaration([]), 'somebody_else');
    }

    public function testAcceptsADeclarationWhoseTagKeyIsItsTechnicalName(): void
    {
        $provider = $this->factory->create($this->declaration([new TestPaymentAction(PaymentCapability::CAPTURE)]), 'testpay');

        static::assertSame('testpay', $provider->technicalName);
    }

    public function testTheSnapshotIsBuiltAroundTheActionsOfTheDeclaration(): void
    {
        $provider = $this->factory->create(new TestPaymentProvider(
            actions: [new TestPaymentAction(PaymentCapability::CAPTURE)],
            channels: [$this->channel('testpay_qr')],
            apiClass: TestPaymentApi::class,
            apiArguments: ['first', 'second'],
        ));

        static::assertSame('testpay', $provider->gatewayFactory->provider());
        static::assertSame('testpay', $provider->gatewayFactory->createConfig()['payum.factory_name']);
    }

    /**
     * @param list<AbstractPaymentAction> $actions
     * @param list<PaymentConfigField> $credentials
     * @param list<PaymentChannel>|null $channels
     * @param list<PaymentCapability> $declaredCapabilities
     */
    private function declaration(
        array $actions,
        string $name = 'testpay',
        array $credentials = [new PaymentConfigField(name: 'api_key')],
        ?array $channels = null,
        array $declaredCapabilities = [],
        ?TestTransferNotificationVerifier $verifier = null,
    ): TestPaymentProvider {
        return new TestPaymentProvider(
            name: $name,
            actions: $actions,
            credentials: $credentials,
            channels: $channels ?? [$this->channel('testpay_qr', $name)],
            declaredCapabilities: $declaredCapabilities,
            verifier: $verifier,
        );
    }

    private function channel(string $technicalName, string $providerTechnicalName = 'testpay'): PaymentChannel
    {
        return new PaymentChannel(
            technicalName: $technicalName,
            providerTechnicalName: $providerTechnicalName,
            label: 'test.channel.label',
            flow: PaymentFlow::QRCODE,
            currencies: ['EUR'],
        );
    }
}
