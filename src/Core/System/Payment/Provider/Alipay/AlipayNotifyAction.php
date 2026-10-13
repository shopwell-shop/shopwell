<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Alipay;

use Alipay\OpenAPISDK\Util\AlipaySignature;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Notify;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentAction;
use Shopwell\Core\System\Payment\Gateway\Money;
use Shopwell\Core\System\Payment\Gateway\NotificationResult;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\PaymentException;

/**
 * Verifies and reads the asynchronous notification of alipay.
 *
 * Alipay posts a form encoded body that carries `out_trade_no` - the payment number of the platform -
 * signed with the private key of the app. The notification is verified with the alipay public key of
 * that very app, which is why the action runs behind the gateway of the resolved credentials.
 *
 * @internal
 */
#[Package('framework')]
final class AlipayNotifyAction extends AbstractPaymentAction
{
    /**
     * The trade states alipay reports for a paid trade.
     */
    final public const PAID_STATES = ['TRADE_SUCCESS', 'TRADE_FINISHED'];

    final public const DEFAULT_CURRENCY = 'CNY';

    protected string $apiClass = AlipayApi::class;

    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = $this->modelOf($request);
        $api = $this->api();

        $parameters = $this->parseBody($this->stringValue($model, PaymentGatewayModel::RAW_BODY));

        $this->recordResponse($model, $parameters);

        $signatureValid = false;
        if (isset($parameters['sign'])) {
            try {
                $signatureValid = AlipaySignature::rsaCheckV1(
                    $parameters,
                    $api->publicKey(),
                    $this->signTypeOf($parameters, $api)
                );
            } catch (\Throwable) {
                $signatureValid = false;
            }
        }

        $status = (string) ($parameters['trade_status'] ?? '');
        $currency = strtoupper((string) ($parameters['currency'] ?? self::DEFAULT_CURRENCY));

        $this->setResult($model, new NotificationResult(
            signatureValid: $signatureValid,
            succeeded: \in_array($status, self::PAID_STATES, true),
            outPaymentNo: isset($parameters['trade_no']) ? (string) $parameters['trade_no'] : null,
            amount: $this->amountOf($parameters, $currency),
            currency: $currency,
            status: $status,
            raw: $parameters,
        ));
    }

    public function supports($request): bool
    {
        return $this->isSupported($request, Notify::class);
    }

    protected function api(): AlipayApi
    {
        $api = parent::api();
        \assert($api instanceof AlipayApi);

        return $api;
    }

    /**
     * @return array<string, string>
     */
    private function parseBody(string $body): array
    {
        $parsed = [];
        parse_str($body, $parsed);

        $parameters = [];
        foreach ($parsed as $key => $value) {
            if (\is_string($value)) {
                $parameters[(string) $key] = $value;
            }
        }

        return $parameters;
    }

    /**
     * @param array<string, string> $parameters
     */
    private function signTypeOf(array $parameters, AlipayApi $api): string
    {
        // the sign type of the notification itself wins, otherwise alipay would have signed with
        // another algorithm than the one configured for the app
        $signType = strtoupper((string) ($parameters['sign_type'] ?? ''));

        return $signType === '' ? $api->signType() : $signType;
    }

    /**
     * @param array<string, string> $parameters
     */
    private function amountOf(array $parameters, string $currency): ?int
    {
        $amount = $parameters['total_amount'] ?? null;

        if ($amount === null || $amount === '') {
            return null;
        }

        try {
            return Money::toMinor($amount, $currency);
        } catch (PaymentException) {
            return null;
        }
    }
}
