<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\Payment\Order;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\App\PaymentAppCollection;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Gateway\Request\NotificationResult;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentRefund\PaymentRefundCollection;
use Shopwell\Core\System\Payment\Order\PaymentOrderCollection;
use Shopwell\Core\System\Payment\Order\PaymentOrderService;
use Shopwell\Core\System\Payment\Order\PaymentOrderStates;
use Shopwell\Core\System\Payment\Order\PaymentRefundService;
use Shopwell\Core\System\Payment\Order\PaymentRefundStates;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookDeliveryCollection;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookDispatcher;

/**
 * @internal
 */
#[Package('framework')]
class PaymentRefundConsistencyTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testApplyRefundUsesTheDatabaseAmountsInsteadOfTheStaleEntity(): void
    {
        $orderId = $this->createOrder();
        $orderService = static::getContainer()->get(PaymentOrderService::class);
        $stale = $orderService->getById($orderId);

        $orderService->applyRefund($stale, 400);
        $refunded = $orderService->applyRefund($stale, 600);

        static::assertSame(1000, $refunded->refundedAmount);
        static::assertSame(
            $this->stateId(PaymentOrderStates::STATE_MACHINE, PaymentOrderStates::STATE_REFUNDED),
            $refunded->stateId
        );
    }

    public function testDuplicateSuccessNotificationBooksOnceAndKeepsOneWebhook(): void
    {
        $orderId = $this->createOrder();
        $refundId = $this->createProcessingRefund($orderId, 400);
        $orderService = static::getContainer()->get(PaymentOrderService::class);
        $refundService = static::getContainer()->get(PaymentRefundService::class);
        $order = $orderService->getById($orderId);
        $refund = $refundService->getRefund($refundId);
        $notification = new NotificationResult(
            true,
            true,
            outRefundId: 'channel-refund-1',
            amount: 400,
            raw: ['status' => 'done']
        );

        $refundService->applyNotification($refund, $order, $notification);
        $refundService->applyNotification($refund, $order, $notification);

        $booked = $orderService->getById($orderId);
        static::assertSame(400, $booked->refundedAmount);
        static::assertSame(
            $this->stateId(PaymentOrderStates::STATE_MACHINE, PaymentOrderStates::STATE_REFUNDED_PARTIALLY),
            $booked->stateId
        );
        static::assertNotNull($refundService->getRefund($refundId)->refundedAt);

        /** @var EntityRepository<PaymentWebhookDeliveryCollection> $deliveryRepository */
        $deliveryRepository = static::getContainer()->get('payment_webhook_delivery.repository');
        $deliveries = $deliveryRepository->searchIds(
            (new Criteria())->addFilter(
                new EqualsFilter('paymentOrderId', $orderId),
                new EqualsFilter('event', PaymentWebhookDispatcher::EVENT_REFUND_FINISHED),
                new EqualsFilter('subject', $refundId)
            ),
            Context::createDefaultContext()
        );

        static::assertSame(1, $deliveries->getTotal());
    }

    private function createOrder(): string
    {
        $context = Context::createDefaultContext();
        $appId = Uuid::randomHex();

        /** @var EntityRepository<PaymentAppCollection> $appRepository */
        $appRepository = static::getContainer()->get('payment_app.repository');
        $appRepository->create([[
            'id' => $appId,
            'technicalName' => 'refund_consistency_' . bin2hex(random_bytes(6)),
            'name' => 'Payment refund consistency test app',
            'type' => PaymentAppEntity::TYPE_EXTERNAL,
            'appSecret' => static::getContainer()->get(PaymentCredentialCipher::class)->encryptValue('secret'),
            'active' => true,
        ]], $context);

        $orderId = Uuid::randomHex();

        /** @var EntityRepository<PaymentOrderCollection> $orderRepository */
        $orderRepository = static::getContainer()->get('payment_order.repository');
        $orderRepository->create([[
            'id' => $orderId,
            'paymentNo' => 'PG' . strtoupper(bin2hex(random_bytes(8))),
            'outTradeNo' => 'OT' . strtoupper(bin2hex(random_bytes(8))),
            'paymentAppId' => $appId,
            'providerTechnicalName' => 'alipay',
            'channelTechnicalName' => 'alipay_h5',
            'amount' => 1000,
            'paidAmount' => 1000,
            'refundedAmount' => 0,
            'currency' => 'CNY',
            'subject' => 'Payment refund consistency test',
            'stateId' => $this->stateId(PaymentOrderStates::STATE_MACHINE, PaymentOrderStates::STATE_PAID),
            'paidAt' => new \DateTimeImmutable(),
            'notifyUrl' => 'https://app.example.test/payment-webhook',
        ]], $context);

        return $orderId;
    }

    private function createProcessingRefund(string $orderId, int $amount): string
    {
        $refundId = Uuid::randomHex();

        /** @var EntityRepository<PaymentRefundCollection> $refundRepository */
        $refundRepository = static::getContainer()->get('payment_refund.repository');
        $refundRepository->create([[
            'id' => $refundId,
            'refundNo' => 'PR' . strtoupper(bin2hex(random_bytes(8))),
            'paymentOrderId' => $orderId,
            'outRefundNo' => 'OR' . strtoupper(bin2hex(random_bytes(8))),
            'amount' => $amount,
            'currency' => 'CNY',
            'stateId' => $this->stateId(PaymentRefundStates::STATE_MACHINE, PaymentRefundStates::STATE_PROCESSING),
        ]], Context::createDefaultContext());

        return $refundId;
    }

    private function stateId(string $machine, string $state): string
    {
        $id = static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT LOWER(HEX(state.id)) FROM state_machine_state state
             INNER JOIN state_machine machine ON machine.id = state.state_machine_id
             WHERE machine.technical_name = :machine AND state.technical_name = :state',
            ['machine' => $machine, 'state' => $state]
        );

        static::assertIsString($id);

        return $id;
    }
}
