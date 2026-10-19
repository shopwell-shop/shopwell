<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Wechat;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\PaymentException;

/**
 * Reads the platform certificates out of the credentials of a wechat merchant account.
 *
 * A notification is verified with the certificate of the platform, and the platform rotates them: the
 * `Wechatpay-Serial` header names the one that signed, and an installation may hold the old and the
 * new one at the same time. Two ways of configuring them are kept - one certificate with its serial,
 * or a json map of serial to certificate - and both end up in the same map here, so the api and the
 * notification verifier read them the same way.
 *
 * @internal
 */
#[Package('framework')]
final class WechatPlatformCertificates
{
    /**
     * @param array<string, mixed> $credentials
     *
     * @throws PaymentException when no certificate is configured at all
     *
     * @return array<string, string> certificate content indexed by its serial
     */
    public static function of(array $credentials): array
    {
        $certs = $credentials[WechatApi::PLATFORM_CERTS] ?? null;

        if (\is_string($certs)) {
            $decoded = json_decode($certs, true);
            $certs = \is_array($decoded) ? $decoded : [];
        }

        $normalized = [];
        if (\is_array($certs)) {
            foreach ($certs as $serial => $certificate) {
                if (\is_scalar($certificate) && (string) $certificate !== '') {
                    $normalized[(string) $serial] = (string) $certificate;
                }
            }
        }

        $single = $credentials[WechatApi::PLATFORM_CERT] ?? null;
        $singleSerial = $credentials[WechatApi::PLATFORM_SERIAL] ?? null;

        if (\is_scalar($single) && (string) $single !== '') {
            $serial = \is_scalar($singleSerial) && (string) $singleSerial !== '' ? (string) $singleSerial : 'platform';
            $normalized[$serial] = (string) $single;
        }

        if ($normalized === []) {
            throw PaymentException::missingCredentials(WechatApi::PROVIDER, WechatApi::PLATFORM_CERTS);
        }

        return $normalized;
    }

    /**
     * The certificate a serial names.
     *
     * An unknown serial is not necessarily an attack: a channel may rotate its certificates, which is
     * why a single configured certificate is used for every serial. With several configured and none
     * named, guessing would verify against a certificate the channel did not sign with.
     *
     * @param array<string, mixed> $credentials
     *
     * @throws PaymentException when no certificate is configured at all
     */
    public static function certificateOf(array $credentials, ?string $serial): ?string
    {
        $certs = self::of($credentials);

        if ($serial === null || $serial === '' || !isset($certs[$serial])) {
            return \count($certs) === 1 ? (string) reset($certs) : null;
        }

        return (string) $certs[$serial];
    }
}
