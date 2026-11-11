<?php
declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\Payment\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\System\Payment\Integration\Alipay\Api\AlipayApi;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Config\PaymentProviderConfigService;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Symfony\Component\HttpFoundation\Response;

/**
 * The built-in Alipay close capability still initiates a provider request.
 *
 * @internal
 */
#[Package('framework')]
class PaymentCapabilityGuardTest extends TestCase
{
    use IntegrationTestBehaviour;
    use PaymentApiFixtureTrait;

    private const CLOSE_PATH = '/payment-api/v1/payment-orders/%s/close';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpAlipayPlatform();
        $this->createContractApp();
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
     * Rewrites the gateway url of the alipay merchant account to a closed local port: the close is
     * built and sent, and the connection is refused immediately instead of waiting for a sandbox.
     */
    private function pointAlipayAtAnUnreachableGateway(): void
    {
        $container = static::getContainer();
        $service = $container->get(PaymentProviderConfigService::class);
        $service->saveProvider($container->get(PaymentProviderRegistry::class)->get(AlipayApi::PROVIDER), true, [
            AlipayApi::GATEWAY_URL => 'http://127.0.0.1:1',
        ], []);
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
