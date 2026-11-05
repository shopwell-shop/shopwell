<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Order;

use Payum\Core\Request\Capture;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\NumberRange\ValueGenerator\AbstractNumberRangeValueGenerator;
use Shopwell\Core\System\Payment\Account\PaymentAccountLock;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\Exception\PaymentGatewayException;
use Shopwell\Core\System\Payment\Exception\PaymentOrderException;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\Struct\PaymentHandover;
use Shopwell\Core\System\Payment\Gateway\Struct\PaymentStartResult;
use Shopwell\Core\System\Payment\Notification\PaymentNotifyUrlBuilder;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentAttempt\PaymentAttemptCollection;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentAttempt\PaymentAttemptEntity;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentTransaction\PaymentTransactionCollection;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentTransaction\PaymentTransactionEntity;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\ResolvedPaymentConfig;
use Shopwell\Core\System\Payment\Routing\PaymentRoute;

/**
 * Hands a payment order over to its channel and answers the app with the parameters it needs to send
 * the payer to the channel.
 *
 * A durable payment attempt owns the selected route; transactions audit its provider requests.
 * A successful handover is replayed. An unanswered attempt is reconciled instead of resubmitted:
 * provider idempotency windows expire, so a lost answer must never turn into a second trade.
 *
 * @internal
 */
#[Package('framework')]
class PaymentOrderStarter
{
    /**
     * @param EntityRepository<PaymentTransactionCollection> $transactionRepository
     * @param EntityRepository<PaymentAttemptCollection> $attemptRepository
     * @param EntityRepository<PaymentOrderCollection> $orderRepository
     */
    public function __construct(
        private readonly EntityRepository $transactionRepository,
        private readonly EntityRepository $attemptRepository,
        private readonly EntityRepository $orderRepository,
        private readonly PaymentOrderService $orderService,
        private readonly PaymentNotifyUrlBuilder $notifyUrlBuilder,
        private readonly AbstractNumberRangeValueGenerator $numberRanges,
        private readonly PaymentOrderRowLock $rowLock,
        private readonly PaymentAccountLock $accountLock,
    ) {
    }

    /**
     * @throws PaymentException
     */
    public function start(PaymentAppEntity $app, PaymentOrderEntity $order, PaymentRoute $route): PaymentStartResult
    {
        if (!$this->orderService->isPayable($order)) {
            throw PaymentOrderException::orderNotPayable($order->paymentNo);
        }

        $claim = $this->claim($order, $route);
        if ($claim instanceof PaymentStartResult) {
            return $claim;
        }
        [$order, $attempt, $resolved, $transactionId] = $claim;

        $model = PaymentGatewayModel::forOrder($order, $attempt);
        $gateway = $resolved->provider->gatewayFactory->createForPayment(
            $resolved,
            $this->notifyUrlBuilder->build($app, $resolved->provider->technicalName, $attempt->attemptNo),
            (string) $order->returnUrl
        );

        try {
            $gateway->execute(new Capture($model));
            $result = $model->result(PaymentStartResult::class, $attempt->channelTechnicalName);
            if ($result->params->flow !== $attempt->interaction) {
                throw PaymentGatewayException::gatewayResultMissing($attempt->channelTechnicalName);
            }
        } catch (\Throwable $exception) {
            $this->finishAttempt($attempt, PaymentAttemptEntity::STATUS_UNKNOWN, $model, $transactionId, exception: $exception);

            throw PaymentGatewayException::gatewayError($attempt->channelTechnicalName, $exception->getMessage(), $exception);
        }

        $model->offsetSet(PaymentGatewayModel::RESPONSE, [...$model->response(), 'handover' => $result->params->jsonSerialize()]);
        $this->finishAttempt($attempt, PaymentAttemptEntity::STATUS_HANDOVER, $model, $transactionId, $result);

        return $result;
    }

    public function replay(PaymentOrderEntity $order): ?PaymentStartResult
    {
        $attempt = $order->activeAttempt;
        if ($attempt === null) {
            // An id without its association is never permission to open another provider trade.
            if ($order->activeAttemptId !== null) {
                throw PaymentOrderException::startPending($order->paymentNo);
            }

            return null;
        }

        if ($attempt->status === PaymentAttemptEntity::STATUS_FAILED) {
            return null;
        }

        $handover = $attempt->handover;
        if ($attempt->status === PaymentAttemptEntity::STATUS_HANDOVER && $handover !== null) {
            /** @var array{flow: string, pay_url?: string, http_method?: string, html_form?: string, extra?: array<string, mixed>} $handover */
            return new PaymentStartResult(new PaymentHandover(
                flow: $handover['flow'],
                payUrl: $handover['pay_url'] ?? null,
                httpMethod: $handover['http_method'] ?? 'GET',
                htmlForm: $handover['html_form'] ?? null,
                extra: $handover['extra'] ?? [],
            ), $attempt->outPaymentNo);
        }

        throw PaymentOrderException::startPending($order->paymentNo);
    }

