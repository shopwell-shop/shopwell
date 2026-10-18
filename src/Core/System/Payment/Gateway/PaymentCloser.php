<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Gateway;

use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentTransaction\PaymentTransactionCollection;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentTransaction\PaymentTransactionEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\PaymentConfigResolver;

/**
 * Closes a payment order at its channel and answers what the channel said about it.
 *
 * This is the gateway side of closing, the counterpart of the {@see PaymentOrderCapture}: the caller
 * decides what the answer means for the order, this service only talks to the channel and keeps the
 * attempt as a payment transaction.
 *
 * Closing runs on the credentials of the merchant account alone - like querying and refunding, the
 * channel is addressed by the merchant order number - so it does not matter which channel opened the
 * trade.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentCloser
{
    /**
     * The type of a close attempt in `payment_transaction`. The entity names the capture only, but a
     * close is an attempt at the channel as well and support needs it the same way.
     */
    private const TRANSACTION_TYPE_CLOSE = 'close';

    /**
     * @param EntityRepository<PaymentTransactionCollection> $transactionRepository
     */
    public function __construct(
        private readonly EntityRepository $transactionRepository,
        private readonly PaymentConfigResolver $configResolver,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @throws PaymentException when the channel cannot be asked or answers something that is no
     *                          answer to a close
     */
    public function close(PaymentOrderEntity $order): CloseResult
    {
        $app = $order->paymentApp;

        if (!$app instanceof PaymentAppEntity) {
            throw PaymentException::orderNotFound($order->paymentNo);
        }

        $resolved = $this->configResolver->resolve(
            $app,
            $order->channelTechnicalName,
            $order->currency,
            $order->device,
            $order->channelParams ?? []
        );

        $model = PaymentGatewayModel::forOrder($order);
        $gateway = $resolved->provider->gatewayFactory->createForPayment($resolved, (string) $order->notifyUrl, (string) $order->returnUrl);

        $transactionId = $this->startTransaction($order, $resolved->provider->technicalName);

        try {
            $gateway->execute(new PaymentClose($model));
            $result = $model->result(CloseResult::class, $order->channelTechnicalName);
        } catch (\Throwable $exception) {
            $this->finishTransaction($transactionId, PaymentTransactionEntity::STATUS_FAILED, $model, $exception);

            // the action already says what the channel answered - a rejection of the channel must not
            // be reworded by the service around it
            throw $exception instanceof PaymentException
                ? $exception
                : PaymentException::gatewayError($order->channelTechnicalName, $exception->getMessage(), $exception);
        }

        $this->finishTransaction($transactionId, PaymentTransactionEntity::STATUS_SUCCESS, $model);

        return $result;
    }

    private function startTransaction(PaymentOrderEntity $order, string $providerTechnicalName): string
    {
        $id = Uuid::randomHex();

        $this->transactionRepository->create([[
            'id' => $id,
            'paymentOrderId' => $order->id,
            'transactionNo' => 'PT' . $this->clock->now()->format('YmdHis') . strtoupper(bin2hex(random_bytes(3))),
            'type' => self::TRANSACTION_TYPE_CLOSE,
            'providerTechnicalName' => $providerTechnicalName,
            'channelTechnicalName' => $order->channelTechnicalName,
            'status' => PaymentTransactionEntity::STATUS_PENDING,
        ]], Context::createDefaultContext());

        return $id;
    }

    private function finishTransaction(
        string $transactionId,
        string $status,
        PaymentGatewayModel $model,
        ?\Throwable $exception = null
    ): void {
        $response = $model->response();

        if ($exception !== null) {
            $response['exception'] = $exception->getMessage();
        }

        $this->transactionRepository->update([[
            'id' => $transactionId,
            'requestPayload' => $model->request(),
            'responsePayload' => $response,
            'status' => $status,
            'errorCode' => $exception === null ? null : (string) $exception->getCode(),
            'errorMessage' => $exception === null ? null : mb_substr($exception->getMessage(), 0, 512),
        ]], Context::createDefaultContext());
    }
}
