<?php
declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\Payment\Api;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\System\Payment\PaymentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contract of `POST /payment-api/v1/payment-orders/{paymentNo}/refunds`.
 *
 * @internal
 */
#[Package('framework')]
class PaymentRefundApiTest extends TestCase
{
    use IntegrationTestBehaviour;
    use PaymentApiFixtureTrait;

    private const PATH_ORDERS = '/payment-api/v1/payment-orders';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAlipayPlatform();
        $this->createContractApp();
    }

    public function testRefundUnknownOrder(): void
    {
        $client = $this->getBrowser();

        $path = self::PATH_ORDERS . '/PG20990101000000DEADBEEF/refunds';
        $payload = $this->signedPayload('POST', $path, [
            'out_refund_no' => 'OR' . bin2hex(random_bytes(8)),
            'amount' => 100,
        ]);

        $this->postJson($client, $path, $payload);

        self::assertError($client->getResponse(), Response::HTTP_NOT_FOUND, PaymentException::ORDER_NOT_FOUND);
    }

    public function testRefundMissingOutRefundNo(): void
    {
        $client = $this->getBrowser();

        $seed = $this->seedPaymentOrder();
        $path = self::PATH_ORDERS . '/' . $seed['paymentNo'] . '/refunds';

        $this->postJson($client, $path, $this->signedPayload('POST', $path, ['amount' => 100]));

        self::assertError($client->getResponse(), Response::HTTP_BAD_REQUEST, PaymentException::MISSING_PARAMETER);
    }

    public function testRefundMissingAmount(): void
    {
        $client = $this->getBrowser();

        $seed = $this->seedPaymentOrder();
        $path = self::PATH_ORDERS . '/' . $seed['paymentNo'] . '/refunds';

        $this->postJson($client, $path, $this->signedPayload('POST', $path, [
            'out_refund_no' => 'OR' . bin2hex(random_bytes(8)),
        ]));

        self::assertError($client->getResponse(), Response::HTTP_BAD_REQUEST, PaymentException::MISSING_PARAMETER);
    }

    public function testRefundUnpaidOrderIsConflict(): void
    {
        $client = $this->getBrowser();

        // the seeded order is opened, nothing was paid on it yet
        $seed = $this->seedPaymentOrder();

        $path = self::PATH_ORDERS . '/' . $seed['paymentNo'] . '/refunds';
        $payload = $this->signedPayload('POST', $path, [
            'out_refund_no' => 'OR' . bin2hex(random_bytes(8)),
            'amount' => 100,
        ]);

        $this->postJson($client, $path, $payload);

        self::assertError($client->getResponse(), Response::HTTP_CONFLICT, PaymentException::REFUND_NOT_ALLOWED);
    }
}
