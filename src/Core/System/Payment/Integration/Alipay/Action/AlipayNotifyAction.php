<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Integration\Alipay\Action;

use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Notify;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentAction;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\Request\Money;
use Shopwell\Core\System\Payment\Gateway\Request\NotificationResult;
use Shopwell\Core\System\Payment\Integration\Alipay\Api\AlipayApi;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;

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

    public function capability(): PaymentCapability
    {
        return PaymentCapability::NOTIFICATION;
    }

    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = $this->modelOf($request);
        $api = $this->api();

        $parameters = AlipayApi::parametersOf($this->stringValue($model, PaymentGatewayModel::RAW_BODY));

        $this->recordResponse($model, $parameters);

        $signatureValid = false;

        try {
            $signatureValid = $api->verifyNotificationSignature($parameters);
        } catch (\Throwable) {
            // a verification that blows up is a verification that failed - a merchant account without
            // a public key is as unverified as a notification with a foreign signature
        }

        $status = (string) ($parameters['trade_status'] ?? '');
        $currency = strtoupper((string) ($parameters['currency'] ?? self::DEFAULT_CURRENCY));

        $this->setResult($model, new NotificationResult(
            signatureValid: $signatureValid,
            // "the trade is paid" is only an answer when it comes from alipay - a forged body that
            // claims TRADE_SUCCESS is not a success, exactly like the stripe and paypal actions read it
            succeeded: $signatureValid && \in_array($status, self::PAID_STATES, true),
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
