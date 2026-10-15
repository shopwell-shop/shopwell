<?php
declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\Payment\Api;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\System\Payment\Order\PaymentOrderStates;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contract of `GET /payment-api/v1/return/{paymentNo}`: the payer is sent back to the return url of
 * the app with the payment numbers and the current state. It is a browser request and therefore
 * carries no app credentials.
 *
 * @internal
 */
#[Package('framework')]
class PaymentReturnTest extends TestCase
{
    use IntegrationTestBehaviour;
    use PaymentApiFixtureTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAlipayPlatform();
        $this->createContractApp();
    }

    public function testRedirectsToTheReturnUrlOfTheApp(): void
    {
        $client = $this->getBrowser();

        $seed = $this->seedPaymentOrder(['returnUrl' => 'https://app.example.com/payback?source=alipay']);

        $client->request('GET', '/payment-api/v1/return/' . $seed['paymentNo']);

        $response = $client->getResponse();
        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());

        $location = $response->headers->get('Location');
        static::assertIsString($location);
        static::assertStringStartsWith('https://app.example.com/payback?source=alipay&', $location);

        $query = [];
        parse_str((string) parse_url($location, \PHP_URL_QUERY), $query);
        static::assertSame($seed['paymentNo'], $query['payment_no'] ?? null);
        static::assertSame($seed['outTradeNo'], $query['out_trade_no'] ?? null);
        static::assertSame(PaymentOrderStates::STATE_OPENED, $query['state'] ?? null);
    }

    public function testUnknownOrderIsNotFound(): void
    {
        $client = $this->getBrowser();

        $client->request('GET', '/payment-api/v1/return/PG20990101000000DEADBEEF');

        $response = $client->getResponse();
        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        static::assertSame('', (string) $response->getContent());
    }

    public function testOrderWithoutReturnUrlIsNotFound(): void
    {
        $client = $this->getBrowser();

        // neither the order nor the app registered a return url
        $seed = $this->seedPaymentOrder();

        $client->request('GET', '/payment-api/v1/return/' . $seed['paymentNo']);

        $response = $client->getResponse();
        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        static::assertSame('', (string) $response->getContent());
    }
}
