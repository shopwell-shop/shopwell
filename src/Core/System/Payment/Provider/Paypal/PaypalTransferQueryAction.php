<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Paypal;

use Payum\Core\Exception\RequestNotSupportedException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentAction;
use Shopwell\Core\System\Payment\Gateway\PaymentTransferQuery;
use Shopwell\Core\System\Payment\Gateway\TransferQueryResult;
use Shopwell\Core\System\Payment\Gateway\TransferResult;

/**
 * Asks paypal what happened to a payout, `GET /v1/payments/payouts/{payout_batch_id}`.
 *
 * The batch is the object the transfer query is made for - it is the id the submission kept as the
 * provider reference of the transfer.
 *
 * **The item is the truth, the batch is the fallback.** A batch answered as `SUCCESS` may still hold
 * an item that is `UNCLAIMED` (the recipient has no paypal account yet, the money sits unclaimed for
 * 30 days and then returns), so the batch status alone may not be read as "the money arrived". The
 * answer of the query carries the items, and `items[0].transaction_status` is what the transfer is
 * converged from; only an answer without items falls back to the batch status.
 *
 * A transfer whose payout never opened a batch has no provider reference and nothing paypal could be
 * asked about - the query answers unknown without a call, exactly like the stripe query does.
 *
 * @internal
 */
#[Package('framework')]
final class PaypalTransferQueryAction extends AbstractPaymentAction
{
    protected string $apiClass = PaypalApi::class;

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
            // the payout never opened a batch: there is nothing the interface could be asked about
            $this->setResult($model, new TransferQueryResult(TransferResult::STATE_UNKNOWN, providerStatus: ''));

            return;
        }

        $this->recordRequest($model, ['payout_batch_id' => $providerRef]);

        // the api answers a rejection instead of throwing, so a failed query stays an answer that
        // resultOf() maps - the next query decides
        $answer = $api->retrievePayout($providerRef);

        $this->recordResponse($model, $answer);

        $this->setResult($model, self::resultOf($answer));
    }

    public function supports($request): bool
    {
        return $this->isSupported($request, PaymentTransferQuery::class);
    }

    /**
     * The answer of the interface, narrowed to what the payout means - the same states the submission
     * answers with, next to the status string the interface used, unchanged, because a support case is
     * debugged with it.
     *
     * A rejected query is no answer about the payout at all: it stays unknown, the next query decides.
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

        $batch = \is_array($answer['batch_header'] ?? null) ? $answer['batch_header'] : [];
        $items = \is_array($answer['items'] ?? null) ? $answer['items'] : [];
        $item = \is_array($items[0] ?? null) ? $items[0] : null;

        $providerRef = self::stringOf($batch, 'payout_batch_id') ?: null;
        $itemStatus = $item === null ? '' : self::stringOf($item, 'transaction_status');

        if ($itemStatus !== '') {
            $state = PaypalTransferAction::stateOfItem($itemStatus);

            return new TransferQueryResult(
                $state,
                providerStatus: $itemStatus,
                providerRef: $providerRef,
                failureCode: \in_array($state, [TransferResult::STATE_FAILED, TransferResult::STATE_RETURNED], true)
                    ? PaypalTransferAction::failureCodeOfItem($item, $itemStatus)
                    : null,
            );
        }

        // no item to read - the batch status is all the answer carries
        $status = self::stringOf($batch, 'batch_status');
        $state = PaypalTransferAction::stateOfBatch($status);

        return new TransferQueryResult(
            $state,
            providerStatus: $status,
            providerRef: $providerRef,
            failureCode: $state === TransferResult::STATE_FAILED ? $status : null,
        );
    }

    protected function api(): PaypalApi
    {
        $api = parent::api();
        \assert($api instanceof PaypalApi);

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
