<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Stripe;

use Payum\Core\Exception\RequestNotSupportedException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentAction;
use Shopwell\Core\System\Payment\Gateway\PaymentTransferQuery;
use Shopwell\Core\System\Payment\Gateway\TransferQueryResult;
use Shopwell\Core\System\Payment\Gateway\TransferResult;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientEntity;

/**
 * Asks stripe what happened to the payout of a transfer, `GET /v1/payouts/{id}`.
 *
 * The payout is the object the transfer query is made for: the transfer of the first stage has no
 * status to ask about. The query rides the `Stripe-Account` header of the connected account - the
 * payout was made on its behalf, and without the header stripe would look for it on the platform
 * account and answer that it does not know it.
 *
 * A payout reported as `paid` is still not final - stripe may return it as `failed` within five
 * business days, the webhook plus the `markFailed` of the transfer service carry that edge. This
 * action only reports what stripe says right now.
 *
 * @internal
 */
#[Package('framework')]
final class StripeTransferQueryAction extends AbstractPaymentAction
{
    protected string $apiClass = StripeApi::class;

    public function capability(): PaymentCapability
    {
        return PaymentCapability::TRANSFER_QUERY;
    }

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
            // the payout never opened an object: there is nothing the interface could be asked about
            $this->setResult($model, new TransferQueryResult(TransferResult::STATE_UNKNOWN, providerStatus: ''));

            return;
        }

        // the query is made on behalf of the connected account - refusing it locally beats asking
        // stripe for a payout the platform account does not carry
        self::assertAccountKnown($request->recipient);

        $this->recordRequest($model, [
            'payout_id' => $providerRef,
            'stripe_account' => $request->recipient->providerAccountId,
        ]);

        try {
            $answer = $api->retrievePayout($providerRef, (string) $request->recipient->providerAccountId);
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
     * The answer of the interface, narrowed to what the payout means - the same states the
     * submission answers with, next to the status string the interface used, unchanged, because a
     * support case is debugged with it.
     *
     * A rejected query is no answer about the payout at all: it stays unknown, the next query
     * decides.
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

        $status = self::stringOf($answer, 'status');
        $state = StripeTransferAction::stateOf($status);

        return new TransferQueryResult(
            $state,
            providerStatus: $status,
            providerRef: self::stringOf($answer, 'id') ?: null,
            failureCode: $state === TransferResult::STATE_FAILED
                ? StripeTransferAction::failureCodeOf($status, $answer)
                : null,
        );
    }

    protected function api(): StripeApi
    {
        $api = parent::api();
        \assert($api instanceof StripeApi);

        return $api;
    }

    /**
     * @throws PaymentException
     */
    private static function assertAccountKnown(PaymentTransferRecipientEntity $recipient): void
    {
        $accountId = $recipient->providerAccountId;

        if ($accountId === null || !str_starts_with($accountId, StripeTransferAction::ACCOUNT_ID_PREFIX)) {
            throw PaymentException::transferRecipientIncomplete('provider_account_id');
        }
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
