<?php
declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\Payment\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderStates;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contract of `POST /payment-api/v1/notify/{provider}/{paymentNo}`: the interface calls this endpoint
 * without any app credential, an unknown interface is a 404 and a rejected notification is still
 * acknowledged with a 200 and the ack body of the interface - alipay repeats its notification until
 * it reads the plain word `success`.
 *
 * @internal
 */
#[Package('framework')]
class PaymentNotifyTest extends TestCase
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

    public function testUnknownProviderIsNotFound(): void
    {
        $client = $this->getBrowser();

        $client->request('POST', '/payment-api/v1/notify/unknown_provider/PG20990101000000DEADBEEF');

        $response = $client->getResponse();
        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        static::assertSame('', (string) $response->getContent());
    }

    public function testAlipayNotificationForUnknownOrderIsAcknowledged(): void
    {
        $client = $this->getBrowser();

        $paymentNo = 'PG20990101000000DEADBEEF';
        $body = $this->alipayNotificationBody($paymentNo);

        $client->request('POST', '/payment-api/v1/notify/alipay/' . $paymentNo, [], [], [
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
        ], $body);

        $response = $client->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('success', (string) $response->getContent());
        static::assertStringContainsString('text/plain', (string) $response->headers->get('Content-Type'));

        // the notification is stored before it is looked at, the outcome is written on it afterwards
        $notification = $this->fetchNotification($body);
        static::assertIsArray($notification);
        static::assertSame(PaymentNotificationEntity::RESULT_UNMATCHED, $notification['process_result']);
        static::assertSame(0, (int) $notification['processed']);
        static::assertSame(0, (int) $notification['signature_valid']);
        static::assertNull($notification['payment_order_id']);
    }

    public function testAlipayNotificationWithInvalidSignatureIsAcknowledged(): void
    {
        $client = $this->getBrowser();

        $seed = $this->seedPaymentOrder();
        $paymentNo = $seed['paymentNo'];
        $body = $this->alipayNotificationBody($paymentNo);

        $client->request('POST', '/payment-api/v1/notify/alipay/' . $paymentNo, [], [], [
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
        ], $body);

        $response = $client->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('success', (string) $response->getContent());

        $connection = static::getContainer()->get(Connection::class);

        $notification = $this->fetchNotification($body);
        static::assertIsArray($notification);
        static::assertSame(PaymentNotificationEntity::RESULT_INVALID_SIGNATURE, $notification['process_result']);
        static::assertSame(0, (int) $notification['processed']);
        static::assertSame(0, (int) $notification['signature_valid']);

        $orderRow = $connection->fetchAssociative(
            'SELECT id, paid_at FROM payment_order WHERE payment_no = :paymentNo',
            ['paymentNo' => $paymentNo]
        );
        static::assertIsArray($orderRow);
        static::assertSame($orderRow['id'], $notification['payment_order_id']);

        // a notification that does not verify never pays the order
        static::assertNull($orderRow['paid_at']);

        $getPath = self::PATH_ORDERS . '/' . $paymentNo;
        $client->request('GET', $getPath, $this->signedPayload('GET', $getPath));
        $orderAfter = self::decodeResponse($client->getResponse());
        static::assertSame(PaymentOrderStates::STATE_OPENED, $orderAfter['state']);
        static::assertNull($orderAfter['paid_at']);
    }

    /**
     * A form encoded alipay notification whose signature does not verify.
     */
    private function alipayNotificationBody(string $paymentNo): string
    {
        return http_build_query([
            'out_trade_no' => $paymentNo,
            'trade_no' => '2026101322001400000000000001',
            'trade_status' => 'TRADE_SUCCESS',
            'total_amount' => '1.00',
            'currency' => 'CNY',
            'gmt_payment' => '2026-10-13 12:00:00',
            'sign' => base64_encode(random_bytes(32)),
            'sign_type' => 'RSA2',
        ]);
    }

    /**
     * @return array<string, mixed>|false
     */
    private function fetchNotification(string $rawBody): array|false
    {
        return static::getContainer()->get(Connection::class)->fetchAssociative(
            'SELECT * FROM payment_notification WHERE raw_body = :rawBody',
            ['rawBody' => $rawBody]
        );
    }
}
