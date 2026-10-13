<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Service;

use Payum\Core\Request\Notify;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentAppEntity;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentNotificationCollection;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentNotificationEntity;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentOrderEntity;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentRefundEntity;
use Shopwell\Core\System\Payment\Event\PaymentOrderPaidEvent;
use Shopwell\Core\System\Payment\Gateway\NotificationResult;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\PaymentOrderStates;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Shopwell\Core\System\Payment\Struct\PaymentNotificationData;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Processes the asynchronous notifications of the payment interfaces.
 *
 * A notification is stored before it is even looked at, so it can never be lost, and the outcome of
 * the processing is written on it afterwards - including the reason why it was rejected. The order of
 * the steps matters:
 *
 *  1. the references are extracted without credentials, only to locate the payment order,
 *  2. the credentials of that order are resolved and the interface verifies the notification,
 *  3. the amount the interface confirms is compared with the amount of the platform,
 *  4. and only then is anything written.
 *
 * The notification is attributed to a payment *interface* and not to a channel: it is the interface
 * that signs - or, in the case of wechat, encrypts - the body, so its credentials are the ones needed
 * to read it at all. The channel of the payment is only known once the order is found.
 *
 * Processing is idempotent: a notification that was already applied is a duplicate and changes nothing.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentNotificationService
{
    /**
     * The headers that are never stored.
     */
    private const HIDDEN_HEADERS = ['authorization', 'cookie', 'proxy-authorization'];

    /**
     * @param EntityRepository<PaymentNotificationCollection> $notificationRepository
     */
    public function __construct(
        private readonly EntityRepository $notificationRepository,
        private readonly PaymentProviderRegistry $providerRegistry,
        private readonly PaymentOrderService $orderService,
        private readonly PaymentRefundService $refundService,
        private readonly PaymentConfigResolver $configResolver,
        private readonly PaymentGatewayFactory $gatewayFactory,
        private readonly PaymentWebhookService $webhookService,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @param array<string, string|list<string>> $headers
     * @param string|null $paymentNoHint the payment number of the notify url, set whenever the
     *                                   interface was given a per payment notify url - which is the
     *                                   only way to match an encrypted notification to its app
     *
     * @throws PaymentException
     */
    public function process(
        string $providerTechnicalName,
        string $rawBody,
        array $headers,
        ?string $remoteIp,
        ?string $paymentNoHint = null
    ): PaymentNotificationEntity {
        $notificationId = $this->recordReceived($providerTechnicalName, $rawBody, $headers, $remoteIp);

        $state = [
            'result' => PaymentNotificationEntity::RESULT_ERROR,
            'signatureValid' => false,
            'orderId' => null,
            'message' => null,
        ];

        try {
            $this->handle($providerTechnicalName, $rawBody, $headers, $remoteIp, $paymentNoHint, $state);
        } catch (\Throwable $exception) {
            $state['message'] = $exception->getMessage();

            if ($exception instanceof PaymentException) {
                throw $exception;
            }

            throw PaymentException::gatewayError($providerTechnicalName, $exception->getMessage());
        } finally {
            $this->conclude($notificationId, $state);
        }

        return $this->getNotification($notificationId);
    }

    /**
     * @param array{result: string, signatureValid: bool, orderId: string|null, message: string|null} $state
     * @param array<string, string|list<string>> $headers
     *
     * @throws PaymentException
     */
    private function handle(
        string $providerTechnicalName,
        string $rawBody,
        array $headers,
        ?string $remoteIp,
        ?string $paymentNoHint,
        array &$state
    ): void {
        $provider = $this->providerRegistry->get($providerTechnicalName);
        $data = $provider->notificationExtractor->extract($rawBody, $headers);

        if ($paymentNoHint !== null && $paymentNoHint !== '') {
            $data = new PaymentNotificationData($paymentNoHint, $data->outTradeNo, $data->outPaymentNo, $data->refundNo);
        }

        // the extracted references are not trusted yet, they only tell us which order the notification
        // is about - which in turn tells us which credentials are needed to verify it
        $refund = $data->refundNo === null ? null : $this->refundService->findByRefundNo($data->refundNo);

        $order = $refund instanceof PaymentRefundEntity
            ? $this->orderService->getById($refund->paymentOrderId)
            : ($data->paymentNo === null ? null : $this->orderService->findByPaymentNo($data->paymentNo));

        $app = $order?->paymentApp;

        if (!$order instanceof PaymentOrderEntity || !$app instanceof PaymentAppEntity) {
            $state['result'] = PaymentNotificationEntity::RESULT_UNMATCHED;

            throw PaymentException::notificationOrderUnknown(
                $providerTechnicalName,
                $data->paymentNo ?? $data->refundNo ?? $data->outTradeNo ?? $data->outPaymentNo ?? ''
            );
        }

        $state['orderId'] = $order->id;

        $resolved = $this->configResolver->resolve(
            $app,
            $order->channelTechnicalName,
            $order->currency,
            $order->device,
            $order->channelParams ?? []
        );

        $model = PaymentGatewayModel::forNotification($rawBody, $headers, $remoteIp);
        $gateway = $this->gatewayFactory->create($resolved, (string) $order->notifyUrl, (string) $order->returnUrl);

        $gateway->execute(new Notify($model));
        $result = PaymentGatewayModel::notificationResult($model, $order->channelTechnicalName);

        if (!$result->signatureValid) {
            $state['result'] = PaymentNotificationEntity::RESULT_INVALID_SIGNATURE;

            throw PaymentException::notificationInvalid($providerTechnicalName, 'the signature of the notification is invalid');
        }

        $state['signatureValid'] = true;

        $expected = $refund instanceof PaymentRefundEntity ? $refund->amount : $order->amount;

        if ($result->amount === null || $result->amount !== $expected) {
            $state['result'] = PaymentNotificationEntity::RESULT_AMOUNT_MISMATCH;

            throw PaymentException::amountMismatch($order->paymentNo, $expected, $result->amount ?? 0);
        }

        if ($refund instanceof PaymentRefundEntity) {
            $this->refundService->applyNotification($refund, $order, $result);

            return;
        }

        $this->applyPayment($order, $result, $state);
    }

    /**
     * @param array{result: string, signatureValid: bool, orderId: string|null, message: string|null} $state
     *
     * @throws PaymentException
     */
    private function applyPayment(PaymentOrderEntity $order, NotificationResult $result, array &$state): void
    {
        if ($order->paidAt !== null) {
            $state['result'] = PaymentNotificationEntity::RESULT_DUPLICATE;

            return;
        }

        // the interface reports a payment that is not settled yet, or one it gave up on. The order stays
        // payable and expires on its own, so nothing is written - the notification stays for support.
        if (!$result->succeeded) {
            $state['result'] = PaymentNotificationEntity::RESULT_IGNORED;

            return;
        }

        // an order may be paid without the app ever having asked for the pay parameters
        $this->orderService->markOpened($order);
        $this->orderService->markPaid($order, $result->amount ?? $order->amount, $result->outPaymentNo, $result->raw);

        $paid = $this->orderService->getById($order->id);

        $this->eventDispatcher->dispatch(new PaymentOrderPaidEvent($paid, Context::createDefaultContext()));

        $this->webhookService->enqueue($paid, PaymentWebhookService::EVENT_ORDER_PAID, [
            'amount' => $paid->paidAmount,
            'currency' => $paid->currency,
            'provider' => $paid->providerTechnicalName,
            'channel' => $paid->channelTechnicalName,
            'out_payment_no' => $paid->outPaymentNo,
            'paid_at' => $paid->paidAt?->format(\DateTimeInterface::ATOM),
            'state' => PaymentOrderStates::STATE_PAID,
        ]);
    }

    /**
     * @param array<string, string|list<string>> $headers
     */
    private function recordReceived(string $providerTechnicalName, string $rawBody, array $headers, ?string $remoteIp): string
    {
        $id = Uuid::randomHex();

        $this->notificationRepository->create([[
            'id' => $id,
            'providerTechnicalName' => $providerTechnicalName,
            'rawBody' => $rawBody,
            'headers' => $this->sanitizeHeaders($headers),
            'remoteIp' => $remoteIp,
            'signatureValid' => false,
            'processed' => false,
        ]], Context::createDefaultContext());

        return $id;
    }

    /**
     * @param array{result: string, signatureValid: bool, orderId: string|null, message: string|null} $state
     */
    private function conclude(string $notificationId, array $state): void
    {
        $this->notificationRepository->update([[
            'id' => $notificationId,
            'paymentOrderId' => $state['orderId'],
            'signatureValid' => $state['signatureValid'],
            'processed' => \in_array($state['result'], [
                PaymentNotificationEntity::RESULT_OK,
                PaymentNotificationEntity::RESULT_DUPLICATE,
                PaymentNotificationEntity::RESULT_IGNORED,
            ], true),
            'processResult' => mb_substr($state['result'], 0, 32),
            'errorMessage' => $state['message'] === null ? null : mb_substr($state['message'], 0, 512),
        ]], Context::createDefaultContext());
    }

    private function getNotification(string $id): PaymentNotificationEntity
    {
        $notification = $this->notificationRepository->search(new Criteria([$id]), Context::createDefaultContext())->getEntities()->first();

        if (!$notification instanceof PaymentNotificationEntity) {
            throw PaymentException::notificationNotFound($id);
        }

        return $notification;
    }

    /**
     * @param array<string, string|list<string>> $headers
     *
     * @return array<string, string|list<string>>
     */
    private function sanitizeHeaders(array $headers): array
    {
        $sanitized = [];

        foreach ($headers as $name => $value) {
            $key = strtolower((string) $name);
            $sanitized[$key] = \in_array($key, self::HIDDEN_HEADERS, true) ? '[hidden]' : $value;
        }

        return $sanitized;
    }
}
