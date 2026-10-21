<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Transfer\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelEntity;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppProvider\PaymentAppProviderEntity;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentAction;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentApi;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\AbstractPaymentProvider;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Shopwell\Core\System\Payment\Provider\Transfer\PaymentTransferRules;
use Shopwell\Core\System\Payment\Transfer\Api\PaymentTransferPresenter;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferStates;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

/**
 * The presenter is the one place that decides what an operator may see of a payout. Two values make it
 * security relevant: the identity of a recipient (a credential, stored encrypted) and wechat's
 * `package_info` (a bearer value that claims the money).
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentTransferPresenter::class)]
class PaymentTransferPresenterTest extends TestCase
{
    private const SECRET = 'unit-test-application-secret';

    #[DataProvider('masks')]
    public function testAnIdentityIsMaskedSoThatItStaysRecognisable(string $plain, string $expected): void
    {
        static::assertSame($expected, PaymentTransferPresenter::mask($plain));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function masks(): iterable
    {
        yield 'an e-mail keeps its first character and its domain' => ['a@b.com', 'a***@b.com'];
        yield 'a long e-mail' => ['zhangsan@example.com', 'z***@example.com'];
        yield 'a short handle is masked completely' => ['1234', '****'];
        yield 'exactly five characters are masked completely' => ['12345', '****'];
        yield 'six characters keep two on each side' => ['123456', '12****56'];
        yield 'an identity without an at sign' => ['2088000000000000', '20****00'];
        yield 'an empty identity' => ['   ', '****'];
    }

    public function testAnEncryptedIdentityIsDecryptedAndMasked(): void
    {
        $cipher = $this->cipher();
        $presenter = $this->presenter([]);

        static::assertSame('zh****23', $presenter->maskIdentity($cipher->encryptValue('zhangsan123')));
        static::assertNull($presenter->maskIdentity(null));
        static::assertNull($presenter->maskIdentity(''));
    }

    /**
     * A value the cipher can not open is a rotated secret or a row from before the encryption - the
     * platform does not know the handle then, and a placeholder would look like a masked handle.
     */
    public function testAnUndecryptableIdentityYieldsNull(): void
    {
        static::assertNull($this->presenter([])->maskIdentity('not-a-ciphertext'));
        static::assertNull($this->presenter([])->maskIdentity(base64_encode('too-short')));
    }

    /**
     * The answer must not carry a single field that lets the browser reconstruct an account or claim a
     * payout: no identity value (plain or encrypted), no channel parameters, no confirm payload.
     */
    public function testATransferIsPresentedWithoutTheCredentialFields(): void
    {
        $presenter = $this->presenter([]);
        $recipient = $this->recipient();
        $transfer = $this->transfer([
            'recipient' => $recipient,
            'confirmPayload' => ['package_info' => 'MchId=1&Package=secret'],
            'channelParams' => ['transfer_scene_name' => 'COMMISSION'],
            'metadata' => ['note' => 'September settlement', 'internal' => 'must not leak'],
        ]);

        $presented = $presenter->transfer($transfer, $this->app('app-id', PaymentAppEntity::TYPE_PLATFORM), $recipient);

        static::assertArrayNotHasKey('identityValue', $presented);
        static::assertArrayNotHasKey('channelParams', $presented);
        static::assertArrayNotHasKey('confirmPayload', $presented);
        static::assertArrayNotHasKey('identityValue', $presented['recipient']);
        static::assertSame('zh****23', $presented['recipient']['maskedIdentity']);
        static::assertTrue($presented['hasConfirmPayload']);

        // the metadata is an allow list, not a copy: whatever a future writer keeps there stays there
        static::assertSame(
            ['note' => 'September settlement'],
            $presented['metadata']
        );

        // the payload itself must not be part of the answer in any form
        static::assertStringNotContainsString('MchId=1', (string) json_encode($presented));
        static::assertStringNotContainsString('zhangsan123', (string) json_encode($presented));
    }

    public function testATransferWithoutAConfirmPayloadSaysSo(): void
    {
        $presented = $this->presenter([])->transfer($this->transfer());

        static::assertFalse($presented['hasConfirmPayload']);
    }

    public function testTheStateIsPresentedWithItsTechnicalName(): void
    {
        $presented = $this->presenter([])->transfer($this->transfer());

        static::assertSame('created', $presented['state']['technicalName']);
        static::assertSame('state-created', $presented['state']['id']);
        static::assertSame('2026-10-21T12:00:00+00:00', $presented['createdAt']);
    }

    public function testARecipientsAnswerCarriesNoIdentityValue(): void
    {
        $presented = $this->presenter([])->recipients([$this->recipient()]);

        static::assertCount(1, $presented);
        static::assertArrayNotHasKey('identityValue', $presented[0]);
        static::assertSame('alipay_logon_id', $presented[0]['identityType']);
        static::assertSame('Zhang San', $presented[0]['holderName']);
        static::assertSame('zh****23', $presented[0]['maskedIdentity']);
    }

    /**
     * The modal must not offer an interface the platform can not pay out with, and the currency of a
     * channel comes from the interface rules - they are what a payout is validated against.
     */
    public function testTheOptionsOnlyOfferPayoutCapableInterfaces(): void
    {
        $alipayRules = new PaymentTransferRules(
            identityTypes: ['alipay_logon_id', 'bankcard'],
            currencies: ['CNY'],
            requiredRecipientFields: ['bankcard' => ['holderName', 'bankInfo']],
            bankAccountTypes: ['1', '2'],
            holderNameThreshold: 200000,
        );

        $presenter = $this->presenter([
            $this->declaration('alipay', $alipayRules),
            // declares capabilities but no payout rules - must not appear
            $this->declaration('paypal', null),
        ]);

        $registry = $this->registry([
            $this->provider('alipay', ['alipay_transfer' => $this->channel('alipay_transfer', 'alipay', ['CNY'])]),
            $this->provider('paypal', ['paypal_payout' => $this->channel('paypal_payout', 'paypal', ['USD'])]),
        ]);

        $app = $this->app('app-id', PaymentAppEntity::TYPE_PLATFORM);
        $app->assign([
            'providers' => [
                $this->authorization('alipay', true),
                $this->authorization('paypal', true),
                $this->authorization('stripe', false),
            ],
            'channels' => [
                $this->appChannel('alipay_transfer', true),
                // a channel of another interface, enabled for the app: it must not be listed under alipay
                $this->appChannel('paypal_payout', true),
                $this->appChannel('alipay_h5', false),
            ],
        ]);

        $options = $presenter->options([$app], $registry);

        static::assertSame('app-id', $options['defaultAppId']);
        static::assertCount(1, $options['apps']);
        static::assertTrue($options['apps'][0]['isPlatform']);

        static::assertCount(1, $options['apps'][0]['providers']);
        $alipay = $options['apps'][0]['providers'][0];

        static::assertSame('alipay', $alipay['technicalName']);
        static::assertSame(['alipay_logon_id', 'bankcard'], $alipay['identityTypes']);
        static::assertSame(['CNY'], $alipay['currencies']);
        static::assertSame(['1', '2'], $alipay['bankAccountTypes']);
        static::assertSame(200000, $alipay['holderNameThreshold']);
        static::assertFalse($alipay['requiresOnboarding']);

        static::assertCount(1, $alipay['channels']);
        static::assertSame('alipay_transfer', $alipay['channels'][0]['technicalName']);
        static::assertSame(['CNY'], $alipay['channels'][0]['currencies']);
    }

    public function testAnAppWithoutPayoutCapableInterfacesOffersNoProviders(): void
    {
        $presenter = $this->presenter([$this->declaration('alipay', null)]);
        $registry = $this->registry([
            $this->provider('alipay', ['alipay_transfer' => $this->channel('alipay_transfer', 'alipay', ['CNY'])]),
        ]);

        $app = $this->app('app-id', PaymentAppEntity::TYPE_EXTERNAL);
        $app->assign([
            'providers' => [$this->authorization('alipay', true)],
            'channels' => [$this->appChannel('alipay_transfer', true)],
        ]);

        $options = $presenter->options([$app], $registry);

        static::assertSame([], $options['apps'][0]['providers']);
        static::assertFalse($options['apps'][0]['isPlatform']);
    }

    private function cipher(): PaymentCredentialCipher
    {
        return new PaymentCredentialCipher(self::SECRET);
    }

    /**
     * @param list<AbstractPaymentProvider> $declarations
     */
    private function presenter(array $declarations): PaymentTransferPresenter
    {
        return new PaymentTransferPresenter($this->cipher(), $declarations);
    }

    /**
     * @param list<PaymentProvider> $providers
     */
    private function registry(array $providers): PaymentProviderRegistry
    {
        // the registry is keyed by the technical name of the interface
        $keyed = [];

        foreach ($providers as $provider) {
            $keyed[$provider->technicalName] = $provider;
        }

        return new PaymentProviderRegistry($keyed);
    }

    private function declaration(string $name, ?PaymentTransferRules $rules): AbstractPaymentProvider
    {
        return new class($name, $rules) extends AbstractPaymentProvider {
            public function __construct(
                private readonly string $name,
                private readonly ?PaymentTransferRules $transferRules,
            ) {
            }

            public function getName(): string
            {
                return $this->name;
            }

            public function getLabel(): string
            {
                return $this->name;
            }

            public function getApiClass(): string
            {
                return AbstractPaymentApi::class;
            }

            public function getActions(): array
            {
                return [];
            }

            public function getCaptureAction(): AbstractPaymentAction
            {
                throw PaymentException::invalidProviderDeclaration('a declaration of the presenter test captures nothing');
            }

            public function getNotificationExtractor(): PaymentNotificationExtractorInterface
            {
                throw PaymentException::invalidProviderDeclaration('a declaration of the presenter test notifies nothing');
            }

            public function getTransferRules(): ?PaymentTransferRules
            {
                return $this->transferRules;
            }
        };
    }

    /**
     * @param array<string, PaymentChannel> $channels
     */
    private function provider(string $name, array $channels): PaymentProvider
    {
        return new PaymentProvider(
            technicalName: $name,
            label: $name,
            credentials: [],
            channels: $channels,
            gatewayFactory: static::createStub(PaymentGatewayFactory::class),
            notificationExtractor: static::createStub(PaymentNotificationExtractorInterface::class),
            capabilities: [PaymentCapability::TRANSFER],
        );
    }

    /**
     * @param list<string> $currencies
     */
    private function channel(string $name, string $provider, array $currencies): PaymentChannel
    {
        return new PaymentChannel(
            technicalName: $name,
            providerTechnicalName: $provider,
            label: $name,
            flow: PaymentFlow::REDIRECT,
            currencies: $currencies,
        );
    }

    private function authorization(string $provider, bool $enabled): PaymentAppProviderEntity
    {
        $authorization = new PaymentAppProviderEntity();
        $authorization->id = 'authorization-' . $provider;
        $authorization->providerTechnicalName = $provider;
        $authorization->enabled = $enabled;

        return $authorization;
    }

    private function appChannel(string $channel, bool $enabled): PaymentAppChannelEntity
    {
        $row = new PaymentAppChannelEntity();
        $row->id = 'app-channel-' . $channel;
        $row->channelTechnicalName = $channel;
        $row->enabled = $enabled;

        return $row;
    }

    private function app(string $id, string $type): PaymentAppEntity
    {
        $app = new PaymentAppEntity();
        $app->id = $id;
        $app->technicalName = $type === PaymentAppEntity::TYPE_PLATFORM ? 'platform' : 'merchant';
        $app->name = 'App ' . $id;
        $app->type = $type;
        $app->active = true;
        $app->setUniqueIdentifier($id);

        return $app;
    }

    private function recipient(): PaymentTransferRecipientEntity
    {
        $recipient = new PaymentTransferRecipientEntity();
        $recipient->id = 'recipient-id';
        $recipient->paymentAppId = 'app-id';
        $recipient->provider = 'alipay';
        $recipient->identityType = 'alipay_logon_id';
        $recipient->identityValue = $this->cipher()->encryptValue('zhangsan123');
        $recipient->identityHash = hash('sha256', 'identity');
        $recipient->holderName = 'Zhang San';
        $recipient->onboardingStatus = PaymentTransferStates::ONBOARDING_REGISTERED;
        $recipient->payoutEnabled = true;
        $recipient->setUniqueIdentifier('recipient-id');

        return $recipient;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function transfer(array $overrides = []): PaymentTransferEntity
    {
        $state = new StateMachineStateEntity();
        $state->setUniqueIdentifier('state-created');
        $state->setTechnicalName(PaymentTransferStates::STATE_CREATED);
        $state->setName('Created');

        $transfer = new PaymentTransferEntity();
        $transfer->id = 'transfer-id';
        $transfer->paymentAppId = 'app-id';
        $transfer->transferNo = 'PT2610211200ABCDEF0123';
        $transfer->outNo = 'TF2610211200A1B2';
        $transfer->recipientId = 'recipient-id';
        $transfer->provider = 'alipay';
        $transfer->channel = 'alipay_transfer';
        $transfer->amount = 10;
        $transfer->currency = 'CNY';
        $transfer->statusId = 'state-created';
        $transfer->submitAttempts = 0;
        $transfer->statusMachineState = $state;
        $transfer->assign(['createdAt' => new \DateTimeImmutable('2026-10-21T12:00:00+00:00')]);
        $transfer->assign($overrides);
        $transfer->setUniqueIdentifier('transfer-id');

        return $transfer;
    }
}
