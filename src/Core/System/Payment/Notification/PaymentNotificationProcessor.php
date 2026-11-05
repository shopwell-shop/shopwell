<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Notification;

use Payum\Core\Request\Notify;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\Exception\PaymentGatewayException;
use Shopwell\Core\System\Payment\Exception\PaymentNotificationException;
use Shopwell\Core\System\Payment\Exception\PaymentOrderException;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\Request\NotificationResult;
use Shopwell\Core\System\Payment\Notification\Struct\PaymentNotificationData;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentAttempt\PaymentAttemptCollection;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentAttempt\PaymentAttemptEntity;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentRefund\PaymentRefundEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderPaidHandler;
use Shopwell\Core\System\Payment\Order\PaymentOrderService;
use Shopwell\Core\System\Payment\Order\PaymentRefundService;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Config\PaymentConfigResolver;
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
     * @param EntityRepository<PaymentAttemptCollection> $attemptRepository
     */
    public function __construct(
        private readonly EntityRepository $notificationRepository,
        private readonly PaymentProviderRegistry $providerRegistry,
        private readonly PaymentOrderService $orderService,
        private readonly PaymentRefundService $refundService,
        private readonly PaymentConfigResolver $configResolver,
        private readonly PaymentOrderPaidHandler $paidHandler,
        private readonly EntityRepository $attemptRepository,
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

        $context = new PaymentNotificationProcessingContext($providerTechnicalName, $rawBody, $headers, $remoteIp, $paymentNoHint);

        try {
            $this->handle($context);
        } catch (\Throwable $exception) {
            $context->message = $exception->getMessage();

            if ($exception instanceof PaymentException) {
                throw $exception;
            }

            throw PaymentGatewayException::gatewayError($providerTechnicalName, $exception->getMessage(), $exception);
        } finally {
            $this->conclude($notificationId, $context);
        }

        return $this->getNotification($notificationId);
    }

    /**
     * @throws PaymentException
     */
    private function handle(PaymentNotificationProcessingContext $context): void
    {
        $data = $this->extractReferences($context);
        $attempt = $this->resolveAttempt($data, $context->paymentNoHint);
        $order = $attempt === null ? null : $this->orderService->getById($attempt->paymentOrderId);
        $app = $order?->paymentApp;

        if (!$order instanceof PaymentOrderEntity || !$app instanceof PaymentAppEntity) {
            $context->result = PaymentNotificationEntity::RESULT_UNMATCHED;

            throw PaymentNotificationException::notificationOrderUnknown(
                $context->providerTechnicalName,
                $data->paymentNo ?? $data->refundNo ?? $data->outTradeNo ?? $data->outPaymentNo ?? ''
            );
        }

        if ($attempt === null || $attempt->providerTechnicalName !== $context->providerTechnicalName) {
            throw PaymentNotificationException::notificationInvalid($context->providerTechnicalName, 'the provider does not match the payment attempt');
        }

        $context->orderId = $order->id;
        $context->attemptId = $attempt->id;
        // A delayed callback belongs to its original attempt, including its account and product.
        $order->activeAttemptId = $attempt->id;
        $order->activeAttempt = $attempt;
        $result = $this->verifyNotification($context, $order, $app);

        if (!$result->signatureValid) {
            $context->result = PaymentNotificationEntity::RESULT_INVALID_SIGNATURE;

            throw PaymentNotificationException::notificationInvalid($context->providerTechnicalName, 'the signature of the notification is invalid');
        }

        $context->signatureValid = true;
        $refund = null;
        if ($result->isRefund) {
            $refund = $result->refundNo === null ? null : $this->refundService->findByRefundNo($result->refundNo);
            if (!$refund instanceof PaymentRefundEntity || $refund->paymentOrderId !== $order->id || $refund->paymentAttemptId !== $attempt->id) {
                $context->result = PaymentNotificationEntity::RESULT_UNMATCHED;

                throw PaymentNotificationException::notificationInvalid($context->providerTechnicalName, 'the signed refund does not belong to the payment');
            }
        } elseif ($result->paymentNo !== $attempt->attemptNo) {
            $context->result = PaymentNotificationEntity::RESULT_UNMATCHED;

            throw PaymentNotificationException::notificationInvalid($context->providerTechnicalName, 'the signed payment reference does not match the payment');
        }

        $expected = $refund instanceof PaymentRefundEntity ? $refund->amount : $order->amount;

        if (!$this->amountMatches($expected, $result)) {
            $context->result = PaymentNotificationEntity::RESULT_AMOUNT_MISMATCH;

            throw PaymentOrderException::amountMismatch($order->paymentNo, $expected, $result->amount ?? 0);
        }

        if ($result->currency !== null && !$this->currencyMatches($order->currency, $result->currency)) {
            // a result of its own: support filters this table by what went wrong, and a currency
            // mismatch reported as an amount mismatch would send them looking at the amount check
            $context->result = PaymentNotificationEntity::RESULT_CURRENCY_MISMATCH;

            throw PaymentOrderException::currencyMismatch($order->paymentNo, $order->currency, $result->currency);
        }

        if ($refund instanceof PaymentRefundEntity) {
            $this->refundService->applyNotification($refund, $order, $result);

            // the notification is applied to the refund, so it is fully processed - whether the
            // refund is settled by it or stays processing is not an error of the notification
            $context->result = PaymentNotificationEntity::RESULT_OK;

            return;
        }

        $this->applyPayment($order, $result, $context);
    }

    private function extractReferences(PaymentNotificationProcessingContext $context): PaymentNotificationData
    {
        $provider = $this->providerRegistry->get($context->providerTechnicalName);

        return $provider->notificationExtractor->extract($context->rawBody, $context->headers);
    }

    /**
     * The extracted references are not trusted yet, they only tell us which order the notification
     * is about - which in turn tells us which credentials are needed to verify it.
     */
    private function resolveAttempt(PaymentNotificationData $data, ?string $paymentNoHint): ?PaymentAttemptEntity
    {
        if ($paymentNoHint !== null && $paymentNoHint !== '') {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('attemptNo', $paymentNoHint));
        } elseif ($data->refundNo !== null) {
            $refund = $this->refundService->findByRefundNo($data->refundNo);
            if ($refund?->paymentAttemptId === null) {
                return null;
            }
            $criteria = new Criteria([$refund->paymentAttemptId]);
        } elseif ($data->paymentNo !== null) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('attemptNo', $data->paymentNo));
        } else {
            return null;
        }
        $criteria->setLimit(1);
        $attempt = $this->attemptRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        return $attempt instanceof PaymentAttemptEntity ? $attempt : null;
    }

    private function verifyNotification(PaymentNotificationProcessingContext $context, PaymentOrderEntity $order, PaymentAppEntity $app): NotificationResult
    {
        $resolved = $this->configResolver->resolveForAttempt($app, $order, $order->activeAttempt ?? throw PaymentOrderException::orderNotFound($order->paymentNo));

        $model = PaymentGatewayModel::forNotification($context->rawBody, $context->headers, $context->remoteIp);
        $model->offsetSet(PaymentGatewayModel::PAYMENT_NO, $order->activeAttempt->attemptNo);
        $gateway = $resolved->provider->gatewayFactory->createForPayment($resolved, (string) $order->notifyUrl, (string) $order->returnUrl);

        $gateway->execute(new Notify($model));

        return $model->result(NotificationResult::class, $resolved->product->technicalName);
    }

    private function amountMatches(int $expected, NotificationResult $result): bool
    {
        return $result->amount !== null && $result->amount === $expected;
    }

    private function currencyMatches(string $expected, string $actual): bool
    {
        // `cny` and `CNY` are the same currency.
        return strtoupper($actual) === strtoupper($expected);
    }

    /**
     * @throws PaymentException
     */
    private function applyPayment(PaymentOrderEntity $order, NotificationResult $result, PaymentNotificationProcessingContext $context): void
    {
        if ($order->paidAt !== null) {
            $context->result = PaymentNotificationEntity::RESULT_DUPLICATE;

            // the payment is written, but one of its consequences may have failed the first time - a
            // duplicate notification is the retry window for exactly that
            $this->paidHandler->announce($order);

            return;
        }

        // the interface reports a payment that is not settled yet, or one it gave up on. The order stays
        // payable and expires on its own, so nothing is written - the notification stays for support.
        if (!$result->succeeded) {
            $context->result = PaymentNotificationEntity::RESULT_IGNORED;

            return;
        }

        // an order may be paid without the app ever having asked for the pay parameters
        $this->paidHandler->handle($order, $result->amount ?? $order->amount, $result->outPaymentNo, $result->raw);

        // the payment is applied and the app is told about it, which is the only fully successful
        // outcome: every branch above either returns early or throws
        $context->result = PaymentNotificationEntity::RESULT_OK;
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

    private function conclude(string $notificationId, PaymentNotificationProcessingContext $context): void
    {
        $this->notificationRepository->update([[
            'id' => $notificationId,
            'paymentOrderId' => $context->orderId,
            'paymentAttemptId' => $context->attemptId,
            'signatureValid' => $context->signatureValid,
            'processed' => \in_array($context->result, [
                PaymentNotificationEntity::RESULT_OK,
                PaymentNotificationEntity::RESULT_DUPLICATE,
                PaymentNotificationEntity::RESULT_IGNORED,
            ], true),
            // 32 is the width of the column: every result constant is far shorter, and a shortened one
            // would read like a different outcome in exactly the table support diagnoses with
            'processResult' => mb_substr($context->result, 0, 32),
            'errorMessage' => $context->message === null ? null : mb_substr($context->message, 0, 512),
        ]], Context::createDefaultContext());
    }

    private function getNotification(string $id): PaymentNotificationEntity
    {
        $notification = $this->notificationRepository->search(new Criteria([$id]), Context::createDefaultContext())->getEntities()->first();

        if (!$notification instanceof PaymentNotificationEntity) {
            throw PaymentNotificationException::notificationNotFound($id);
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
