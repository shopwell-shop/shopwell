<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Wechat;

use Payum\Core\Exception\RequestNotSupportedException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentAction;
use Shopwell\Core\System\Payment\Gateway\PaymentTransferQuery;
use Shopwell\Core\System\Payment\Gateway\TransferQueryResult;
use Shopwell\Core\System\Payment\Gateway\TransferResult;
use Shopwell\Core\System\Payment\PaymentException;

/**
 * Asks wechat what happened to a merchant transfer,
 * `GET /v3/fund-app/mch-transfer/transfer-bills/out-bill-no/{out_bill_no}`.
 *
 * The bill is queried by the out number of the merchant, which is the idempotency key of the transfer
 * and stays the same across retries. Wechat answers for the last 30 days only: a bill older than that
 * is gone, and the query answers unknown instead of failing - the transfer's own record is the only
 * evidence left at that point.
 *
 * A transfer whose submission never opened a bill has no provider reference and nothing wechat could be
 * asked about - the query answers unknown without a call, exactly like the stripe and paypal queries do.
 *
 * The states are the ones of {@see WechatTransferAction} - the mapping lives there and is not copied
 * here, so a submission and a query can never disagree about what a `CANCELLED` bill means. When the
 * bill waits for the shopper the answer carries the `package_info` again, which is what a client needs
 * to let the shopper accept a transfer whose submission answer was lost.
 *
 * @internal
 */
#[Package('framework')]
final class WechatTransferQueryAction extends AbstractPaymentAction
{
    protected string $apiClass = WechatApi::class;

    /**
     * @throws PaymentException
     */
    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        if (!$request instanceof PaymentTransferQuery) {
            throw RequestNotSupportedException::create($request);
        }

        $api = $this->api();
        $model = $this->modelOf($request);

        $providerRef = $request->transfer->providerRef;

        if ($providerRef === null || $providerRef === '') {
            // the submission never opened a bill: there is nothing the interface could be asked about
            $this->setResult($model, new TransferQueryResult(TransferResult::STATE_UNKNOWN, providerStatus: ''));

            return;
        }

        $this->recordRequest($model, ['out_bill_no' => $providerRef]);

        try {
            $answer = $api->queryTransfer($providerRef);
        } catch (\Throwable $exception) {
            $this->recordResponse($model, ['error' => $exception->getMessage()]);

            throw PaymentException::gatewayError($api->channel(), $exception->getMessage(), $exception);
        }

        $this->recordResponse($model, $answer);

        $this->setResult($model, self::resultOf($answer));
    }

    public function supports($request): bool
    {
        return $this->isSupported($request, PaymentTransferQuery::class);
    }

    /**
     * The answer of the interface, narrowed to what the bill means - the same states the submission
     * answers with, next to the status string the interface used, unchanged, because a support case is
     * debugged with it.
     *
     * A bill wechat does not know (a 404 of the api, mapped to an error by {@see WechatApi}) is no
     * answer about the transfer at all: it stays unknown, and nothing is booked from it.
     *
     * @param array<string, mixed> $answer
     */
    public static function resultOf(array $answer): TransferQueryResult
    {
        if (isset($answer['error'])) {
            $error = \is_array($answer['error']) ? $answer['error'] : [];
            $code = \is_string($error['code'] ?? null) && $error['code'] !== '' ? $error['code'] : '';

            return new TransferQueryResult(TransferResult::STATE_UNKNOWN, providerStatus: $code);
        }

        $state = self::stringOf($answer, 'state');
        $mapped = WechatTransferAction::stateOf($state);

        return new TransferQueryResult(
            $mapped,
            providerStatus: $state,
            providerRef: WechatTransferAction::providerRefOf($answer),
            // the shopper still has to accept - the one time payload rides along again
            confirmPayload: $mapped === TransferResult::STATE_AWAITING_CONFIRM
                ? WechatTransferAction::confirmPayloadOf($answer)
                : null,
            failureCode: WechatTransferAction::failureCodeOf($answer, $state),
        );
    }

    protected function api(): WechatApi
    {
        $api = parent::api();
        \assert($api instanceof WechatApi);

        return $api;
    }

    /**
     * @param array<string, mixed> $answer
     */
    private static function stringOf(array $answer, string $key): string
    {
        $value = $answer[$key] ?? null;

        return \is_scalar($value) ? (string) $value : '';
    }
}