    /**
     * @return array{PaymentOrderEntity, PaymentAttemptEntity, ResolvedPaymentConfig, string}|PaymentStartResult
     */
    private function claim(PaymentOrderEntity $order, PaymentRoute $route): array|PaymentStartResult
    {
        return $this->rowLock->transaction(function () use ($order, $route): array|PaymentStartResult {
            if ($this->rowLock->lockOrder($order->id) === null) {
                throw PaymentOrderException::orderNotFound($order->paymentNo);
            }

            // Re-read after acquiring the lock: a concurrent caller may have started or settled it.
            $order = $this->orderService->getById($order->id);
            if (!$this->orderService->isPayable($order)) {
                throw PaymentOrderException::orderNotPayable($order->paymentNo);
            }
            $replayed = $this->replay($order);
            if ($replayed !== null) {
                return $replayed;
            }

            $resolved = $route->config;
            $this->accountLock->validateConfiguration($resolved);
            $attempt = $this->createAttempt($order, $route);
            $this->orderRepository->update([[
                'id' => $order->id,
                'activeAttemptId' => $attempt->id,
            ]], Context::createDefaultContext());
            $this->orderService->markOpened($order);
            $transactionId = $this->startTransaction($order, $attempt);

            return [$order, $attempt, $resolved, $transactionId];
        });
    }

    private function createAttempt(PaymentOrderEntity $order, PaymentRoute $route): PaymentAttemptEntity
    {
        $attempt = new PaymentAttemptEntity();
        $attempt->id = Uuid::randomHex();
        $attempt->paymentOrderId = $order->id;
        $attempt->attemptNo = 'p' . $attempt->id;
        $attempt->accountId = $route->config->accountId;
        $attempt->method = $route->config->product->method;
        $attempt->providerTechnicalName = $route->config->provider->technicalName;
        $attempt->channelTechnicalName = $route->config->product->technicalName;
        $attempt->interaction = $route->interaction;
        $attempt->status = PaymentAttemptEntity::STATUS_PENDING;
        $attempt->context = [
            'scene' => $route->context->scene->name,
            'device' => $route->context->scene->device,
            'environment' => $route->context->scene->environment,
            'currency' => $route->context->currency,
            'supported_interactions' => $route->context->supportedInteractions,
            'supported_sdk_products' => $route->context->supportedSdkProducts,
            'runtime_params' => $route->context->runtimeParams,
            'account_runtime_params' => $route->context->accountRuntimeParams,
            'policy' => $route->policyName,
        ];
        $params = $route->config->params;
        foreach ($route->config->product->params as $field) {
            if ($field->secret) {
                unset($params[$field->name]);
            }
        }
        $attempt->configuration = [
            'params' => $params,
            'runtimeParams' => $route->config->runtimeParams,
            'settings' => $route->config->settings,
        ];
        $this->attemptRepository->create([[
            'id' => $attempt->id,
            'paymentOrderId' => $attempt->paymentOrderId,
            'attemptNo' => $attempt->attemptNo,
            'accountId' => $attempt->accountId,
            'method' => $attempt->method,
            'providerTechnicalName' => $attempt->providerTechnicalName,
            'channelTechnicalName' => $attempt->channelTechnicalName,
            'interaction' => $attempt->interaction,
            'status' => $attempt->status,
            'context' => $attempt->context,
            'configuration' => $attempt->configuration,
        ]], Context::createDefaultContext());

        return $attempt;
    }

    private function finishAttempt(PaymentAttemptEntity $attempt, string $status, PaymentGatewayModel $model, string $transactionId, ?PaymentStartResult $result = null, ?\Throwable $exception = null): void
    {
        $this->rowLock->transaction(function () use ($attempt, $status, $model, $transactionId, $result, $exception): void {
            $this->rowLock->lockOrder($attempt->paymentOrderId);
            $current = $this->attemptRepository->search(new Criteria([$attempt->id]), Context::createDefaultContext())->getEntities()->first();
            if (!$current instanceof PaymentAttemptEntity) {
                throw PaymentOrderException::orderNotFound($attempt->attemptNo);
            }

            $this->finishTransaction($transactionId, $exception === null ? PaymentTransactionEntity::STATUS_SUCCESS : PaymentTransactionEntity::STATUS_UNKNOWN, $model, $result?->outPaymentNo, $exception);
            $row = [
                'id' => $attempt->id,
                'handover' => $result?->params->jsonSerialize(),
            ];
            // A synchronous callback may have already settled the attempt while Capture was running.
            if (\in_array($current->status, [PaymentAttemptEntity::STATUS_PENDING, PaymentAttemptEntity::STATUS_UNKNOWN], true)) {
                $row['status'] = $status;
                $row['channelData'] = $model->response();
            }
            if ($result?->outPaymentNo !== null && $current->outPaymentNo === null) {
                $row['outPaymentNo'] = $result->outPaymentNo;
            }
            $this->attemptRepository->update([$row], Context::createDefaultContext());
        });
    }

    private function startTransaction(PaymentOrderEntity $order, PaymentAttemptEntity $attempt): string
    {
        $id = Uuid::randomHex();

        $this->transactionRepository->create([[
            'id' => $id,
            'paymentOrderId' => $order->id,
            'paymentAttemptId' => $attempt->id,
            'transactionNo' => $this->numberRanges->getValue(PaymentTransactionEntity::ENTITY_NAME, Context::createDefaultContext(), null),
            'type' => PaymentTransactionEntity::TYPE_CAPTURE,
            'providerTechnicalName' => $attempt->providerTechnicalName,
            'channelTechnicalName' => $attempt->channelTechnicalName,
            'status' => PaymentTransactionEntity::STATUS_PENDING,
        ]], Context::createDefaultContext());

        return $id;
    }

    private function finishTransaction(
        string $transactionId,
        string $status,
        PaymentGatewayModel $model,
        ?string $outTransactionId,
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
            'outTransactionId' => $outTransactionId,
            'status' => $status,
            'errorCode' => $exception === null ? null : (string) $exception->getCode(),
            'errorMessage' => $exception === null ? null : mb_substr($exception->getMessage(), 0, 512),
        ]], Context::createDefaultContext());
    }
}
