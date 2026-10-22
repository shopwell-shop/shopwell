<?php
declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\Payment\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelCollection;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppProvider\PaymentAppProviderCollection;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayApi;
use Shopwell\Core\System\Payment\Provider\PaymentChannelConfigCollection;
use Shopwell\Core\System\Payment\Provider\PaymentProviderConfigCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * End to end proof that a capability the declaration does not carry is answered as a client error
 * instead of a channel fault.
 *
 * The close endpoint is the one call the platform initiates itself, so it is the shortest path from an
 * api request to an action of an interface. Stripe declares no close action at all - before the guard
 * existed, `payum` threw "no action supports this request" inside the gateway and the app read a 502
 * `PAYMENT__GATEWAY_ERROR`, i.e. "the channel is broken", for a request that could never work. Alipay
 * declares a close, and this test proves its close still leaves the platform - the guard must not have
 * turned into a blanket refusal.
 *
 * @internal
 */
#[Package('framework')]
class PaymentCapabilityGuardTest extends TestCase
{
    use IntegrationTestBehaviour;
    use PaymentApiFixtureTrait;

    private const CLOSE_PATH = '/payment-api/v1/payment-orders/%s/close';

    private const STRIPE_PROVIDER = 'stripe';

    private const STRIPE_CHANNEL = 'stripe_checkout';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpAlipayPlatform();
        $this->createContractApp();
        $this->authorizeStripe();
    }

    /**
     * The declaration answers before a request is signed: 400, its own error code, and no attempt is
     * booked - nothing was sent, so nothing may be reported as an attempt.
     */
    public function testClosingThroughAnInterfaceThatCannotCloseIsABadRequest(): void
    {
        $client = $this->getBrowser();
        $seed = $this->seedPaymentOrder([
            'providerTechnicalName' => self::STRIPE_PROVIDER,
            'channelTechnicalName' => self::STRIPE_CHANNEL,
        ]);

        $path = \sprintf(self::CLOSE_PATH, $seed['paymentNo']);
        $this->postJson($client, $path, $this->signedPayload('POST', $path));

        $response = $client->getResponse();
        $content = (string) $response->getContent();

        self::assertError($response, Response::HTTP_BAD_REQUEST, PaymentException::PROVIDER_LACKS_CAPABILITY);
        // and it does not read like a broken channel: a caller that reads "the channel returned an
        // error" retries a call that can never work
        static::assertStringNotContainsString(PaymentException::GATEWAY_ERROR, $content);
        static::assertStringNotContainsString('returned an error', $content);

        // the attempt would have been booked before the request leaves the platform - it is not
        static::assertSame(0, $this->closeAttemptsOf($seed['id']));
    }

    /**
     * The other side of the guard: an interface that declares the close is still asked. The gateway url
     * points at a closed port on purpose - the point of the test is that the close is initiated, and a
     * request that never leaves the platform could not prove that.
     */
    public function testClosingThroughAnInterfaceThatCanCloseIsStillInitiated(): void
    {
        $this->pointAlipayAtAnUnreachableGateway();

        $client = $this->getBrowser();
        $seed = $this->seedPaymentOrder();

        $path = \sprintf(self::CLOSE_PATH, $seed['paymentNo']);
        $this->postJson($client, $path, $this->signedPayload('POST', $path));

        $response = $client->getResponse();

        // the guard let the call through, so the answer is the one of the channel and not a refusal of
        // the declaration - whatever the channel said, it said it to a request that really happened
        static::assertNotSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());
        static::assertStringNotContainsString(
            PaymentException::PROVIDER_LACKS_CAPABILITY,
            (string) $response->getContent()
        );

        // and the attempt is on record: the close left the platform and the channel was addressed
        static::assertSame(1, $this->closeAttemptsOf($seed['id']));
    }

    /**
     * Stripe as the platform offers it: the merchant account configured and the checkout channel
     * switched on, and the contract app authorized for both. The credentials are not used by this test
     * - the close never reaches the api of stripe - they only have to exist, because a configured
     * interface is one with credentials.
     */
    private function authorizeStripe(): void
    {
        $cipher = static::getContainer()->get(PaymentCredentialCipher::class);
        $context = Context::createDefaultContext();

        /** @var EntityRepository<PaymentProviderConfigCollection> $providerRepository */
        $providerRepository = static::getContainer()->get('payment_provider_config.repository');
        $providerId = $providerRepository->searchIds(
            (new Criteria())
                ->addFilter(new EqualsFilter('providerTechnicalName', self::STRIPE_PROVIDER))
                ->setLimit(1),
            $context
        )->firstId();

        $providerData = [
            'providerTechnicalName' => self::STRIPE_PROVIDER,
            'enabled' => true,
            'credentials' => $cipher->encrypt([
                'secret_key' => 'sk_test_contract',
                'publishable_key' => 'pk_test_contract',
                'webhook_secret' => 'whsec_contract',
            ]),
        ];

        if ($providerId !== null) {
            $providerRepository->update([['id' => $providerId] + $providerData], $context);
        } else {
            $providerRepository->create([$providerData], $context);
        }

        /** @var EntityRepository<PaymentChannelConfigCollection> $channelRepository */
        $channelRepository = static::getContainer()->get('payment_channel_config.repository');
        $channelId = $channelRepository->searchIds(
            (new Criteria())
                ->addFilter(
                    new EqualsFilter('providerTechnicalName', self::STRIPE_PROVIDER),
                    new EqualsFilter('channelTechnicalName', self::STRIPE_CHANNEL)
                )
                ->setLimit(1),
            $context
        )->firstId();

        if ($channelId !== null) {
            $channelRepository->update([['id' => $channelId, 'enabled' => true]], $context);
        } else {
            $channelRepository->create([[
                'providerTechnicalName' => self::STRIPE_PROVIDER,
                'channelTechnicalName' => self::STRIPE_CHANNEL,
                'enabled' => true,
            ]], $context);
        }

        /** @var EntityRepository<PaymentAppProviderCollection> $appProviderRepository */
        $appProviderRepository = static::getContainer()->get('payment_app_provider.repository');
        $appProviderRepository->create([[
            'paymentAppId' => $this->paymentAppId,
            'providerTechnicalName' => self::STRIPE_PROVIDER,
            // the app collects on the merchant account of the platform tenant, which is what the
            // retired `mode = 'platform'` used to say
            'sourceAppId' => $this->platformTenantId($context),
            'enabled' => true,
        ]], $context);

        // the account an app inherits is only charged for an interface the account holder collects
        // through itself: the chain is followed through the authorizations of every hop, so the tenant
        // of the platform needs its own stripe authorization before the app can inherit it. Without
        // this row the chain stops at the platform and the caller reads "not enabled" instead of the
        // answer of the declaration this test is about.
        $platformId = $this->platformTenantId($context);
        $platformAuthorization = $appProviderRepository->searchIds(
            (new Criteria())
                ->addFilter(
                    new EqualsFilter('paymentAppId', $platformId),
                    new EqualsFilter('providerTechnicalName', self::STRIPE_PROVIDER)
                )
                ->setLimit(1),
            $context
        )->firstId();

        if ($platformAuthorization === null) {
            $appProviderRepository->create([[
                'paymentAppId' => $platformId,
                'providerTechnicalName' => self::STRIPE_PROVIDER,
                // the tenant is the root of every chain: it collects on its own account
                'sourceAppId' => null,
                'enabled' => true,
            ]], $context);
        } else {
            $appProviderRepository->update([['id' => $platformAuthorization, 'enabled' => true]], $context);
        }

        /** @var EntityRepository<PaymentAppChannelCollection> $appChannelRepository */
        $appChannelRepository = static::getContainer()->get('payment_app_channel.repository');
        $appChannelRepository->create([[
            'paymentAppId' => $this->paymentAppId,
            'channelTechnicalName' => self::STRIPE_CHANNEL,
            'enabled' => true,
        ]], $context);
    }

    /**
     * Rewrites the gateway url of the alipay merchant account to a closed local port: the close is
     * built and sent, and the connection is refused immediately instead of waiting for a sandbox.
     */
    private function pointAlipayAtAnUnreachableGateway(): void
    {
        $cipher = static::getContainer()->get(PaymentCredentialCipher::class);
        $context = Context::createDefaultContext();

        /** @var EntityRepository<PaymentProviderConfigCollection> $providerRepository */
        $providerRepository = static::getContainer()->get('payment_provider_config.repository');
        $id = $providerRepository->searchIds(
            (new Criteria())
                ->addFilter(new EqualsFilter('providerTechnicalName', AlipayApi::PROVIDER))
                ->setLimit(1),
            $context
        )->firstId();

        static::assertIsString($id, 'the alipay platform of the fixture has to exist');

        $config = $providerRepository->search(new Criteria([$id]), $context)->getEntities()->first();
        static::assertNotNull($config);

        $credentials = $cipher->decrypt($config->credentials);
        $credentials[AlipayApi::GATEWAY_URL] = 'http://127.0.0.1:1';

        $providerRepository->update([[
            'id' => $id,
            'credentials' => $cipher->encrypt($credentials),
        ]], $context);
    }

    /**
     * How many close attempts are on record for the order - the row the closer writes before it sends.
     */
    private function closeAttemptsOf(string $paymentOrderId): int
    {
        $count = static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT COUNT(*) FROM payment_transaction WHERE payment_order_id = UNHEX(:orderId) AND type = :type',
            ['orderId' => $paymentOrderId, 'type' => 'close']
        );

        static::assertIsNumeric($count);

        return (int) $count;
    }
}
