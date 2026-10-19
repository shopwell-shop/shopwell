<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Alipay;

use Alipay\OpenAPISDK\Util\AlipaySignature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Notification\Struct\PaymentNotificationAck;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Transfer\Notification\PaymentTransferNotificationVerifierInterface;

/**
 * Verifies the ant message `alipay.fund.trans.order.changed`.
 *
 * Alipay has no `notify_url` on the payout call: the fate of a transfer arrives as an ant message on
 * the application gateway, in the very same form encoded, RSA signed shape the payment notification
 * has. The platform number it was given is `out_biz_no`, which is what the message carries back.
 *
 * The signature is checked with the alipay public key of the merchant account; the sign type of the
 * message wins over the configured one for the same reason the payment notification does it - alipay
 * signs with the algorithm it announced.
 *
 * @internal
 */
#[Package('framework')]
final class AlipayTransferNotificationVerifier implements PaymentTransferNotificationVerifierInterface
{
    /**
     * The reference the platform handed alipay as the business number of the payout.
     */
    private const OUT_NO = 'out_biz_no';

    public function provider(): string
    {
        return AlipayApi::PROVIDER;
    }

    public function verify(array $credentials, string $rawBody, array $headers): bool
    {
        $parameters = self::parametersOf($rawBody);

        $signature = $parameters['sign'] ?? null;

        if (!\is_string($signature) || $signature === '') {
            return false;
        }

        $publicKey = $credentials[AlipayApi::PUBLIC_KEY] ?? null;

        if (!\is_string($publicKey) || $publicKey === '') {
            throw PaymentException::missingCredentials(AlipayApi::PROVIDER, AlipayApi::PUBLIC_KEY);
        }

        try {
            return AlipaySignature::rsaCheckV1(
                $parameters,
                AlipayApi::stripPem($publicKey),
                self::signTypeOf($parameters, $credentials)
            );
        } catch (\Throwable) {
            // a verification that blows up is a verification that failed
            return false;
        }
    }

    public function outNoOf(array $credentials, string $rawBody, array $headers): ?string
    {
        $outNo = self::parametersOf($rawBody)[self::OUT_NO] ?? null;

        return $outNo === '' ? null : $outNo;
    }

    /**
     * Alipay repeats a notification until it reads the plain word `success`.
     */
    public function acknowledge(): PaymentNotificationAck
    {
        return new PaymentNotificationAck('success');
    }

    /**
     * The ant message is form encoded, so every value `parse_str` reads is a string. An empty value is
     * kept as an empty string and treated as absent by the caller.
     *
     * @return array<string, string>
     */
    private static function parametersOf(string $rawBody): array
    {
        $parsed = [];
        parse_str($rawBody, $parsed);

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
     * @param array<string, mixed> $credentials
     */
    private static function signTypeOf(array $parameters, array $credentials): string
    {
        $signType = strtoupper($parameters[AlipayApi::SIGN_TYPE] ?? '');

        if ($signType !== '') {
            return $signType;
        }

        $configured = $credentials[AlipayApi::SIGN_TYPE] ?? null;

        return \is_string($configured) && $configured !== ''
            ? strtoupper($configured)
            : AlipayApi::DEFAULT_SIGN_TYPE;
    }
}
