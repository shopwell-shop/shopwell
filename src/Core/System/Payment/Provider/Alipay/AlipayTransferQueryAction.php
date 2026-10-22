<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Alipay;

use Payum\Core\Exception\RequestNotSupportedException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentAction;
use Shopwell\Core\System\Payment\Gateway\PaymentTransferQuery;
use Shopwell\Core\System\Payment\Gateway\TransferQueryResult;
use Shopwell\Core\System\Payment\Gateway\TransferResult;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;

/**
 * Asks alipay what happened to a payout, `alipay.fund.trans.common.query`.
 *
 * This is the query of the transfers of the new interface and of the legacy one alike - `order.query`
 * only knows the documents of the legacy interface and would answer "unknown transfer" for every
 * payout this platform sends. Queried by the out business number, alipay demands the very
 * `product_code` and `biz_scene` the submission was built with, which is why the recipient rides
 * along on the request: its identity type decides both.
 *
 * `code=10000` again only says the call was taken - the `status` of the answer is what the transfer
 * becomes, and `DEALING` after a few minutes is the normal course of a payout, not an error.
 *
 * @internal
 */
#[Package('framework')]
final class AlipayTransferQueryAction extends AbstractPaymentAction
{
    protected string $apiClass = AlipayApi::class;

    public function capability(): PaymentCapability
    {
        return PaymentCapability::TRANSFER_QUERY;
    }

    /**
     * A channel failure leaves this method as a {@see PaymentException} of code `GATEWAY_ERROR` - the
     * query that was sent is recorded on the model before the exception is thrown, so the caller can
     * persist what was asked even though the call failed. The exception is documented here because
     * that is the contract a caller catches by: without the tag the failure looks like a
     * `RequestNotSupportedException` of the payum interface and nothing else.
     *
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

        $productCode = AlipayTransferAction::PRODUCT_CODES[$request->recipient->identityType]
            ?? throw PaymentException::transferIdentityNotSupported($request->transfer->provider, $request->recipient->identityType);

        $this->recordRequest($model, [
            'method' => AlipayApi::METHOD_COMMON_QUERY,
            'out_biz_no' => $request->transfer->outNo,
            'product_code' => $productCode,
            'biz_scene' => AlipayTransferAction::BIZ_SCENE,
        ]);

        try {
            $answer = $api->transferQuery($request->transfer->outNo, $productCode, AlipayTransferAction::BIZ_SCENE);
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
     * The answer of the interface, narrowed to what a transfer means - the same states the
     * submission answers with, next to the status string the interface used, unchanged, because a
     * support case is debugged with it.
     *
     * A rejected query is no answer about the transfer at all: it stays unknown, the next query
     * decides.
     *
     * @param array<string, mixed> $answer
     */
    public static function resultOf(array $answer): TransferQueryResult
    {
        $code = self::stringOf($answer, 'code');

        if ($code !== '' && $code !== AlipayApi::TRANSFER_CALL_OK) {
            $subCode = self::stringOf($answer, 'sub_code');

            return new TransferQueryResult(TransferResult::STATE_UNKNOWN, providerStatus: $subCode !== '' ? $subCode : $code);
        }

        $status = self::stringOf($answer, 'status');

        $state = match ($status) {
            'SUCCESS' => TransferResult::STATE_SUCCEEDED,
            'FAIL' => TransferResult::STATE_FAILED,
            'DEALING' => TransferResult::STATE_PROCESSING,
            'REFUND' => TransferResult::STATE_RETURNED,
            default => TransferResult::STATE_UNKNOWN,
        };

        $errorCode = self::stringOf($answer, 'error_code');

        return new TransferQueryResult(
            $state,
            providerStatus: $status,
            providerRef: self::stringOf($answer, 'order_id') ?: null,
            failureCode: $state === TransferResult::STATE_FAILED
                ? ($errorCode !== '' ? $errorCode : null)
                : null,
        );
    }

    protected function api(): AlipayApi
    {
        $api = parent::api();
        \assert($api instanceof AlipayApi);

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
