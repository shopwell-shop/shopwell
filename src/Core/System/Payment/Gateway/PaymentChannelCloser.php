<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Gateway;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\NumberRange\ValueGenerator\AbstractNumberRangeValueGenerator;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\Exception\PaymentGatewayException;
use Shopwell\Core\System\Payment\Exception\PaymentOrderException;
use Shopwell\Core\System\Payment\Exception\PaymentProviderException;
use Shopwell\Core\System\Payment\Gateway\Request\CloseResult;
use Shopwell\Core\System\Payment\Gateway\Request\PaymentClose;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentTransaction\PaymentTransactionCollection;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentTransaction\PaymentTransactionEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;
use Shopwell\Core\System\Payment\Provider\Config\PaymentConfigResolver;

/**
 * Closes a payment order at its channel and answers what the channel said about it.
 *
 * This is the gateway side of closing, the counterpart of the {@see PaymentOrderStarter}: the caller
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
final class PaymentChannelCloser
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
        private readonly AbstractNumberRangeValueGenerator $numberRanges,
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
            throw PaymentOrderException::orderNotFound($order->paymentNo);
        }

        $resolved = $this->configResolver->resolveForOrder($app, $order);

        // An interface that never declared a close cannot be asked to close. Payum answers a request
        // no action of the interface supports with an error *inside* the gateway, so the catch below
        // could only report it as a channel fault - a 502 "the channel returned an error" for a fact
        // the declaration knew before a single request was signed. The guard therefore runs here, on
        // the resolved provider (the end of the decoration chain, whose capabilities are the union of
        // the whole chain) and before the attempt is booked: a call that can never reach the channel
        // must not leave a failed transaction behind.
        if (!$resolved->provider->supports(PaymentCapability::CLOSE) || !$resolved->product->supports(PaymentCapability::CLOSE)) {
            throw PaymentProviderException::providerLacksCapability($resolved->provider->technicalName, PaymentCapability::CLOSE->value);
        }

        $model = PaymentGatewayModel::forOrder($order);
        $gateway = $resolved->provider->gatewayFactory->createForPayment($resolved, (string) $order->notifyUrl, (string) $order->returnUrl);

        $transactionId = $this->startTransaction($order, $resolved->provider->technicalName, $resolved->product->technicalName);

        try {
            $gateway->execute(new PaymentClose($model));
            $result = $model->result(CloseResult::class, $resolved->product->technicalName);
        } catch (\Throwable $exception) {
            $this->finishTransaction($transactionId, PaymentTransactionEntity::STATUS_FAILED, $model, $exception);

            // the action already says what the channel answered - a rejection of the channel must not
            // be reworded by the service around it
            throw $exception instanceof PaymentException
                ? $exception
                : PaymentGatewayException::gatewayError($resolved->product->technicalName, $exception->getMessage(), $exception);
        }

        $this->finishTransaction($transactionId, PaymentTransactionEntity::STATUS_SUCCESS, $model);

        return $result;
    }

    private function startTransaction(PaymentOrderEntity $order, string $providerTechnicalName, string $productName): string
    {
        $id = Uuid::randomHex();

        $this->transactionRepository->create([[
            'id' => $id,
            'paymentOrderId' => $order->id,
            'paymentAttemptId' => $order->activeAttemptId,
            'transactionNo' => $this->numberRanges->getValue(PaymentTransactionEntity::ENTITY_NAME, Context::createDefaultContext(), null),
            'type' => self::TRANSACTION_TYPE_CLOSE,
            'providerTechnicalName' => $providerTechnicalName,
            'channelTechnicalName' => $productName,
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
