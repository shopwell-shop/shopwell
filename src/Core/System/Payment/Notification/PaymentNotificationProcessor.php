<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Notification;

use Payum\Core\Request\Notify;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\Gateway\NotificationResult;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Notification\Struct\PaymentNotificationData;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderPaidHandler;
use Shopwell\Core\System\Payment\Order\PaymentOrderService;
use Shopwell\Core\System\Payment\Order\Refund\PaymentRefundEntity;
use Shopwell\Core\System\Payment\Order\Refund\PaymentRefundService;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\PaymentConfigResolver;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;

/**
 * Processes the asynchronous notifications of the payment interfaces.
 *
 * A notification is stored before it is even looked at, so it can never be lost, and the outcome of
 * the processing is written on it afterwards - including the reason why it was rejected. The order of
 * the steps matters:
 *
 *  1. the references are extracted without credentials, only to locate the payment order,
 *  2. the credentials of that order are resolved and the interface verifies the notification,
 *  3. the amount and the currency the interface confirms are compared with the ones of the platform,
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
final class PaymentNotificationProcessor
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
        private readonly PaymentOrderPaidHandler $paidHandler,
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

            throw PaymentException::gatewayError($providerTechnicalName, $exception->getMessage(), $exception);
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
        $gateway = $resolved->provider->gatewayFactory->createForPayment($resolved, (string) $order->notifyUrl, (string) $order->returnUrl);

        $gateway->execute(new Notify($model));
        $result = $model->result(NotificationResult::class, $order->channelTechnicalName);

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

        // the currency is judged as strictly as the amount: the same number in another currency is not
        // the payment of this order. A currency the interface does not report cannot be disproved -
        // rejecting it would leave a payment that really happened unpaid while the notification keeps
        // retrying - so only a reported currency has to match, and it is compared case insensitively
        // because `cny` and `CNY` are the same currency.
        if ($result->currency !== null && strtoupper($result->currency) !== strtoupper($order->currency)) {
            // a result of its own: support filters this table by what went wrong, and a currency
            // mismatch reported as an amount mismatch would send them looking at the amount check
            $state['result'] = PaymentNotificationEntity::RESULT_CURRENCY_MISMATCH;

            throw PaymentException::currencyMismatch($order->paymentNo, $order->currency, $result->currency);
        }

        if ($refund instanceof PaymentRefundEntity) {
            $this->refundService->applyNotification($refund, $order, $result);

            // the notification is applied to the refund, so it is fully processed - whether the
            // refund is settled by it or stays processing is not an error of the notification
            $state['result'] = PaymentNotificationEntity::RESULT_OK;

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

            // the payment is written, but one of its consequences may have failed the first time - a
            // duplicate notification is the retry window for exactly that
            $this->paidHandler->announce($order);

            return;
        }

        // the interface reports a payment that is not settled yet, or one it gave up on. The order stays
        // payable and expires on its own, so nothing is written - the notification stays for support.
        if (!$result->succeeded) {
            $state['result'] = PaymentNotificationEntity::RESULT_IGNORED;

            return;
        }

        // an order may be paid without the app ever having asked for the pay parameters
        $this->paidHandler->handle($order, $result->amount ?? $order->amount, $result->outPaymentNo, $result->raw);

        // the payment is applied and the app is told about it, which is the only fully successful
        // outcome: every branch above either returns early or throws
        $state['result'] = PaymentNotificationEntity::RESULT_OK;
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
            // 32 is the width of the column: every result constant is far shorter, and a shortened one
            // would read like a different outcome in exactly the table support diagnoses with
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
