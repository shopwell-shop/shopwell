<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Service;

use Payum\Core\Request\Capture;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentAppEntity;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentOrderEntity;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentTransactionCollection;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentTransactionEntity;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\PayResult;
use Shopwell\Core\System\Payment\PaymentException;

/**
 * Hands a payment order over to its channel and answers the app with the parameters it needs to send
 * the payer to the channel.
 *
 * Every attempt is kept as a payment transaction, including the request and the answer of the channel.
 * The caller may call this more than once for the very same order, e.g. when the payer switched the
 * device: the order may be attempted again while it is not paid yet, but it is never paid twice.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentCaptureService
{
    /**
     * @param EntityRepository<PaymentTransactionCollection> $transactionRepository
     */
    public function __construct(
        private readonly EntityRepository $transactionRepository,
        private readonly PaymentGatewayFactory $gatewayFactory,
        private readonly PaymentConfigResolver $configResolver,
        private readonly PaymentOrderService $orderService,
        private readonly PaymentNotifyUrlBuilder $notifyUrlBuilder,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @throws PaymentException
     */
    public function capture(PaymentAppEntity $app, PaymentOrderEntity $order): PayResult
    {
        if (!$this->orderService->isPayable($order)) {
            throw PaymentException::orderNotPayable($order->paymentNo);
        }

        // the runtime parameters of this payment were handed in when the order was created, so a second
        // attempt - the payer switched the device, for instance - is charged with the very same ones
        $resolved = $this->configResolver->resolve(
            $app,
            $order->channelTechnicalName,
            $order->currency,
            $order->device,
            $order->channelParams ?? []
        );

        $model = PaymentGatewayModel::forOrder($order);
        $gateway = $this->gatewayFactory->create(
            $resolved,
            $this->notifyUrlBuilder->build($app, $resolved->provider->technicalName, $order->paymentNo),
            (string) $order->returnUrl
        );

        $transactionId = $this->startTransaction($order, $resolved->provider->technicalName);

        try {
            $gateway->execute(new Capture($model));
            $result = PaymentGatewayModel::payResult($model, $order->channelTechnicalName);
        } catch (\Throwable $exception) {
            $this->finishTransaction($transactionId, PaymentTransactionEntity::STATUS_FAILED, $model, null, $exception);

            throw PaymentException::gatewayError($order->channelTechnicalName, $exception->getMessage());
        }

        $this->finishTransaction($transactionId, PaymentTransactionEntity::STATUS_SUCCESS, $model, $result->outPaymentNo);
        $this->orderService->markOpened($order);

        return $result;
    }

    private function startTransaction(PaymentOrderEntity $order, string $providerTechnicalName): string
    {
        $id = Uuid::randomHex();

        $this->transactionRepository->create([[
            'id' => $id,
            'paymentOrderId' => $order->id,
            'transactionNo' => 'PT' . $this->clock->now()->format('YmdHis') . strtoupper(bin2hex(random_bytes(3))),
            'type' => PaymentTransactionEntity::TYPE_CAPTURE,
            'providerTechnicalName' => $providerTechnicalName,
            'channelTechnicalName' => $order->channelTechnicalName,
            'status' => PaymentTransactionEntity::STATUS_PENDING,
        ]], Context::createDefaultContext());

        return $id;
    }

    /**
     * @param \ArrayObject<string, mixed> $model
     */
    private function finishTransaction(
        string $transactionId,
        string $status,
        \ArrayObject $model,
        ?string $outTransactionId,
        ?\Throwable $exception = null
    ): void {
        $response = PaymentGatewayModel::responseOf($model);

        if ($exception !== null) {
            $response['exception'] = $exception->getMessage();
        }

        $this->transactionRepository->update([[
            'id' => $transactionId,
            'requestPayload' => PaymentGatewayModel::requestOf($model),
            'responsePayload' => $response,
            'outTransactionId' => $outTransactionId,
            'status' => $status,
            'errorCode' => $exception === null ? null : (string) $exception->getCode(),
            'errorMessage' => $exception === null ? null : mb_substr($exception->getMessage(), 0, 512),
        ]], Context::createDefaultContext());
    }
}
